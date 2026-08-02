<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>View Passbook - {{ $user->full_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans text-gray-900 antialiased p-4 md:p-8">
    <div class="max-w-6xl mx-auto bg-white shadow-lg rounded-lg overflow-hidden">
        <div class="p-6 md:p-8">
            <div class="flex flex-col md:flex-row justify-between items-start border-b-2 border-gray-900 pb-6 mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold uppercase tracking-tight">Passbook Statement</h1>
                    <p class="text-gray-500 mt-1">Year: <span class="font-semibold text-gray-900">{{ $year }}</span></p>
                </div>
                <div class="mt-4 md:mt-0 md:text-right">
                    <h2 class="text-xl font-bold">{{ $user->full_name }}</h2>
                    <p class="text-gray-500">Membership ID: <span class="font-semibold text-gray-900">{{ $user->membership_number }}</span></p>
                    @if(!empty($branch))
                        <p class="text-gray-500">Branch: <span class="font-semibold text-gray-900">{{ $branch }}</span></p>
                    @endif
                </div>
            </div>

            <div class="mb-10">
                <h3 class="text-lg font-bold border-b border-gray-200 pb-2 mb-4">Yearly Summary Matrix</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left border-collapse border border-gray-200">
                        <thead>
                            <tr class="bg-gray-900 text-white uppercase">
                                <th class="p-2 border border-gray-700">Scheme</th>
                                <th class="p-2 border border-gray-700 text-center">BF</th>
                                @foreach(['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $m)
                                    <th class="p-2 border border-gray-700 text-center">{{ $m }}</th>
                                @endforeach
                                <th class="p-2 border border-gray-700 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($matrix as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="p-2 border border-gray-200 font-bold">{{ $row['scheme_name'] }}</td>
                                    <td class="p-2 border border-gray-200 text-center">{{ $row['bf'] > 0 ? number_format($row['bf'], 0) : '-' }}</td>
                                    @foreach($row['months'] as $val)
                                        <td class="p-2 border border-gray-200 text-center">{{ $val > 0 ? number_format($val, 0) : '-' }}</td>
                                    @endforeach
                                    <td class="p-2 border border-gray-200 text-right font-bold">{{ number_format($row['total'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-100 font-bold">
                            <tr>
                                <td class="p-2 border border-gray-200">GRAND TOTAL</td>
                                <td class="p-2 border border-gray-200 text-center">{{ number_format($bf_total, 2) }}</td>
                                <td colspan="12" class="p-2 border border-gray-200"></td>
                                <td class="p-2 border border-gray-200 text-right">{{ number_format($grand_total, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-bold border-b border-gray-200 pb-2 mb-4">Transaction Details</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left border-collapse border border-gray-200">
                        <thead>
                            <tr class="bg-gray-900 text-white uppercase text-xs">
                                <th class="p-3 border border-gray-700 w-1/5">Date</th>
                                <th class="p-3 border border-gray-700">Scheme</th>
                                <th class="p-3 border border-gray-700 w-1/4">Reference</th>
                                <th class="p-3 border border-gray-700 text-right w-32">Units</th>
                                <th class="p-3 border border-gray-700 text-right w-40">Amount (₦)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $total = 0; @endphp
                            @forelse($contributions as $c)
                                @php $amt = (float) $c->amount; $total += $amt; @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="p-3 border border-gray-200 text-xs">{{ optional($c->paid_at ?? $c->created_at)->format('Y-m-d H:i') }}</td>
                                    <td class="p-3 border border-gray-200">{{ optional($c->scheme)->name ?? '—' }}</td>
                                    <td class="p-3 border border-gray-200 text-xs font-mono">{{ $c->reference }}</td>
                                    <td class="p-3 border border-gray-200 text-right">{{ $c->units ? number_format((float)$c->units, 4).(str_contains(strtolower(optional($c->scheme)->name), 'gold') ? 'g' : '') : '—' }}</td>
                                    <td class="p-3 border border-gray-200 text-right font-semibold">{{ number_format($amt, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-gray-500 italic">No contributions found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="bg-gray-100 font-bold">
                            <tr>
                                <td colspan="4" class="p-3 border border-gray-200 text-right uppercase text-xs tracking-wider">Total</td>
                                <td class="p-3 border border-gray-200 text-right">{{ number_format((float) $total, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="mt-8 flex justify-between items-center text-gray-500 text-xs italic">
                <p>Generated on {{ now()->format('Y-m-d H:i') }}</p>
                <div class="no-print space-x-4">
                    <button onclick="window.print()" class="bg-gray-800 text-white px-4 py-2 rounded shadow hover:bg-gray-700 transition">Print PDF</button>
                    <button onclick="window.close()" class="bg-gray-200 text-gray-800 px-4 py-2 rounded shadow hover:bg-gray-300 transition">Close Window</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
