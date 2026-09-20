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

                <div class="space-y-4">
                    @foreach($lastResult as $category => $items)
                        <div>
                            <h4 class="text-sm font-bold capitalize">{{ str_replace('_', ' ', $category) }}</h4>
                            @if(empty($items))
                                <p class="text-xs text-gray-500">No issues found.</p>
                            @else
                                <ul class="mt-2 space-y-1">
                                    @foreach($items as $item)
                                        <li class="text-xs p-2 bg-gray-50 rounded border border-gray-100">
                                            {{ is_string($item) ? $item : json_encode($item) }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament::page>
