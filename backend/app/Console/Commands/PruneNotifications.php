<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;
use Carbon\Carbon;

class PruneNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old read notifications and limit the number of notifications per user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) Setting::get('notification_retention_days', 30);
        $maxPerUser = (int) Setting::get('notification_max_per_user', 50);

        $this->info("Pruning notifications: read older than {$days} days, max {$maxPerUser} per user.");

        // 1. Delete read notifications older than X days
        $deletedOld = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('read_at', '<', Carbon::now()->subDays($days))
            ->delete();

        $this->info("Deleted {$deletedOld} old read notifications.");

        // 2. Limit maximum notifications per user
        // We need to do this for each user who has more than $maxPerUser notifications.
        // Get users with more than $maxPerUser notifications.
        $usersWithTooMany = DB::table('notifications')
            ->select('notifiable_id', 'notifiable_type')
            ->groupBy('notifiable_id', 'notifiable_type')
            ->having(DB::raw('count(*)'), '>', $maxPerUser)
            ->get();

        $deletedPerUserTotal = 0;
        foreach ($usersWithTooMany as $target) {
            // Find IDs of notifications to keep (the most recent $maxPerUser)
            $keepIds = DB::table('notifications')
                ->where('notifiable_id', $target->notifiable_id)
                ->where('notifiable_type', $target->notifiable_type)
                ->orderBy('created_at', 'desc')
                ->limit($maxPerUser)
                ->pluck('id');

            // Delete notifications for this user that are not in the 'keep' list
            $deleted = DB::table('notifications')
                ->where('notifiable_id', $target->notifiable_id)
                ->where('notifiable_type', $target->notifiable_type)
                ->whereNotIn('id', $keepIds)
                ->delete();

            $deletedPerUserTotal += $deleted;
        }

        $this->info("Deleted {$deletedPerUserTotal} notifications due to per-user limit.");

        return Command::SUCCESS;
    }
}
