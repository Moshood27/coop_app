<div class="p-6">
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
        >
    </div>

    @if(count($users) > 0)
        <div class="mb-6 border rounded-lg divide-y bg-white">
            @foreach($users as $user)
                <div
                    wire:click="selectUser({{ $user->id }})"
                    class="p-4 cursor-pointer hover:bg-gray-50 flex items-center justify-between {{ $selectedUserId === $user->id ? 'bg-primary-50 border-l-4 border-primary-500' : '' }}"
                >
                    <div>
                        <div class="font-bold">{{ $user->full_name }}</div>
                        <div class="text-xs text-gray-500">ID: {{ $user->membership_number }} | {{ $user->branch?->name }}</div>
                    </div>
                    @if($user->webAuthnCredentials()->exists())
                        <span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full font-medium">Registered</span>
                    @else
                        <span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded-full font-medium">Not Enrolled</span>
                    @endif
                </div>
            @endforeach
        </div>
    @elseif($search && strlen($search) >= 2)
        <div class="mb-6 p-4 text-center text-gray-500 italic border rounded-lg">
            No members found.
        </div>
    @endif

    @if($this->selectedUser)
        <div
            class="mt-6 p-6 border rounded-xl bg-gray-50"
            x-data="biometricStationHandler"
            x-on:start-webauthn-registration.window="handleRegistration($event.detail.options)"
            x-on:start-webauthn-verification.window="handleVerification($event.detail.options)"
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
                    <p class="text-sm text-gray-600">Selected for biometric action</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <button
                    wire:click="startVerification"
                    wire:loading.attr="disabled"
                    class="flex flex-col items-center justify-center p-6 bg-white border-2 border-primary-500 rounded-xl hover:bg-primary-50 transition group"
                >
                    <x-heroicon-o-finger-print class="w-12 h-12 text-primary-600 mb-2 group-hover:scale-110 transition" />
                    <span class="font-bold text-primary-700">Verify & Mark Present</span>
                </button>

                <button
                    wire:click="startEnrollment"
                    wire:loading.attr="disabled"
                    class="flex flex-col items-center justify-center p-6 bg-white border-2 border-gray-300 rounded-xl hover:bg-gray-50 transition group"
                >
                    <x-heroicon-o-user-plus class="w-12 h-12 text-gray-600 mb-2 group-hover:scale-110 transition" />
                    <span class="font-bold text-gray-700">Enroll New Fingerprint</span>
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

                async handleRegistration(options) {
                    this.processing = true;
                    try {
                        const publicKey = this.parseOptions(options);
                        const credential = await navigator.credentials.create({ publicKey });
                        const credentialJSON = this.publicKeyCredentialToJSON(credential);

                        $wire.completeEnrollment(credentialJSON);
                    } catch (e) {
                        console.error('Registration failed', e);
                        this.processing = false;
                    }
                },

                async handleVerification(options) {
                    this.processing = true;
                    try {
                        const publicKey = this.parseOptions(options);
                        const assertion = await navigator.credentials.get({ publicKey });
                        const assertionJSON = this.publicKeyCredentialToJSON(assertion);

                        $wire.completeVerification(assertionJSON);
                    } catch (e) {
                        console.error('Verification failed', e);
                        this.processing = false;
                    }
                },

                bufferToBase64url(buffer) {
                    const byteView = new Uint8Array(buffer);
                    let str = '';
                    for (const charCode of byteView) {
                        str += String.fromCharCode(charCode);
                    }
                    return btoa(str)
                        .replace(/\+/g, '-')
                        .replace(/\//g, '_')
                        .replace(/=/g, '');
                },

                base64urlToBuffer(base64url) {
                    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
                    const padLen = (4 - (base64.length % 4)) % 4;
                    const str = atob(base64 + '='.repeat(padLen));
                    const buffer = new ArrayBuffer(str.length);
                    const byteView = new Uint8Array(buffer);
                    for (let i = 0; i < str.length; i++) {
                        byteView[i] = str.charCodeAt(i);
                    }
                    return buffer;
                },

                publicKeyCredentialToJSON(pubKeyCred) {
                    if (pubKeyCred instanceof Array) {
                        return pubKeyCred.map(p => this.publicKeyCredentialToJSON(p));
                    }

                    if (pubKeyCred instanceof ArrayBuffer) {
                        return this.bufferToBase64url(pubKeyCred);
                    }

                    if (pubKeyCred instanceof Object) {
                        const obj = {};
                        for (const key in pubKeyCred) {
                            if (typeof pubKeyCred[key] === 'function') continue;

                            if (pubKeyCred[key] instanceof ArrayBuffer) {
                                obj[key] = this.bufferToBase64url(pubKeyCred[key]);
                            } else if (typeof pubKeyCred[key] === 'object' && pubKeyCred[key] !== null) {
                                obj[key] = this.publicKeyCredentialToJSON(pubKeyCred[key]);
                            } else {
                                obj[key] = pubKeyCred[key];
                            }
                        }

                        if (pubKeyCred.getClientExtensionResults) {
                            obj.clientExtensionResults = pubKeyCred.getClientExtensionResults();
                        }

                        return obj;
                    }

                    return pubKeyCred;
                },

                parseOptions(options) {
                    const cloned = JSON.parse(JSON.stringify(options));

                    if (cloned.challenge) cloned.challenge = this.base64urlToBuffer(cloned.challenge);
                    if (cloned.user && cloned.user.id) cloned.user.id = this.base64urlToBuffer(cloned.user.id);

                    if (cloned.allowCredentials) {
                        cloned.allowCredentials = cloned.allowCredentials.map(cred => ({
                            ...cred,
                            id: this.base64urlToBuffer(cred.id)
                        }));
                    }

                    if (cloned.excludeCredentials) {
                        cloned.excludeCredentials = cloned.excludeCredentials.map(cred => ({
                            ...cred,
                            id: this.base64urlToBuffer(cred.id)
                        }));
                    }

                    return cloned;
                }
            }));
        }
    </script>
    @endscript
</div>
