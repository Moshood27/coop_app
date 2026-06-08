<div wire:poll.2s="refreshPayload" class="flex flex-col items-center justify-center p-6 space-y-4">
    <div class="text-center">
        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $meeting->name }}</h3>
        <p class="text-sm text-gray-500">Scan this QR code with your app to mark attendance.</p>
    </div>

    <div class="p-4 bg-white rounded-xl shadow-sm border border-gray-100">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={{ urlencode($payload) }}"
             alt="Attendance QR"
             class="w-64 h-64 mx-auto"
        />
    </div>

    <div class="flex items-center space-y-1 flex-col">
         <div class="flex items-center space-x-2">
            <span class="relative flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
            </span>
            <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Live Rolling QR</span>
        </div>
        <p class="text-[10px] text-gray-400 italic">Refreshes every 2 seconds or on each scan</p>
    </div>
</div>
