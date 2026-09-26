<?php

namespace App\Filament\Resources\UserResource\Traits;

use App\Models\Branch;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

trait HasTableFilters
{
    public static function getTableFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('branch')
                ->relationship('branch', 'name')
                ->searchable()
                ->preload(),
            Tables\Filters\SelectFilter::make('approval_status')
                ->options([
                    'pending' => 'Pending',
                    'recommended' => 'Recommended',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
            Tables\Filters\Filter::make('is_admin')
                ->label('Administrators Only')
                ->query(fn (Builder $query) => $query->where('is_admin', true)),
            Tables\Filters\Filter::make('is_defaulter')
                ->label('Defaulters Only')
                ->query(fn (Builder $query) => $query->where('is_defaulter', true)),
            Tables\Filters\TernaryFilter::make('deceased')
                ->label('Member Status')
                ->placeholder('All Members')
                ->trueLabel('Deceased Only')
                ->falseLabel('Active Only')
                ->queries(
                    true: fn (Builder $query) => $query->whereNotNull('deceased_at'),
                    false: fn (Builder $query) => $query->whereNull('deceased_at'),
                ),
            Tables\Filters\TernaryFilter::make('zakat_due')
                ->label('Zakat Status')
                ->placeholder('All Users')
                ->trueLabel('Zakat Due')
                ->falseLabel('Not Due')
                ->queries(
                    true: function (Builder $query) {
                        $lunarDays = (int) config('zakat.lunar_days', 354);
                        return $query->whereNotNull('zakat_nisab_crossed_at')
                            ->whereRaw('DATEDIFF(NOW(), zakat_nisab_crossed_at) >= ?', [$lunarDays]);
                    },
                    false: function (Builder $query) {
                        $lunarDays = (int) config('zakat.lunar_days', 354);
                        return $query->where(function ($q) use ($lunarDays) {
                            $q->whereNull('zakat_nisab_crossed_at')
                                ->orWhereRaw('DATEDIFF(NOW(), zakat_nisab_crossed_at) < ?', [$lunarDays]);
                        });
                    },
                ),
            Tables\Filters\Filter::make('needs_wellness_check')
                ->label('Needs Wellness Check')
                ->query(function (Builder $query) {
                    $months = \App\Models\Setting::get('wellness_check_inactivity_months', config('cooperative.legacy.inactivity_months', 6));
                    $threshold = now()->subMonths($months);
                    return $query->whereNull('deceased_at')
                        ->where(function($q) use ($threshold) {
                            $q->where('last_activity_at', '<', $threshold)
                              ->orWhereNull('last_activity_at');
                        })
                        ->where(function($q) {
                            $q->whereNull('wellness_check_notified_at')
                              ->orWhereColumn('wellness_check_notified_at', '<', 'last_activity_at');
                        });
                }),
            Tables\Filters\Filter::make('has_admin_charge_balance')
                ->label('Has Outstanding Admin Charge')
                ->query(fn (Builder $query) => $query->where('admin_charge_balance', '>', 0)),
            Tables\Filters\TrashedFilter::make(),
        ];
    }
}
