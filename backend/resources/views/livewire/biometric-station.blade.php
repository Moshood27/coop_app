<div class="p-6" x-data="biometricStationHandler">
    <div class="mb-6">
        <h2 class="text-lg font-bold mb-2">Biometric Attendance Station</h2>
        <p class="text-sm text-gray-600">Search for a member to enroll or verify their fingerprint.</p>
    </div>

    <div class="mb-6">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search by name, surname or ID..."
            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 outline-none"
            wire:keydown.enter.prevent=""
            :disabled="processing"
        >
    </div>

    @if(count($users) > 0)
        <div class="mb-6 border rounded-lg divide-y bg-white">
            @foreach($users as $user)
                <div
                    wire:key="user-search-{{ $user->id }}"
                    wire:click="selectUser({{ $user->id }})"
                    class="p-4 cursor-pointer hover:bg-gray-50 flex items-center justify-between {{ $selectedUserId === $user->id ? 'bg-primary-50 border-l-4 border-primary-500' : '' }}"
                >
                    <div>
                        <div class="font-bold">{{ $user->full_name }}</div>
                        <div class="text-xs text-gray-500">ID: {{ $user->membership_number }} | {{ $user->branch?->name }}</div>
                    </div>
                    @if($user->biometric_template)
                        <span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full font-medium">USB Template Saved</span>
                    @else
                        <span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded-full font-medium">No USB Biometric</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if(count($users) === 0 && $search && strlen($search) >= 2)
        <div class="mb-6 p-4 text-center text-gray-500 italic border rounded-lg">
            No members found.
        </div>
    @endif

    @if($this->selectedUser)
        <div
            wire:key="biometric-actions-{{ $selectedUserId }}"
            class="mt-6 p-6 border rounded-xl bg-gray-50"
            x-on:enrollment-completed.window="processing = false"
            x-on:attendance-marked.window="processing = false"
            x-on:error-occurred.window="processing = false"
        >
            <div class="flex items-center space-x-4 mb-6">
                <div class="h-16 w-16 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 text-2xl font-bold">
                    {{ strtoupper(substr($this->selectedUser->name, 0, 1)) }}
                </div>
                <div>
                    <h3 class="text-xl font-bold">{{ $this->selectedUser->full_name }}</h3>
                    <p class="text-sm text-gray-600">Selected for USB Biometric action</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <button
                    type="button"
                    x-on:click="captureUsbTemplate('verify')"
                    wire:loading.attr="disabled"
                    :disabled="processing"
                    class="flex flex-col items-center justify-center p-6 bg-white border-2 border-primary-500 rounded-xl hover:bg-primary-50 transition group"
                >
                    <x-heroicon-o-finger-print class="w-12 h-12 text-primary-600 mb-2 group-hover:scale-110 transition" />
                    <span class="font-bold text-primary-700">USB: Verify & Mark</span>
                </button>

                <button
                    type="button"
                    x-on:click="captureUsbTemplate('enroll')"
                    wire:loading.attr="disabled"
                    :disabled="processing"
                    class="flex flex-col items-center justify-center p-6 bg-white border-2 border-gray-300 rounded-xl hover:bg-gray-50 transition group"
                >
                    <x-heroicon-o-user-plus class="w-12 h-12 text-gray-600 mb-2 group-hover:scale-110 transition" />
                    <span class="font-bold text-gray-700">USB: Enroll Fingerprint</span>
                </button>
            </div>

            <div x-show="processing" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[100]" x-cloak>
                <div class="bg-white p-8 rounded-2xl shadow-xl flex flex-col items-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mb-4"></div>
                    <p class="font-medium text-gray-900">Waiting for biometric scanner...</p>
                    <p class="text-sm text-gray-500 mt-2">Please have the member touch the sensor.</p>
                </div>
            </div>
        </div>
    @endif

    @script
    <script>
        if (!Alpine.data('biometricStationHandler')) {
            Alpine.data('biometricStationHandler', () => ({
                processing: false,

                async captureUsbTemplate(action) {
                    this.processing = true;
                    try {
                        // This integration point communicates with a local desktop service
                        // or browser extension that interfaces with the USB Biometric Scanner
                        // (DigitalPersona, ZKTeco, etc.)

                        // Example: Calling a local service on the admin PC
                        // const response = await fetch('http://localhost:8080/biometric/scan');
                        // if (!response.ok) throw new Error('Scanner service offline');
                        // const data = await response.json();
                        // const template = data.template;

                        // FOR DEMONSTRATION: Simulating scanner capture
                        await new Promise(resolve => setTimeout(resolve, 1500));
                        const template = "USB_FINGERPRINT_TEMPLATE_" + Math.random().toString(36).substring(2, 15);

                        if (action === 'enroll') {
                            $wire.saveUsbTemplate(template);
                        } else {
                            // In verification, we send the captured template to match against stored one
                            $wire.verifyUsbTemplate(template);
                        }
                    } catch (e) {
                        console.error('USB Capture failed', e);
                        this.processing = false;
                        alert('Could not communicate with the USB scanner. Please ensure the local biometric service is running.');
                    }
                }
            }));
        }
    </script>
    @endscript
</div>
