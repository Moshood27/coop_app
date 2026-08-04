window.biometricScanner = {
    getDefaultUrl() {
        return window.biometricDefaultUrl || 'http://localhost:8080/biometric/scan';
    },

    getScannerUrl() {
        return localStorage.getItem('biometric_scanner_url') || this.getDefaultUrl();
    },

    setScannerUrl(url) {
        localStorage.setItem('biometric_scanner_url', url);
    },

    async captureTemplate() {
        const scannerUrl = this.getScannerUrl();
        console.log('Fetching biometric from: ' + scannerUrl);

        try {
            const response = await fetch(scannerUrl);

            if (!response.ok) {
                if (response.status === 404) {
                    throw new Error('Scanner service path not found (404). Please check your URL configuration.');
                }
                throw new Error('Scanner service responded with status: ' + response.status);
            }

            const data = await response.json();
            if (!data.template) {
                throw new Error('No fingerprint template received from scanner.');
            }
            return data.template;
        } catch (err) {
            console.error('Biometric capture error:', err);

            let message = err.message;
            if (err instanceof TypeError && err.message === 'Failed to fetch') {
                message = 'Could not connect to scanner service. ';

                if (window.location.protocol === 'https:' && scannerUrl.startsWith('http:')) {
                    message += 'This is likely a CORS or Mixed Content issue. Ensure your local service supports CORS and allows requests from ' + window.location.origin + '.';
                } else {
                    message += 'Ensure the local biometric service is running at ' + scannerUrl + '.';
                }
            }

            throw new Error(message);
        }
    },

    showConfigModal() {
        const currentUrl = this.getScannerUrl();
        const newUrl = prompt('Enter USB Biometric Scanner Service URL:', currentUrl);
        if (newUrl !== null) {
            this.setScannerUrl(newUrl);
            if (window.FilamentNotification) {
                new FilamentNotification()
                    .title('Scanner URL Updated')
                    .body('New URL: ' + newUrl)
                    .success()
                    .send();
            } else {
                alert('Scanner URL Updated to: ' + newUrl);
            }
        }
    },

    /**
     * Helper for Filament suffix actions to avoid HTML escaping issues in PHP strings.
     * @param {Object} wire - The Livewire object ($wire)
     * @param {string} field - The field to set (e.g. 'data.biometric_template')
     * @param {HTMLElement} element - The element that was clicked ($el)
     */
    async scanAndSet(wire, field, element) {
        if (element) element.classList.add("animate-pulse");

        try {
            const template = await this.captureTemplate();
            wire.set(field, template);

            if (window.FilamentNotification) {
                new FilamentNotification()
                    .title("Biometric Captured")
                    .success()
                    .send();
            }
        } catch (err) {
            if (window.FilamentNotification) {
                new FilamentNotification()
                    .title("Scanner Error")
                    .body(err.message)
                    .danger()
                    .persistent()
                    .send();
            } else {
                alert("Scanner Error: " + err.message);
            }
        } finally {
            if (element) element.classList.remove("animate-pulse");
        }
    }
};
