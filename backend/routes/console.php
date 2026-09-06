<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Setting;
use Laravel\Pennant\Feature;
use App\Jobs\ReconcileUtilityTransactions;
use App\Console\Commands\CollectAdministrativeCharges;
use App\Console\Commands\ProcessAdministrativeCharges;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-wellness-check')->daily();
Schedule::command('app:apply-monthly-fines')
    ->monthlyOn(5, '00:00')
    ->when(fn() => Setting::get('monthly_fees_enabled', true));

// Loan & Reminder Tasks
Schedule::command('loans:send-default-reminders')
    ->dailyAt('08:00')
    ->when(fn() => Setting::get('auto_overdue_recovery_enabled', true));
Schedule::command('loans:send-monthly-reminders')
    ->dailyAt('09:00')
    ->timezone('Africa/Lagos')
    ->when(fn() => Setting::get('auto_overdue_recovery_enabled', true));
Schedule::command('loans:remind-guarantors')
    ->twiceDaily(9, 16)
    ->when(fn() => Setting::get('auto_overdue_recovery_enabled', true));
Schedule::command('loans:hunter-sweep')
    ->hourly()
    ->when(fn() => Setting::get('auto_overdue_recovery_enabled', true));
Schedule::command('murabaha:sweep')
    ->dailyAt('03:00')
    ->timezone('Africa/Lagos')
    ->when(fn() => Feature::for('global')->active('store-enabled'));

// AGM Tasks
Schedule::command('agm:notify-voting-open')
    ->everyMinute()
    ->when(fn() => Feature::for('global')->active('agm-voting-enabled'));
Schedule::command('agm:close-expired-sessions')
    ->everyMinute()
    ->when(fn() => Feature::for('global')->active('agm-voting-enabled'));

// Financial & Savings Tasks
Schedule::command('autosave:charge')->dailyAt('08:00')->timezone('Africa/Lagos');
Schedule::command('takaful:charge')
    ->monthlyOn(1, '08:10')
    ->timezone('Africa/Lagos')
    ->when(fn() => Feature::for('global')->active('takaful-enabled'));
Schedule::command('savings-groups:charge')
    ->monthlyOn(1, '08:20')
    ->timezone('Africa/Lagos')
    ->when(fn() => Feature::for('global')->active('group-savings-enabled'));
Schedule::command('admin-charges:collect')
    ->monthlyOn(1, '08:30')
    ->timezone('Africa/Lagos')
    ->when(fn() => Setting::get('monthly_fees_enabled', true));
Schedule::command('zakat:check-nisab-hawl')
    ->daily()
    ->timezone('Africa/Lagos')
    ->when(fn() => Feature::for('global')->active('zakat-enabled'));

// Utility & Reconciliation Tasks
Schedule::command('vtu:check-balances')
    ->hourly()
    ->timezone('Africa/Lagos')
    ->when(fn() => Feature::for('global')->active('airtime-data-enabled'));
Schedule::job(new ReconcileUtilityTransactions)->everyFiveMinutes();
Schedule::command('reconcile:contributions')->everyThirtyMinutes();

// Meeting & Attendance Tasks
Schedule::command('app:update-meeting-statuses')->everyMinute();
Schedule::command('app:send-meeting-reminders')->everyMinute();
Schedule::command('app:audit-attendance')->hourly();

// Maintenance Tasks
Schedule::command('telescope:prune --hours=24')->daily();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('horizon:forget --all')->daily();
Schedule::command('queue:prune-failed --hours=24')->daily();
Schedule::command('queue:prune-batches --hours=24')->daily();
Schedule::command('chat:expire-sensitive-files')->daily();
Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('02:00');
Schedule::command('health:check')->everyFifteenMinutes();
Schedule::command(\Spatie\Health\Commands\ScheduleCheckHeartbeatCommand::class)->everyMinute();
