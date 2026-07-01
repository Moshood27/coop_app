# USB Biometric Scanner Integration Guide

This document describes the USB Desktop Biometric Scanner integration for administrative enrollment and attendance marking.

## Overview

The system allows administrators to enroll members' fingerprints using a USB biometric scanner (e.g., DigitalPersona, ZKTeco) connected to their desktop. This replaces WebAuthn for centralized administrative workflows, allowing one station to handle multiple members efficiently.

## Configuration

The integration requires a local service running on the administrator's PC that interfaces with the USB scanner and provides an HTTP API for the browser.

### Environment Variables

Add the following to your `backend/.env` file:

```env
# Enable/Disable USB Biometric Scanner Integration
BIOMETRIC_SCANNER_ENABLED=true

# URL of the local biometric service running on the admin PC
BIOMETRIC_SCANNER_URL=http://localhost:8080/biometric/scan
```

## How It Works

1.  **Local Service:** A small desktop application or browser extension must be running on the admin PC. This service listens for requests on the configured `BIOMETRIC_SCANNER_URL`.
2.  **Enrollment:** When an admin clicks "Enroll Fingerprint", the system sends a request to the local service. The service triggers the USB scanner, captures the fingerprint, and returns a raw template string.
3.  **Storage:** The template string is saved in the `biometric_template` column of the `users` or `member_applications` table.
4.  **Verification:** During attendance marking at a `BiometricStation`, the admin selects a member and clicks "Verify & Mark". The scanner captures a fresh template, which is then compared against the stored template in the database.

## Admin Interface

-   **Biometric Attendance Station:** A dedicated Livewire component (`/admin/biometric-station/{meeting}`) for high-speed attendance marking.
-   **User Resource:** Admins can manually view or update biometric templates under the "Identity & Membership" tab of a member's profile.
-   **Member Applications:** Biometrics can be captured during the application phase and are automatically transferred to the user profile upon approval.

## Security Considerations

-   The `biometric_template` is stored as a raw string. In high-security environments, ensure the local service provides encrypted templates.
-   The administrative interface requires appropriate permissions to access biometric enrollment features.

---
**Date:** 2026-07-01
**Version:** 1.0 (USB Scanner Edition)
