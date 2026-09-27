<?php

namespace App\Console\Commands;

use App\Models\Scheme;
use App\Models\User;
use App\Models\UserSchemeBalance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillUserSchemeBalances extends Command
{
    protected $signature = 'balances:backfill {--chunk=500} {--dry-run}';

    protected $description = 'Backfill normalized user_scheme_balances from contributions and legacy user columns (idempotent)';

    public function handle(): int
    {
        $chunk = (int) $this->option('chunk') ?: 500;
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Backfilling user_scheme_balances in chunks of {$chunk}" . ($dryRun ? ' (DRY RUN)' : ''));

        // Map legacy columns by scheme name for fallback
        $columnMap = [
            'Savings' => 'ordinary_savings',
            'Ordinary Savings' => 'ordinary_savings',
            'Sav' => 'ordinary_savings',
            'Shares' => 'shares_capital',
            'Share Capital' => 'shares_capital',
            'Development' => 'development_fund_balance',
            'Building' => 'building_balance',
            'AGM' => 'agm_balance',
            'Loan Repayment' => 'loan_repayment_balance',
            'Fine' => 'fine_balance',
            'Welfare' => 'welfare_balance',
            'Lateness' => 'lateness_balance',
            'Stationery' => 'stationery_balance',
            'Loan Form' => 'loan_form_balance',
            'Others' => 'others_balance',
            'ID Card' => 'id_card_balance',
            'Emergency' => 'emergency_balance',
            'Entrance' => 'entrance_balance',
            'H Savings' => 'h_savings_balance',
            'Investment' => 'investment_balance',
            'Group Savings' => 'group_savings_balance',
            'Special Savings' => 'special_savings_balance',
            'Takaful' => 'takaful_balance',
            'Digital Gold' => 'gold_balance',
            'Dawah Fund' => 'dawah_fund_balance',
            'SITTING' => 'sitting_balance',
        ];

        // Include soft-deleted schemes in validation/maps to satisfy FK constraints
        $schemes = Scheme::withTrashed()->get(['id', 'name'])->keyBy('id');
        $schemeNameToId = Scheme::withTrashed()->pluck('id', 'name');
        $validSchemeIds = $schemes->keys()->map(fn($v) => (int)$v)->all();

        $totalUsers = User::query()->count();
        $processed = 0;

        User::query()->orderBy('id')->chunk($chunk, function ($users) use (&$processed, $totalUsers, $dryRun, $schemes, $schemeNameToId, $columnMap, $validSchemeIds) {
            $userIds = $users->pluck('id')->all();

            // Preload contributions sums per user per scheme to minimize queries
            $sums = DB::table('contributions')
                ->selectRaw('user_id, scheme_id, SUM(amount) as total')
                ->whereIn('user_id', $userIds)
                ->where('status', 'success')
                ->groupBy('user_id', 'scheme_id')
                ->get()
                ->groupBy('user_id');

            foreach ($users as $user) {
                $rows = [];
                $byScheme = $sums->get($user->id) ?? collect();

                // Primary source: contributions sums
                foreach ($byScheme as $row) {
                    $sid = (int) ($row->scheme_id ?? 0);
                    // Skip invalid/missing scheme ids (e.g., 0 or non-existent) to avoid FK violations
                    if ($sid <= 0 || !in_array($sid, $validSchemeIds, true)) {
                        continue;
                    }

                    $rows[] = [
                        'user_id' => $user->id,
                        'scheme_id' => $sid,
                        'balance' => (float) $row->total,
                        'meta' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Fallbacks: if for a given legacy column there is no per-scheme sum, attempt to backfill
                foreach ($columnMap as $name => $col) {
                    $schemeId = (int) ($schemeNameToId->get($name) ?? 0);
                    if ($schemeId <= 0 || !in_array($schemeId, $validSchemeIds, true)) continue;

                    $has = collect($rows)->firstWhere('scheme_id', (int) $schemeId);
                    if ($has) continue;

                    $legacyValue = (float) ($user->{$col} ?? 0);
                    if ($legacyValue <= 0) continue;

                    // Note: Builder::upsert bypasses model casting, so encode arrays to JSON manually for JSON column
                    $rows[] = [
                        'user_id' => $user->id,
                        'scheme_id' => (int) $schemeId,
                        'balance' => $legacyValue,
                        'meta' => json_encode(['source' => 'legacy_column', 'column' => $col]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($dryRun) {
                    $this->line("Would upsert ".count($rows)." balances for user {$user->id}");
                } else if (!empty($rows)) {
                    // Use upsert for idempotency
                    UserSchemeBalance::query()->upsert(
                        $rows,
                        uniqueBy: ['user_id', 'scheme_id'],
                        update: ['balance', 'meta', 'updated_at']
                    );
                }

                $processed++;
                if ($processed % 100 === 0) {
                    $this->info("Processed {$processed}/{$totalUsers} users...");
                }
            }
        });

        $this->info("Done. Processed {$processed} users.");
        return self::SUCCESS;
    }
}
