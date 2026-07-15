# Automated Backups & Proactive Health Monitoring

This document describes the implementation, configuration, and usage of the automated backup system and the proactive health monitoring system in the Cooperative application.

## 1. Automated Backups (Laravel Backup)

The application uses `spatie/laravel-backup` to ensure data safety and disaster recovery.

### How it Works
- **Database Dumps**: It creates a dump of the MySQL database.
- **File Backups**: It zips the `storage/app/public` directory and other important application files.
- **Encryption**: Backups are encrypted using the password defined in the `.env` file.
- **Multi-Destination Storage**:
    - **Local**: Backups are stored in `storage/app/backups`.
    - **Google Drive**: Backups are uploaded to a specified Google Drive folder for off-site resilience. The driver is registered in `AppServiceProvider.php`.

### Configuration
Configuration is located in `backend/config/backup.php`.

**Required Environment Variables:**
```env
# Google Drive Credentials
GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REFRESH_TOKEN=
GOOGLE_DRIVE_FOLDER_ID=

# Backup Archive Password
BACKUP_ARCHIVE_PASSWORD=your-secure-password
```

### Manual Commands
Run these commands from the `backend` directory:
- **Run Backup**: `php artisan backup:run` (Backs up database and files)
- **Database Only**: `php artisan backup:run --only-db`
- **Clean Old Backups**: `php artisan backup:clean` (Removes old backups based on rotation strategy)
- **List Backups**: `php artisan backup:list` (Shows the status of all configured backup destinations)

### Scheduling
Backups are automatically scheduled in `backend/routes/console.php`:
- `backup:clean`: Daily at 01:00 AM.
- `backup:run`: Daily at 02:00 AM.

---

## 2. Proactive Health Monitoring (Laravel Health)

The application uses `spatie/laravel-health` to monitor system status and alert administrators of potential issues.

### How it Works
- **System Checks**: Regularly checks essential services like Database, Redis/Cache, Horizon, and Queue workers.
- **Environment Checks**: Monitors disk space, debug mode status, and environment settings.
- **Backup Verification**: Verifies that the latest backup was successful and is not too old.

### Registered Health Checks
The checks are registered in `AppServiceProvider.php`:
1. **Used Disk Space**: Alerts if disk usage exceeds 90%.
2. **Database**: Verifies database connection.
3. **Debug Mode**: Warns if `APP_DEBUG` is enabled in production.
4. **Environment**: Ensures the environment is set correctly.
5. **Queue**: Verifies that the queue worker is running.
6. **Schedule**: Verifies that the task scheduler is working.
7. **Cache**: Verifies cache driver functionality.
8. **Horizon**: Monitors Laravel Horizon status.
9. **Backups**: Checks if recent backups exist and are successful.

### Manual Commands
- **Run Health Checks**: `php artisan health:run`
- **View Health Status (JSON)**: `php artisan health:list --json`

### Notifications
If a check fails, notifications can be sent via Email or Slack based on `.env` configuration:
```env
HEALTH_NOTIFICATIONS_ENABLED=true
HEALTH_TO_ADDRESS=admin@example.com
HEALTH_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
```

### Scheduling
Health checks are scheduled in `backend/routes/console.php`:
- `health:run`: Every 15 minutes.

---

## 3. Disaster Recovery Steps

In the event of a total server failure:
1. Re-provision the server using `BUILD_AND_DEPLOY.md`.
2. Retrieve the latest backup from Google Drive or local storage.
3. Use `php artisan backup:run --only-db` (manually or via restore script) to restore the database.
4. Unzip the backup archive using the `BACKUP_ARCHIVE_PASSWORD`.
5. Restore the `storage/app/public` files.
