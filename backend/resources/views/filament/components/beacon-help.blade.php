<div class="space-y-4 text-sm">
    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <h4 class="font-bold mb-2">Recommended Scanner Apps</h4>
        <ul class="list-disc list-inside space-y-1">
            <li><strong>Android:</strong> Beacon Scanner (by Bridgestone), nRF Connect</li>
            <li><strong>iOS:</strong> Locate Beacon, iBeacon Scanner</li>
        </ul>
    </div>

    <div>
        <h4 class="font-bold mb-2 text-primary-600">Steps to retrieve IDs:</h4>
        <ol class="list-decimal list-inside space-y-2">
            <li>Power on your physical BLE Beacon.</li>
            <li>Open the scanner app on your mobile device.</li>
            <li>Look for a device broadcasting as <strong>iBeacon</strong>.</li>
            <li>Copy the <strong>UUID</strong> (usually a string like <code>E2C56DB5-DFFB-48D2-B060-D0F5A71096E0</code>). Ensure dashes are included.</li>
            <li>Note the <strong>Major</strong> and <strong>Minor</strong> integers (e.g., 100, 1).</li>
        </ol>
    </div>

    <div class="flex items-start p-3 bg-blue-50 dark:bg-blue-900/30 rounded-md">
        <x-heroicon-m-information-circle class="w-5 h-5 text-blue-500 mr-2 shrink-0" />
        <p class="text-xs text-blue-700 dark:text-blue-300">
            Ensure your beacon is set to iBeacon mode. Some beacons might require a vendor-specific app to configure their initial settings.
        </p>
    </div>
</div>
