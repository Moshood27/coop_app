import { IBeacon } from '@awesome-cordova-plugins/ibeacon';
import { LocalNotifications } from '@capacitor/local-notifications';
import { Capacitor } from '@capacitor/core';

class BeaconService {
  constructor() {
    this.isNative = Capacitor.getPlatform() !== 'web';
    this.monitoring = false;
    this.currentRegion = null;
    this.delegateSubscription = null;
  }

  async requestPermissions() {
    if (!this.isNative) return false;
    try {
      // iBeacon monitoring requires location permissions. 
      // Always authorization is required for background monitoring on iOS.
      await IBeacon.requestAlwaysAuthorization();
      
      const notificationStatus = await LocalNotifications.requestPermissions();
      return notificationStatus.display === 'granted';
    } catch (e) {
      console.error('Failed to request beacon permissions', e);
      return false;
    }
  }

  async startMonitoring(meeting) {
    if (!this.isNative || !meeting || !meeting.beacon_uuid) return;
    try {
      await this.requestPermissions();

      // Stop any existing monitoring
      await this.stopMonitoring();

      const identifier = `meeting-${meeting.id}`;
      const uuid = meeting.beacon_uuid;
      const major = meeting.beacon_major ? parseInt(meeting.beacon_major) : null;
      const minor = meeting.beacon_minor ? parseInt(meeting.beacon_minor) : null;

      // Create the beacon region
      this.currentRegion = IBeacon.BeaconRegion(identifier, uuid, major, minor);

      // Set up the delegate to handle events
      const delegate = IBeacon.getDelegate();

      // Subscribe to region entry events
      this.delegateSubscription = delegate.didEnterRegion().subscribe(
        async (data) => {
          console.log('Entered beacon region:', data);
          await this.showNotification(meeting);
        }
      );

      // Start monitoring
      await IBeacon.startMonitoringForRegion(this.currentRegion);
      
      this.monitoring = true;
      console.log('Started monitoring for beacon region:', identifier);
    } catch (e) {
      console.error('Failed to start beacon monitoring', e);
    }
  }

  async stopMonitoring() {
    if (!this.isNative) return;
    try {
      if (this.currentRegion) {
        await IBeacon.stopMonitoringForRegion(this.currentRegion);
        this.currentRegion = null;
      }
      
      if (this.delegateSubscription) {
        this.delegateSubscription.unsubscribe();
        this.delegateSubscription = null;
      }
      
      this.monitoring = false;
    } catch (e) {
      console.error('Failed to stop beacon monitoring', e);
    }
  }

  async showNotification(meeting) {
    try {
      await LocalNotifications.schedule({
        notifications: [
          {
            title: 'Nearby Meeting Venue 📍',
            body: `You are near "${meeting.name}". Don't forget to mark your attendance!`,
            id: meeting.id,
            schedule: { at: new Date(Date.now() + 1000) },
            sound: null,
            attachments: null,
            actionTypeId: '',
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