@php /** @var \App\Filament\Pages\FxOperationsPage $this */ @endphp

<x-filament::page>
    <div>
        {{ $this->form }}
        <div class="mt-4">
            <x-filament::button wire:click="postRealized">Post Realized FX</x-filament::button>
        </div>
    </div>

    @if($lastResponse)
        <div class="mt-4 text-sm">
            <strong>Last response:</strong>
            <pre class="p-2 bg-gray-50 rounded">{{ json_encode($lastResponse, JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif
</x-filament::page>
