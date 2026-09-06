@php($report = $this->report)
<x-filament::page>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 no-print">
            <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-2/3">
                @if(auth()->user()->hasRole('super_admin'))
                    <div class="w-full sm:w-1/2">
                        <select wire:model.live="branchId" class="fi-select-input block w-full rounded-lg border-none bg-white py-1.5 text-base text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:focus:ring-primary-500 sm:text-sm sm:leading-6">
                            <option value="">All Branches</option>
                            @foreach(\App\Models\Branch::all() as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="w-full sm:w-1/4">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="date"
                            wire:model.live="from"
                            placeholder="From Date"
                        />
                    </x-filament::input.wrapper>
                </div>
                <div class="w-full sm:w-1/4">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="date"
                            wire:model.live="to"
                            placeholder="To Date"
                        />
                    </x-filament::input.wrapper>
                </div>
            </div>
            <div class="flex gap-2 ml-auto">
                <x-filament::button wire:click="refreshReport" color="gray" icon="heroicon-m-arrow-path">Refresh</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-m-printer" onclick="window.print()">Print</x-filament::button>
                <x-filament::button color="primary" icon="heroicon-m-arrow-down-tray" wire:click="exportCsv">Export CSV</x-filament::button>
            </div>
        </div>

        @forelse($report['branches'] as $branch)
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm overflow-hidden break-inside-avoid mb-8">
                <div class="bg-gray-50 dark:bg-gray-800/60 px-5 py-3 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $branch['branch_name'] }}</h3>
                    <div class="flex gap-4 text-sm font-medium">
                        <div class="text-gray-500 dark:text-gray-400">
                            Collected: <span class="text-success-600 dark:text-success-400 font-bold">₦{{ number_format($branch['total_collected'], 2) }}</span>
                        </div>
                        <div class="text-gray-500 dark:text-gray-400">
                            Outstanding: <span class="text-danger-600 dark:text-danger-400 font-bold">₦{{ number_format($branch['total_outstanding'], 2) }}</span>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800 text-sm">
                        <thead>
                            <tr class="bg-gray-50/50 dark:bg-gray-800/40">
                                <th class="px-5 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Member</th>
                                <th class="px-5 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Membership #</th>
                                <th class="px-5 py-3 text-left font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Type</th>
                                <th class="px-5 py-3 text-right font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Collected</th>
                                <th class="px-5 py-3 text-right font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Outstanding</th>
                                <th class="px-5 py-3 text-right font-semibold text-gray-700 dark:text-gray-200 uppercase tracking-wider">Last Charged</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($branch['members'] as $member)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="px-5 py-3 text-gray-900 dark:text-gray-100 font-medium">{{ $member['member_name'] }}</td>
                                    <td class="px-5 py-3 text-gray-600 dark:text-gray-400 font-mono text-xs">{{ $member['membership_number'] }}</td>
                                    <td class="px-5 py-3">
                                        <x-filament::badge color="{{ $member['is_distant'] ? 'info' : 'success' }}">
                                            {{ $member['is_distant'] ? 'Meeting Fee (Distant)' : 'Sitting Fee (Regular)' }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-5 py-3 text-right tabular-nums text-success-600 dark:text-success-400 font-medium">₦{{ number_format($member['collected'], 2) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums text-danger-600 dark:text-danger-400 font-medium">₦{{ number_format($member['outstanding'], 2) }}</td>
                                    <td class="px-5 py-3 text-right text-gray-500 dark:text-gray-400 text-xs">{{ $member['last_charge_date'] ?? 'Never' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50/50 dark:bg-gray-800/40 font-bold border-t border-gray-200 dark:border-gray-800">
                            <tr>
                                <td colspan="3" class="px-5 py-4 text-gray-900 dark:text-gray-100 uppercase tracking-tight">Branch Totals</td>
                                <td class="px-5 py-4 text-right tabular-nums text-success-600 dark:text-success-400">₦{{ number_format($branch['total_collected'], 2) }}</td>
                                <td class="px-5 py-4 text-right tabular-nums text-danger-600 dark:text-danger-400">₦{{ number_format($branch['total_outstanding'], 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center p-12 bg-white dark:bg-gray-900 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 no-print">
                <p class="text-gray-500 dark:text-gray-400 font-medium">No administrative charge records found for the selected criteria.</p>
            </div>
        @endforelse

        @if(count($report['branches']) > 1)
            <div class="rounded-xl border-2 border-primary-500 dark:border-primary-400 bg-primary-50/50 dark:bg-primary-900/20 p-6 break-inside-avoid">
                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">Organization Grand Totals</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Total Members Processed</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $report['grand_total_members_count'] }}</div>
                    </div>
                    <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-success-200 dark:border-success-800">
                        <div class="text-xs text-success-600 dark:text-success-400 uppercase font-semibold">Total Collected (Revenue)</div>
                        <div class="text-2xl font-bold text-success-700 dark:text-success-300">₦{{ number_format($report['grand_total_collected'], 2) }}</div>
                    </div>
                    <div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-danger-200 dark:border-danger-800">
                        <div class="text-xs text-danger-600 dark:text-danger-400 uppercase font-semibold">Total Outstanding (Receivables)</div>
                        <div class="text-2xl font-bold text-danger-700 dark:text-danger-300">₦{{ number_format($report['grand_total_outstanding'], 2) }}</div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <style>
        @media print {
            .no-print, .fi-sidebar, .fi-topbar, .fi-footer {
                display: none !important;
            }
            .fi-main {
                padding: 0 !important;
                margin: 0 !important;
            }
            body {
                background: white !important;
            }
            .rounded-xl {
                border-radius: 0 !important;
            }
            .shadow-sm {
                box-shadow: none !important;
            }
            .break-inside-avoid {
                break-inside: avoid;
            }
        }
    </style>
</x-filament::page>
