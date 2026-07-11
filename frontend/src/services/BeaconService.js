import { LocalNotifications } from '@capacitor/local-notifications';
import { Capacitor } from '@capacitor/core';
import { Device } from '@capacitor/device';
import { Geolocation } from '@capacitor/geolocation';
import axios from '../http';

class BeaconService {
  constructor() {
    this.isNative = Capacitor.getPlatform() !== 'web';
    this.monitoring = false;
    this.currentRegion = null;
    this.insideRegion = false;
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

      delegate.didEnterRegion = async (pluginResult) => {
        console.log('Entered beacon region:', pluginResult);
        this.insideRegion = true;
        this.showNotification(meeting);
        await this.autoMarkAttendance(meeting);
      };

      delegate.didExitRegion = (pluginResult) => {
        console.log('Exited beacon region:', pluginResult);
        this.insideRegion = false;
      };

      delegate.didDetermineStateForRegion = async (pluginResult) => {
        console.log('Determined state for region:', pluginResult);
        const wasInside = this.insideRegion;
        this.insideRegion = pluginResult.state === 'CLRegionStateInside';
        
        if (this.insideRegion && !wasInside) {
           await this.autoMarkAttendance(meeting);
        }
      };

      locationManager.setDelegate(delegate);

      // Create the beacon region
      this.currentRegion = new locationManager.BeaconRegion(identifier, uuid, major, minor);

      // Start monitoring
      await locationManager.startMonitoringForRegion(this.currentRegion);
      await locationManager.requestStateForRegion(this.currentRegion);
      
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

  async autoMarkAttendance(meeting) {
    try {
      // Prevent multiple auto-marks in a short period
      const lastMarked = localStorage.getItem(`auto_marked_${meeting.id}`);
      if (lastMarked && Date.now() - parseInt(lastMarked) < 3600000) { // 1 hour
        console.log('Already auto-marked recently for this meeting');
        return;
      }

      console.log('Attempting to auto-mark attendance for meeting:', meeting.id);

      const deviceId = (await Device.getId()).identifier;
      let lat = null, lng = null;
      
      try {
        const loc = await Geolocation.getCurrentPosition({ 
          enableHighAccuracy: true,
          timeout: 10000 
        });
        lat = loc.coords.latitude;
        lng = loc.coords.longitude;
      } catch (err) {
        console.warn('Could not get location for auto-mark', err);
      }

      const payload = {
        beacon_uuid: meeting.beacon_uuid,
        beacon_major: meeting.beacon_major,
        beacon_minor: meeting.beacon_minor,
        device_uuid: deviceId,
        lat: lat,
        lng: lng,
        auto_mark: true
      };

      const response = await axios.post(`/api/meetings/${meeting.id}/mark-beacon`, payload);
      
      localStorage.setItem(`auto_marked_${meeting.id}`, Date.now().toString());

      await LocalNotifications.schedule({
        notifications: [
          {
            title: 'Attendance Marked! \u2705',
            body: `Your attendance for "${meeting.name}" has been marked automatically.`,
            id: Math.floor(Math.random() * 1000000),
            schedule: { at: new Date(Date.now() + 500) }
          }
        ]
      });

      console.log('Auto-mark success:', response.data);
    } catch (e) {
      console.error('Auto-mark attendance failed:', e.response?.data || e.message);
    }
  }

  /**
   * Manually check if the beacon is currently nearby using ranging.
   * Useful for the "Mark via Room Beacon" button to ensure proximity.
   */
  async checkProximity(meeting) {
    if (!(await this.waitForPlugin())) return false;

    return new Promise(async (resolve) => {
      const locationManager = this.locationManager;
      const identifier = `ranging-check-${meeting.id}`;
      const uuid = meeting.beacon_uuid;
      const major = meeting.beacon_major ? parseInt(meeting.beacon_major) : null;
      const minor = meeting.beacon_minor ? parseInt(meeting.beacon_minor) : null;

      const region = new locationManager.BeaconRegion(identifier, uuid, major, minor);
      let found = false;

      const delegate = new locationManager.Delegate();
      delegate.didRangeBeaconsInRegion = (pluginResult) => {
        if (pluginResult.beacons && pluginResult.beacons.length > 0) {
          found = true;
        }
      };

      locationManager.setDelegate(delegate);
      
      try {
        await locationManager.startRangingBeaconsInRegion(region);
        
        // Scan for 3 seconds
        setTimeout(async () => {
          await locationManager.stopRangingBeaconsInRegion(region);
          // Restore the main monitoring delegate if needed
          if (this.monitoring) {
            this.startMonitoring(meeting); 
          }
          resolve(found);
        }, 3000);
      } catch (err) {
        console.error('Proximity check failed', err);
        resolve(false);
      }
    });
  }
}

export default new BeaconService();