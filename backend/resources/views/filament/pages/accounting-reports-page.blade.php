@php /** @var \App\Filament\Pages\AccountingReportsPage $this */ @endphp

<x-filament::page>
    <div>
        {{ $this->form }}
        @php($state = $this->form->getState())
        @php($from = \Illuminate\Support\Carbon::parse(($state['from'] ?? now()))->toDateString())
        @php($to = \Illuminate\Support\Carbon::parse(($state['to'] ?? now()))->toDateString())
        @php($branch = $state['branch_id'] ?? '')
        @php($compareFrom = $state['compare_from'] ?? '')
        @php($compareTo = $state['compare_to'] ?? '')

        <div class="mt-4 flex flex-wrap gap-3">
            <a class="px-3 py-2 bg-primary-600 text-white rounded" target="_blank"
               href="{{ url('/api/admin/accounting/reports/trial-balance.csv') }}?from={{ $from }}&to={{ $to }}&branch_id={{ $branch }}">Trial Balance CSV</a>
            <a class="px-3 py-2 bg-primary-600 text-white rounded" target="_blank"
               href="{{ url('/api/admin/accounting/reports/income-expenditure.csv') }}?from={{ $from }}&to={{ $to }}&branch_id={{ $branch }}">Income & Expenditure CSV</a>
            <a class="px-3 py-2 bg-primary-600 text-white rounded" target="_blank"
               href="{{ url('/api/admin/accounting/reports/balance-sheet.csv') }}?from={{ $from }}&to={{ $to }}&branch_id={{ $branch }}">Balance Sheet CSV</a>
            <a class="px-3 py-2 bg-primary-600 text-white rounded" target="_blank"
               href="{{ url('/api/admin/accounting/reports/cash-flows.csv') }}?from={{ $from }}&to={{ $to }}&branch_id={{ $branch }}">Cash Flows CSV</a>
            <a class="px-3 py-2 bg-primary-600 text-white rounded" target="_blank"
               href="{{ url('/api/admin/accounting/reports/aging') }}?type=ar&asOf={{ $to }}&format=csv">AR Aging CSV</a>
            <a class="px-3 py-2 bg-primary-600 text-white rounded" target="_blank"
               href="{{ url('/api/admin/accounting/reports/aging') }}?type=ap&asOf={{ $to }}&format=csv">AP Aging CSV</a>
            <a class="px-3 py-2 bg-primary-600 text-white rounded" target="_blank"
               href="{{ url('/api/admin/accounting/reports/comparative') }}?from={{ $from }}&to={{ $to }}&compare_from={{ $compareFrom }}&compare_to={{ $compareTo }}&format=csv">Comparative CSV</a>
        </div>
    </div>
</x-filament::page>
