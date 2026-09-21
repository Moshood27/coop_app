@php /** @var \App\Filament\Pages\AgingReportPage $this */ @endphp

<x-filament::page>
    <div>
        {{ $this->form }}
        <div class="mt-4">
            <x-filament::button wire:click="generate">Generate</x-filament::button>
            @php($formData = $this->data)
            <a
                class="ms-2 text-primary-600 hover:underline"
                href="{{ url('/api/admin/accounting/reports/aging') }}?type={{ data_get($formData,'type','ar') }}&asOf={{ \Illuminate\Support\Carbon::parse(data_get($formData,'asOf', now()))->toDateString() }}&buckets={{ data_get($formData,'buckets','30,60,90') }}&accounts={{ data_get($formData,'accounts','') }}&branch_id={{ data_get($formData,'branch_id','') }}&format=csv"
                target="_blank"
            >Download CSV</a>
        </div>
    </div>

    @if($result && data_get($result,'ready'))
        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-2 py-1 text-left">Code</th>
                        <th class="px-2 py-1 text-left">Account</th>
                        @foreach(data_get($result,'buckets',[]) as $b)
                            <th class="px-2 py-1 text-right">{{ $b }}</th>
                        @endforeach
                        <th class="px-2 py-1 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(data_get($result,'rows',[]) as $row)
                        <tr>
                            <td class="px-2 py-1">{{ $row['code'] }}</td>
                            <td class="px-2 py-1">{{ $row['name'] }}</td>
                            @foreach(data_get($result,'buckets',[]) as $b)
                                <td class="px-2 py-1 text-right">{{ number_format((float)($row['buckets'][$b] ?? 0), 2) }}</td>
                            @endforeach
                            <td class="px-2 py-1 text-right font-medium">{{ number_format((float)($row['total'] ?? 0), 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="border-t">
                        <td class="px-2 py-1 font-semibold">TOTAL</td>
                        <td class="px-2 py-1"></td>
                        @foreach(data_get($result,'buckets',[]) as $b)
                            <td class="px-2 py-1 text-right font-semibold">{{ number_format((float) data_get($result, 'totals.'.$b, 0), 2) }}</td>
                        @endforeach
                        <td class="px-2 py-1 text-right font-bold">{{ number_format((float) data_get($result, 'totals.total', 0), 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @elseif($result && !data_get($result,'ready'))
        <div class="mt-4 text-warning-600">{{ data_get($result,'reason','Not ready') }}</div>
    @endif
</x-filament::page>
