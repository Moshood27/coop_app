@props([
    'heading' => null,
    'subheading' => null,
])

<x-filament-panels::layout.base :livewire="$this">
    <div class="flex min-h-screen">
        <!-- Left side: Branding/Marketing (Hidden on mobile) -->
        <div class="relative hidden w-0 flex-1 lg:block bg-gray-900 overflow-hidden">
            <!-- Background Image/Pattern -->
            <div class="absolute inset-0 h-full w-full object-cover opacity-60 bg-gradient-to-br from-primary-900 via-slate-900 to-black"></div>

            <!-- Fintech-style pattern -->
            <svg class="absolute inset-0 h-full w-full opacity-20" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
                <defs>
                    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)" />
            </svg>

            <div class="relative z-10 flex flex-col justify-center h-full px-20 text-white">
                <div class="mb-12">
                     <img src="{{ asset('images/'.config('brand.slug', 'attaqwa').'-logo-dark.svg') }}" alt="Logo" class="h-16 w-auto">
                </div>

                <h2 class="text-4xl font-extrabold tracking-tight sm:text-5xl lg:text-6xl mb-6">
                    The Modern <span class="text-primary-400">Cooperative</span> Platform.
                </h2>

                <p class="mt-6 text-xl text-slate-300 max-w-lg leading-relaxed">
                    Secure, Shariah-compliant financial ecosystem designed for the next generation of cooperatives. Manage everything in one place.
                </p>

                <div class="mt-16 grid grid-cols-2 gap-x-12 gap-y-10">
                    <div class="flex flex-col">
                        <span class="text-3xl font-bold text-white tracking-tight">100%</span>
                        <span class="mt-1 text-sm text-slate-400 uppercase tracking-widest font-semibold">Shariah Compliant</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-3xl font-bold text-white tracking-tight">Secure</span>
                        <span class="mt-1 text-sm text-slate-400 uppercase tracking-widest font-semibold">End-to-End Encryption</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-3xl font-bold text-white tracking-tight">Real-time</span>
                        <span class="mt-1 text-sm text-slate-400 uppercase tracking-widest font-semibold">Financial Insights</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-3xl font-bold text-white tracking-tight">Scalable</span>
                        <span class="mt-1 text-sm text-slate-400 uppercase tracking-widest font-semibold">Cloud Infrastructure</span>
                    </div>
                </div>
            </div>

            <!-- Bottom decorative element -->
            <div class="absolute bottom-0 left-0 w-full h-32 bg-gradient-to-t from-black/50 to-transparent"></div>
        </div>

        <!-- Right side: Auth Form -->
        <div class="flex flex-1 flex-col justify-center px-6 py-12 sm:px-12 lg:flex-none lg:px-20 xl:px-24 bg-slate-50 dark:bg-gray-950">
            <div class="mx-auto w-full max-w-sm lg:w-96">
                <div class="bg-white dark:bg-gray-900 px-8 py-10 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.1)] ring-1 ring-gray-900/5 dark:ring-white/10 transition-all duration-300">
                    <div class="mb-10 text-center lg:text-left">
                        <div class="lg:hidden mb-8 flex justify-center">
                            <img src="{{ asset('images/'.config('brand.slug', 'attaqwa').'-logo.svg') }}" alt="Logo" class="h-12 w-auto">
                        </div>

                        <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                            {{ $heading }}
                        </h2>
                        @if ($subheading)
                            <div class="mt-4 text-sm text-slate-500 dark:text-slate-400">
                                {{ $subheading }}
                            </div>
                        @endif
                    </div>

                    <div class="fi-auth-form-container">
                        {{ $slot }}
                    </div>

                    <div class="mt-10 pt-8 border-t border-slate-100 dark:border-slate-800 text-center">
                        <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold">
                            Powered by {{ config('brand.name') }} Secure Gateway
                        </p>
                    </div>
                </div>

                <div class="mt-8 text-center">
                    <p class="text-xs text-slate-400 dark:text-slate-500">
                        &copy; {{ date('Y') }} {{ config('brand.name') }}. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <style>
        .fi-auth-form-container form button[type="submit"] {
            background-color: rgb(var(--primary-600)) !important;
            padding-top: 0.75rem !important;
            padding-bottom: 0.75rem !important;
            font-weight: 700 !important;
            border-radius: 0.75rem !important;
            box-shadow: 0 10px 15px -3px rgba(var(--primary-500), 0.3) !important;
        }
        .fi-auth-form-container form button[type="submit"]:hover {
            box-shadow: 0 20px 25px -5px rgba(var(--primary-500), 0.4) !important;
            transform: translateY(-1px);
        }
        .fi-auth-form-container .fi-fo-field-wrp-label label {
            font-size: 0.75rem !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            font-weight: 700 !important;
            color: #64748b !important;
        }
        .fi-auth-form-container input {
            border-radius: 0.75rem !important;
        }
    </style>
</x-filament-panels::layout.base>
