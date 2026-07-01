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
2.  **Enrollment:** When an admin clicks the "Scan" icon next to the Biometric Template field (or the "Capture Biometric" button), the browser sends a request to the local service. The service triggers the USB scanner and returns a raw template string.
3.  **Storage:** The template string is saved in the `biometric_template` column of the `users` or `member_applications` table.
4.  **Verification:** During attendance marking at a `BiometricStation`, the admin selects a member and clicks "Verify & Mark". The scanner captures a fresh template, which is compared against the stored template.

## Admin Interface

-   **Biometric Attendance Station:** A dedicated Livewire component (`/admin/biometric-station/{meeting}`) for high-speed attendance marking and bulk enrollment.
-   **User Resource:** Admins can manually capture or update biometric templates using the "Scan" button in the member's profile.
-   **Member Applications:** Admins can capture biometrics directly from the "View Application" page.

## Removal of Simulation

The system no longer uses mock templates. If the `BIOMETRIC_SCANNER_URL` is unreachable or the scanner fails to return a template, an error notification will be displayed. Ensure the local service is running and the scanner is connected.

## VPS & Public IP Deployment

When running the application on a live public IP (VPS environment):

1.  **HTTPS Requirement:** Your VPS should be served over HTTPS.
2.  **Mixed Content:** Browsers may block requests from an HTTPS site to an HTTP local service.
    -   Use `http://localhost` or `http://127.0.0.1` as the `BIOMETRIC_SCANNER_URL` as these are often exempted from Mixed Content restrictions.
    -   If using a custom domain for the local service, ensure it has a valid SSL certificate.
3.  **CORS:** Ensure your `backend/.env` includes the public domain in `CORS_ALLOWED_ORIGINS`.

## Security Considerations

-   The `biometric_template` is stored as a raw string. In high-security environments, ensure the local service provides encrypted templates.
-   The administrative interface requires appropriate permissions to access biometric enrollment features.

---
**Date:** 2026-07-01
**Version:** 1.0 (USB Scanner Edition)
