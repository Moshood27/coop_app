@php /** @var \App\Filament\Pages\InventoryStockPage $this */ @endphp

<x-filament::page>
    <div>
        {{ $this->form }}
        <div class="mt-4">
            <x-filament::button wire:click="load">Load</x-filament::button>
        </div>
    </div>

    @if($rows)
        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-2 py-1 text-left">Product</th>
                        <th class="px-2 py-1 text-right">Stock on Hand</th>
                        <th class="px-2 py-1 text-right">Avg Cost</th>
                        <th class="px-2 py-1 text-right">Inventory Value</th>
                    </tr>
                </thead>
                <tbody>
                    @php($total = 0)
                    @foreach($rows as $r)
                        @php($total += $r['inventory_value'])
                        <tr>
                            <td class="px-2 py-1">{{ $r['name'] }}</td>
                            <td class="px-2 py-1 text-right">{{ number_format((float)$r['stock_on_hand'], 2) }}</td>
                            <td class="px-2 py-1 text-right">{{ number_format((float)$r['avg_cost'], 2) }}</td>
                            <td class="px-2 py-1 text-right font-medium">{{ number_format((float)$r['inventory_value'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="border-t">
                        <td class="px-2 py-1 font-semibold">TOTAL</td>
                        <td class="px-2 py-1"></td>
                        <td class="px-2 py-1"></td>
                        <td class="px-2 py-1 text-right font-bold">{{ number_format((float)$total, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</x-filament::page>
