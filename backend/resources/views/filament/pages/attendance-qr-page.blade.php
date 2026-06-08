<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <div class="flex items-end gap-4">
                <div class="flex-1">
                    <label for="meetingId" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Select Meeting
                    </label>
                    <x-filament::input.select wire:model.live="meetingId" id="meetingId">
                        <option value="">Select a meeting...</option>
                        @foreach($this->getMeetings() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </div>
            </div>
        </x-filament::section>

        @if($meetingId && $this->getSelectedMeeting())
            <x-filament::section>
                @livewire('attendance-qr', ['meeting' => $this->getSelectedMeeting()], key($meetingId))
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="py-12 flex flex-col items-center justify-center text-center">
                    <x-heroicon-o-calendar-days class="w-12 h-12 text-gray-400 mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">No Meeting Selected</h3>
                    <p class="text-gray-500 max-w-sm mx-auto">
                        Please select an ongoing or scheduled meeting from the dropdown above to display the attendance QR code.
                    </p>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
