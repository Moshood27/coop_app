<div class="p-6" x-data="biometricStationHandler">
    <div class="mb-6">
        <h2 class="text-lg font-bold mb-2">Biometric Attendance Station</h2>
        <p class="text-sm text-gray-600">Search for a member to enroll or verify their fingerprint.</p>
    </div>

    <div class="mb-6 flex space-x-4">
        <button
            wire:click="toggleAutoEnroll"
            class="px-4 py-2 rounded-lg font-medium transition {{ $autoEnrollMode ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
        >
            {{ $autoEnrollMode ? 'Stop Auto-Enroll' : 'Start Auto-Enroll (Bulk)' }}
        </button>
        <button
            wire:click="toggleAutoMark"
            class="px-4 py-2 rounded-lg font-medium transition {{ $autoMarkMode ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
        >
            {{ $autoMarkMode ? 'Stop Auto-Mark' : 'Start Auto-Mark (Continuous)' }}
        </button>
    </div>

    <div class="mb-6" x-show="!autoMarkMode">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search by name, surname or ID..."
            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 outline-none"
            wire:keydown.enter.prevent=""
            :disabled="processing"
        >
    </div>

    @if($autoMarkMode)
        <div class="mb-6 p-12 border-4 border-dashed border-green-200 rounded-3xl bg-green-50 text-center flex flex-col items-center">
            <div class="h-24 w-24 bg-green-100 rounded-full flex items-center justify-center mb-6">
                <x-heroicon-o-finger-print class="w-16 h-16 text-green-600 {{ $autoMarkMode ? 'animate-pulse' : '' }}" />
            </div>
            <h3 class="text-2xl font-bold text-green-800 mb-2">Continuous Attendance Mode Active</h3>
            <p class="text-green-600 mb-6">The system is waiting for any member to touch the scanner.</p>
            <button
                type="button"
                x-on:click="captureUsbTemplate('identify')"
                :disabled="processing"
                class="px-8 py-3 bg-green-600 text-white rounded-xl font-bold hover:bg-green-700 transition shadow-lg"
            >
                Start Scanner for Next Member
            </button>
        </div>
    @endif

    @if(count($users) > 0 && !$autoMarkMode)
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

            @if(config('cooperative.biometric.enabled', true))
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
            @else
                <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-700 text-sm">
                    USB Biometric integration is currently disabled in system settings.
                </div>
            @endif

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
                    if (!{{ config('cooperative.biometric.enabled', true) ? 'true' : 'false' }}) {
                        alert('Biometric scanner integration is currently disabled in system settings.');
                        return;
                    }

                    this.processing = true;
                    try {
                        const scannerUrl = "{{ config('cooperative.biometric.scanner_url', 'http://localhost:8080/biometric/scan') }}";

                        // Handle Mixed Content Warnings
                        if (window.location.protocol === 'https:' && scannerUrl.startsWith('http://') && !scannerUrl.includes('localhost') && !scannerUrl.includes('127.0.0.1')) {
                            console.warn('Potential Mixed Content issue: Accessing HTTP scanner from HTTPS site.');
                        }

                        // This integration point communicates with a local desktop service
                        // or browser extension that interfaces with the USB Biometric Scanner
                        // (DigitalPersona, ZKTeco, etc.)

                        // In a real implementation, we fetch from the local scanner service
                        // const response = await fetch(scannerUrl);
                        // if (!response.ok) throw new Error('Scanner service offline');
                        // const data = await response.json();
                        // const template = data.template;

                        // FOR DEMONSTRATION/PLACEHOLDER: Simulating scanner capture
                        // In actual deployment, the fetch above would be uncommented
                        console.log('Fetching biometric from: ' + scannerUrl);
                        await new Promise(resolve => setTimeout(resolve, 1500));
                        const template = "USB_FINGERPRINT_TEMPLATE_" + Math.random().toString(36).substring(2, 15);

                        if (action === 'enroll') {
                            $wire.saveUsbTemplate(template);
                        } else if (action === 'identify') {
                            $wire.identifyUsbTemplate(template);
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
