@php($r = $this->report)
<x-filament::page>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row gap-3 sm:items-end">
            <div class="w-full sm:w-auto">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">As of</label>
                <input type="date" wire:model.live="as_of" class="fi-input fi-input-base w-full sm:w-56 text-sm" />
            </div>
            <div class="sm:ml-auto flex gap-2 pt-2 sm:pt-0">
                <x-filament::button wire:click="refreshReport">Refresh</x-filament::button>
                <x-filament::button color="gray" wire:click="exportCsv">Export CSV</x-filament::button>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            <div class="px-5 py-3 text-sm font-semibold text-gray-700 dark:text-gray-100 bg-gray-50 dark:bg-gray-800/60">Receivables Aging (as of {{ $r['as_of'] ?? '' }})</div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800/60">
                            <th class="px-5 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Bucket</th>
                            <th class="px-5 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-300">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach(($r['buckets'] ?? []) as $bucket => $amount)
                            <tr class="odd:bg-white even:bg-gray-50 dark:odd:bg-gray-900 dark:even:bg-gray-800">
                                <td class="px-5 py-3 text-gray-900 dark:text-gray-100">{{ $bucket }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-gray-900 dark:text-gray-100">₦ {{ number_format($amount ?? 0, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-800/60">
                        <tr>
                            <th class="px-5 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Open Invoices</th>
                            <th class="px-5 py-3 text-right font-mono tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($r['count'] ?? 0) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</x-filament::page>
