# Admin Use Cases

This document outlines key administrative workflows and use cases for the Cooperative management system, focusing on both the Filament Admin Panel and authorized administrative actions within the mobile application.

---

## 1. Decentralized Attendance Marking (Mark for Member)

**Role**: Branch Manager, Clerk, or Admin
**Platform**: Mobile Application

### Scenario
An authorized officer (e.g., Branch Manager) is present at a physical meeting or event. Some members are unable to mark their own attendance due to device issues, lack of internet, or other technical difficulties.

### Process
1.  **Login**: The officer logs into the mobile app using their credentials.
2.  **Navigate to Attendance**: Open the **Attendance** section from the dashboard.
3.  **Select Meeting**: The app will display the currently "Ongoing" meeting.
4.  **Identify Privileges**: If the user has the `mark_attendance` permission, a "Mark for Member" section will appear below the standard attendance options.
5.  **Search Member**:
    *   Enter the member's name, phone number, or membership number in the search field.
    *   The system performs a real-time search across the cooperative's database.
6.  **Mark Attendance**:
    *   From the search results, locate the correct member.
    *   Click the **Mark Present** button.
7.  **Confirmation**:
    *   A confirmation dialog appears.
    *   Upon confirmation, the system records the attendance, tagged with `marked_by_admin_{id}` for audit purposes.
8.  **Notification**: The member receives an instant push notification and a database notification informing them that their attendance has been marked by an authorized officer.

---

## 2. Administrative Biometric Enrollment

**Role**: Admin
**Platform**: Filament Admin Panel (Desktop with USB Scanner)

### Scenario
A new member joins the cooperative, or an existing member needs to update their biometric data for secure attendance verification at "Biometric Stations".

### Process
1.  **Prepare Hardware**: Ensure the USB Fingerprint Scanner is connected and the local biometric service is running on the workstation.
2.  **Access Member Profile**:
    *   Navigate to **Users** in the Filament sidebar.
    *   Search for and open the specific member's record.
3.  **Initiate Scan**:
    *   Locate the **Biometric Template** field or the **Capture Biometric** action.
    *   Click the **Scan** icon.
4.  **Capture Fingerprint**:
    *   The member places their finger on the USB scanner.
    *   The browser communicates with the local service to retrieve the template.
5.  **Save**:
    *   The captured template string is automatically populated in the field.
    *   Save the user record to store the template securely in the database.
6.  **Verification**: The member can now use high-speed verification at any physical meeting equipped with a Biometric Station.

---

## 3. Branch-Wide Bulk Communication

**Role**: Admin / Branch Coordinator
**Platform**: Filament Admin Panel

### Scenario
An administrator needs to send an urgent update (e.g., meeting reschedule or emergency announcement) to all members of a specific branch.

### Process
1.  **Navigate to Branches**: Open the **Branches** resource in the sidebar.
2.  **Select Action**: Click the **Bulk Communicate** button next to the target branch.
3.  **Draft Message**:
    *   Type the message content.
    *   Select the communication channels (**SMS** and/or **Push Notification**).
4.  **Dispatch**:
    *   Click **Send**.
    *   The system queues a background job (`SendBulkCommunication`) to handle the delivery, ensuring the admin interface remains responsive even for branches with thousands of members.
5.  **Audit**: The action is logged in the **Activity Logs** for compliance and tracking.

---

## 4. Loan Default Penalty Management

**Role**: Admin / Credit Officer
**Platform**: Filament Admin Panel

### Scenario
Managing members who have defaulted on their loans and enforcing the "duration-matching" wait period for future loans.

### Process
1.  **Monitor Defaults**: Use the **Loan Penalties** report to view members currently in default or those serving wait periods.
2.  **Automatic Enforcement**: 
    *   When a member pays off a defaulted loan, the system automatically calculates the time spent in default.
    *   A penalty record is created, blocking new loan applications for an equal duration.
3.  **Manual Override (Rare)**: If a penalty was applied in error or needs adjustment due to special Shariah Board approval:
    *   Navigate to the **Loan Penalties** resource.
    *   Adjust the `expires_at` date or delete the penalty record (requires Super Admin privileges).
4.  **Verification**: When the member attempts to apply for a new loan via the mobile app, the system checks for active penalties and displays the remaining wait time if applicable.

---
**Last Updated**: 2026-07-13
