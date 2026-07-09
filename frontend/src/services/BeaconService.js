import { LocalNotifications } from '@capacitor/local-notifications';
import { Capacitor } from '@capacitor/core';

class BeaconService {
  constructor() {
    this.isNative = Capacitor.getPlatform() !== 'web';
    this.monitoring = false;
    this.currentRegion = null;
    this.initialized = false;
  }

  async waitForPlugin(timeout = 3000) {
    if (!this.isNative) return false;
    if (this.initialized) return true;

    const start = Date.now();
    while (Date.now() - start < timeout) {
      if (window.cordova?.plugins?.locationManager) {
        this.initialized = true;
        return true;
      }
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    return false;
  }

  get locationManager() {
    return window.cordova?.plugins?.locationManager;
  }

  async requestPermissions() {
    if (!(await this.waitForPlugin())) return false;
    try {
      // iBeacon monitoring requires location permissions. 
      // Always authorization is required for background monitoring on iOS.
      if (this.locationManager.requestAlwaysAuthorization) {
        await this.locationManager.requestAlwaysAuthorization();
      }
      
      const notificationStatus = await LocalNotifications.requestPermissions();
      return notificationStatus.display === 'granted';
    } catch (e) {
      console.error('Failed to request beacon permissions', e);
      return false;
    }
  }

  async startMonitoring(meeting) {
    if (!(await this.waitForPlugin()) || !meeting || !meeting.beacon_uuid) return;
    try {
      const permitted = await this.requestPermissions();
      if (!permitted) {
        console.warn('Beacon permissions not granted');
        return;
      }
      
      // Stop any existing monitoring
      await this.stopMonitoring();

      const identifier = `meeting-${meeting.id}`;
      const uuid = meeting.beacon_uuid;
      const major = meeting.beacon_major ? parseInt(meeting.beacon_major) : null;
      const minor = meeting.beacon_minor ? parseInt(meeting.beacon_minor) : null;

      const locationManager = this.locationManager;
      if (!locationManager) return;

      // Create delegate to handle events
      const delegate = new locationManager.Delegate();

      delegate.didEnterRegion = (pluginResult) => {
        console.log('Entered beacon region:', pluginResult);
        this.showNotification(meeting);
      };

      locationManager.setDelegate(delegate);

      // Create the beacon region
      this.currentRegion = new locationManager.BeaconRegion(identifier, uuid, major, minor);

      // Start monitoring
      await locationManager.startMonitoringForRegion(this.currentRegion);
      
      this.monitoring = true;
      console.log('Started monitoring for beacon region:', identifier);
    } catch (e) {
      console.error('Failed to start beacon monitoring', e);
    }
  }

  async stopMonitoring() {
    if (!(await this.waitForPlugin())) return;
    try {
      const locationManager = this.locationManager;
      if (!locationManager) return;

      if (this.currentRegion) {
        await locationManager.stopMonitoringForRegion(this.currentRegion);
        this.currentRegion = null;
      }
      
      this.monitoring = false;
    } catch (e) {
      console.error('Failed to stop beacon monitoring', e);
    }
  }

  async showNotification(meeting) {
    try {
      // Capacitor LocalNotifications ID must be a number. 
      // meeting.id might be a string from API.
      const notificationId = Math.abs(parseInt(meeting.id)) || Math.floor(Math.random() * 1000000);

      await LocalNotifications.schedule({
        notifications: [
          {
            title: 'Nearby Meeting Venue \uD83D\uDCCD',
            body: `You are near "${meeting.name}". Don't forget to mark your attendance!`,
            id: notificationId,
            schedule: { at: new Date(Date.now() + 1000) },
            extra: {
              meetingId: meeting.id,
              type: 'attendance_reminder'
            }
          }
        ]
      });
    } catch (e) {
      console.error('Failed to show local notification', e);
    }
  }
}

export default new BeaconService();