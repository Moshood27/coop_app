# Beacon-Based Attendance System Guide

## Overview
The Beacon-Based Attendance system provides a seamless, "touchless" experience for marking attendance at meetings. By using Bluetooth Low Energy (BLE) Beacons, the system can automatically detect a member's presence within a specific room or area, reducing the need for manual QR code scanning or PIN entry.

## Use Case: Member Convenience
1. **Detection:** When a member enters the meeting venue, their mobile app detects the BLE Beacon signal.
2. **Verification:** The app verifies the Beacon's **UUID**, **Major**, and **Minor** values against the ones configured for the meeting.
3. **One-Touch Marking:** The member receives a notification or a button appears in their app: "Beacon Detected. Mark Attendance now?".
4. **Security:** Combining Beacon detection with GPS geofencing and device binding ensures that the member is physically present at the venue.

## How to Get Beacon UUID, Major, and Minor
Administrators need to configure the unique identifiers of their physical beacons in the Meeting management panel.

### 1. Identify Your Beacon Type
Most beacons follow the **iBeacon** (Apple) or **Eddystone** (Google) protocols. This system uses the iBeacon standard (UUID, Major, Minor).

### 2. Use a Mobile Scanner App
To find the IDs of your physical beacon, download one of the following apps on your smartphone:
*   **Locate Beacon** (iOS/Android)
*   **Beacon Scanner** (Android - by Bridgestone)
*   **NRF Connect** (iOS/Android - by Nordic Semiconductor)
*   **iBeacon Scanner** (iOS)

### 3. Retrieve the Values
1. Turn on your physical beacon.
2. Open the scanner app on your phone.
3. Bring your phone close to the beacon.
4. The app will list nearby BLE devices. Look for one labeled "iBeacon" or the manufacturer's name.
5. Record the following values:
    *   **UUID:** A long string (e.g., `E2C56DB5-DFFB-48D2-B060-D0F5A71096E0`).
    *   **Major:** A number between 0 and 65535 (e.g., `100`).
    *   **Minor:** A number between 0 and 65535 (e.g., `1`).

### 4. Configure the Meeting
Paste these values into the **Beacon UUID**, **Beacon Major**, and **Beacon Minor** fields when creating or editing a meeting in the Admin Panel.

## Benefits for Admins
*   **Higher Accuracy:** Unlike GPS, Beacons work perfectly indoors and can distinguish between adjacent rooms.
*   **Reduced Queues:** No need for everyone to crowd around a single QR code printout.
*   **Fraud Prevention:** Physical proximity to the beacon is required, making it harder to fake attendance from outside the venue.
