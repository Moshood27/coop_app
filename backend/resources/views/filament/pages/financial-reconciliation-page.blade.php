<x-filament::page>
    <div class="space-y-6">
        <form wire:submit.prevent="runReconciliation">
            {{ $this->form }}

            <div class="mt-4">
                <x-filament::button type="submit" wire:target="runReconciliation" wire:loading.attr="disabled">
                    <span wire:loading wire:target="runReconciliation" class="mr-2">
                        <x-filament::loading-indicator class="h-4 w-4" />
                    </span>
                    {{ $data['fix'] ? 'Run Reconciliation & Fix' : 'Run Reconciliation Audit' }}
                </x-filament::button>
            </div>
        </form>

        @if($lastResult)
            <x-filament::section>
                <x-slot name="heading">
                    Reconciliation Results
                </x-slot>

                <div class="space-y-6">
                    @foreach($lastResult as $category => $report)
                        @if($category === 'timestamp')
                            <div class="text-xs text-gray-500 border-t pt-4">
                                Run completed at: {{ $report }}
                            </div>
                        @else
                            <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <h4 class="text-md font-bold capitalize text-gray-700 mb-3 border-b pb-2">{{ str_replace('_', ' ', $category) }} Report</h4>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    @foreach($report as $key => $value)
                                        @if(!is_array($value))
                                            <div class="bg-white p-3 rounded border border-gray-100 shadow-sm">
                                                <div class="text-xs text-gray-500 uppercase font-semibold">{{ str_replace('_', ' ', $key) }}</div>
                                                <div class="text-lg font-bold text-primary-600">{{ $value }}</div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>

                                @if(!empty($report['errors']))
                                    <div class="mt-4">
                                        <div class="text-xs font-bold text-red-600 uppercase mb-2">Errors & Warnings</div>
                                        <ul class="space-y-1">
                                            @foreach($report['errors'] as $error)
                                                <li class="text-xs p-2 bg-red-50 text-red-700 rounded border border-red-100">
                                                    {{ $error }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament::page>
