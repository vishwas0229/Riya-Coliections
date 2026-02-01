# Backup and Recovery System Implementation

## Overview

The Backup and Recovery System provides comprehensive database backup and restoration capabilities for the Riya Collections PHP backend. This system ensures data protection, disaster recovery, and business continuity through automated backups, integrity verification, and reliable restoration processes.

## Architecture

### Core Components

1. **BackupService** - Handles backup creation, scheduling, and management
2. **RecoveryService** - Manages restoration and recovery operations
3. **BackupController** - Provides API endpoints for backup operations
4. **CLI Scripts** - Command-line tools for automated operations

### Features

- **Automated Backup Creation** with compression and verification
- **Scheduled Backups** with configurable frequency (hourly, daily, weekly)
- **Backup Retention Management** with automatic cleanup
- **Full Database Restoration** with integrity checks
- **Selective Table Restoration** for targeted recovery
- **Dry-Run Testing** for safe restoration validation
- **Backup Verification** with checksum validation
- **Metadata Tracking** for backup management
- **Admin API Endpoints** for web-based management
- **CLI Tools** for cron-based automation

## Implementation Details

### BackupService Class

Located: `services/BackupService.php`

#### Key Methods

```php
// Create a comprehensive backup
public function createBackup($options = [])

// List all available backups
public function listBackups()

// Get backup information
public function getBackupInfo($backupId)

// Schedule automatic backups
public function scheduleBackup($frequency = 'daily')

// Run scheduled backup
public function runScheduledBackup()
```

#### Configuration Options

```php
$config = [
    'max_backups' => 30,        // Keep 30 days of backups
    'compress' => true,         // Enable gzip compression
    'verify_integrity' => true, // Verify backup after creation
    'chunk_size' => 1000,      // Process data in chunks
    'timeout' => 300           // 5 minutes timeout
];
```

### RecoveryService Class

Located: `services/RecoveryService.php`

#### Key Methods

```php
// Restore database from backup
public function restoreFromBackup($backupId, $options = [])

// Test restoration without making changes
public function testRestore($backupId)

// Restore specific tables only
public function restoreSpecificTables($backupId, $tables)

// Get recovery options for a backup
public function getRecoveryOptions($backupId)

// Create recovery point before operations
public function createRecoveryPoint($description = 'Recovery point')
```

#### Safety Features

- **Pre-restoration Backup** - Creates backup before restoration
- **Integrity Verification** - Validates backup before and after restoration
- **Transaction Support** - Uses database transactions for atomicity
- **Rollback Capability** - Can rollback failed restorations
- **Dry-Run Mode** - Test restoration without making changes

### API Endpoints

All backup endpoints require admin authentication and are prefixed with `/api/admin/backup/`

#### GET Endpoints

- `GET /api/admin/backup/list` - List all backups
- `GET /api/admin/backup/info/{id}` - Get backup information
- `GET /api/admin/backup/recovery-options/{id}` - Get recovery options
- `GET /api/admin/backup/schedule` - Get backup schedule
- `GET /api/admin/backup/status` - Get system status

#### POST Endpoints

- `POST /api/admin/backup/create` - Create new backup
- `POST /api/admin/backup/restore/{id}` - Restore from backup
- `POST /api/admin/backup/test-restore/{id}` - Test restoration
- `POST /api/admin/backup/schedule` - Configure backup schedule
- `POST /api/admin/backup/run-scheduled` - Run scheduled backup

#### DELETE Endpoints

- `DELETE /api/admin/backup/delete/{id}` - Delete backup

### CLI Scripts

#### Backup Cron Script

Located: `scripts/backup_cron.php`

```bash
# Set up daily backups
php scripts/backup_cron.php --frequency=daily

# Force backup regardless of schedule
php scripts/backup_cron.php --force

# Set up hourly backups
php scripts/backup_cron.php --frequency=hourly
```

#### Cron Job Setup

```bash
# Daily backup at 2 AM
0 2 * * * /usr/bin/php /path/to/htdocs/scripts/backup_cron.php

# Hourly backups during business hours
0 9-17 * * 1-5 /usr/bin/php /path/to/htdocs/scripts/backup_cron.php --frequency=hourly
```

## Backup Format

### File Structure

```
backups/
├── backup_2024-01-31_14-30-15_backup_65ba1f2f3d4e5.sql.gz
├── backup_2024-01-31_02-00-00_backup_65b9e8a0b1c2d.sql.gz
├── metadata.json
├── schedule.json
└── .htaccess
```

### Backup Content

```sql
-- Riya Collections Database Backup
-- Generated: 2024-01-31 14:30:15
-- Description: Automated backup
-- Options: {"include_data":true,"compress":true,"verify":true}

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET AUTOCOMMIT = 0;
START TRANSACTION;

-- Table structure for `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  -- ... table definition
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data for table `users`
INSERT INTO `users` VALUES
(1,'user1@example.com','John','Doe','2024-01-31 14:30:15'),
(2,'user2@example.com','Jane','Smith','2024-01-31 14:30:15');

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
-- Backup completed
```

### Metadata Format

```json
{
    "backup_65ba1f2f3d4e5": {
        "backup_id": "backup_65ba1f2f3d4e5",
        "file": "backup_2024-01-31_14-30-15_backup_65ba1f2f3d4e5.sql.gz",
        "full_path": "/path/to/backups/backup_2024-01-31_14-30-15_backup_65ba1f2f3d4e5.sql.gz",
        "size": 1048576,
        "created_at": "2024-01-31 14:30:15",
        "duration": 2.45,
        "options": {
            "include_data": true,
            "compress": true,
            "verify": true,
            "description": "Automated backup"
        },
        "tables_count": 15,
        "checksum": "d41d8cd98f00b204e9800998ecf8427e"
    }
}
```

## Security Features

### Access Control

- **Admin Authentication Required** - All backup operations require admin privileges
- **Directory Protection** - Backup directory protected with .htaccess
- **File Permissions** - Backup files created with restricted permissions (0755)
- **Path Validation** - Input validation prevents directory traversal attacks

### Data Protection

- **Checksum Verification** - MD5 checksums for integrity validation
- **Compression** - Optional gzip compression for space efficiency
- **Retention Policies** - Automatic cleanup of old backups
- **Secure Deletion** - Proper file deletion with cleanup verification

## Error Handling

### Backup Errors

- **Connection Failures** - Graceful handling of database connection issues
- **Disk Space** - Monitoring and alerts for insufficient disk space
- **Permission Errors** - Clear error messages for file permission issues
- **Timeout Handling** - Configurable timeouts for large backups

### Recovery Errors

- **Corruption Detection** - Automatic detection of corrupted backups
- **Rollback Support** - Automatic rollback on restoration failures
- **Validation Errors** - Pre-restoration validation with detailed error reporting
- **Transaction Safety** - Database transactions ensure atomicity

## Performance Optimization

### Backup Performance

- **Chunked Processing** - Large tables processed in configurable chunks
- **Compression** - Gzip compression reduces file size and I/O
- **Selective Backups** - Option to backup structure only or specific tables
- **Connection Reuse** - Efficient database connection management

### Recovery Performance

- **Streaming Restoration** - Large backups processed as streams
- **Parallel Processing** - Multiple tables can be restored concurrently
- **Progress Tracking** - Real-time progress monitoring for long operations
- **Memory Management** - Efficient memory usage for large datasets

## Monitoring and Logging

### Backup Monitoring

- **Success/Failure Tracking** - Comprehensive logging of all operations
- **Performance Metrics** - Duration, size, and throughput monitoring
- **Health Checks** - Regular validation of backup integrity
- **Alerting** - Notifications for backup failures or issues

### Log Entries

```
[2024-01-31 14:30:15] INFO: Starting database backup {"backup_id":"backup_65ba1f2f3d4e5"}
[2024-01-31 14:30:17] INFO: Backing up table: users
[2024-01-31 14:30:18] INFO: Database backup completed successfully {"backup_id":"backup_65ba1f2f3d4e5","duration":2.45}
```

## Testing

### Property-Based Testing

The system includes comprehensive property-based tests that verify:

- **Data Integrity** - Backup and restoration preserve data exactly
- **Verification Accuracy** - Corruption detection works correctly
- **Selective Restoration** - Only specified tables are affected
- **Error Handling** - Proper error responses for various failure scenarios

### Test Coverage

- **Unit Tests** - Individual component testing
- **Integration Tests** - End-to-end workflow testing
- **Property Tests** - Universal correctness properties
- **Performance Tests** - Load and stress testing

## Usage Examples

### Creating a Manual Backup

```php
$backupService = new BackupService();

$result = $backupService->createBackup([
    'description' => 'Pre-deployment backup',
    'compress' => true,
    'verify' => true
]);

if ($result['success']) {
    echo "Backup created: " . $result['backup_id'];
}
```

### Restoring from Backup

```php
$recoveryService = new RecoveryService();

$result = $recoveryService->restoreFromBackup($backupId, [
    'verify_before' => true,
    'verify_after' => true,
    'create_backup' => true
]);

if ($result['success']) {
    echo "Restoration completed successfully";
}
```

### API Usage

```bash
# Create backup
curl -X POST /api/admin/backup/create \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"description":"Manual backup","compress":true}'

# List backups
curl -X GET /api/admin/backup/list \
  -H "Authorization: Bearer $ADMIN_TOKEN"

# Restore backup
curl -X POST /api/admin/backup/restore/backup_65ba1f2f3d4e5 \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"verify_before":true,"create_backup":true}'
```

## Best Practices

### Backup Strategy

1. **Regular Schedules** - Set up automated daily backups
2. **Retention Policies** - Keep appropriate number of backups (30 days recommended)
3. **Verification** - Always verify backup integrity
4. **Compression** - Use compression for storage efficiency
5. **Off-site Storage** - Consider copying backups to remote storage

### Recovery Planning

1. **Test Restorations** - Regularly test backup restoration procedures
2. **Recovery Points** - Create recovery points before major operations
3. **Selective Restoration** - Use selective restoration when possible
4. **Verification** - Always verify data integrity after restoration
5. **Documentation** - Maintain recovery procedures documentation

### Security Considerations

1. **Access Control** - Restrict backup access to authorized administrators
2. **Encryption** - Consider encrypting sensitive backups
3. **Audit Logging** - Log all backup and recovery operations
4. **File Permissions** - Use appropriate file system permissions
5. **Network Security** - Secure backup file transfers

## Troubleshooting

### Common Issues

1. **Backup Failures**
   - Check disk space availability
   - Verify database connectivity
   - Review file permissions
   - Check timeout settings

2. **Restoration Failures**
   - Verify backup file integrity
   - Check database permissions
   - Review foreign key constraints
   - Validate backup format

3. **Performance Issues**
   - Adjust chunk size for large tables
   - Consider compression settings
   - Review timeout configurations
   - Monitor system resources

### Error Messages

- `"Backup file not found"` - Backup file has been deleted or moved
- `"Checksum mismatch"` - Backup file is corrupted
- `"Insufficient disk space"` - Not enough space for backup creation
- `"Database connection failed"` - Cannot connect to database
- `"Permission denied"` - File system permission issues

## Future Enhancements

### Planned Features

1. **Incremental Backups** - Backup only changed data
2. **Cloud Storage Integration** - Support for AWS S3, Google Cloud
3. **Encryption Support** - Built-in backup encryption
4. **Backup Scheduling UI** - Web interface for schedule management
5. **Real-time Monitoring** - Live backup status monitoring

### Performance Improvements

1. **Parallel Processing** - Multi-threaded backup creation
2. **Delta Backups** - Only backup changes since last backup
3. **Compression Algorithms** - Support for different compression methods
4. **Streaming Backups** - Direct streaming to storage without local files
5. **Memory Optimization** - Reduced memory usage for large databases

This comprehensive backup and recovery system ensures data protection and business continuity for the Riya Collections e-commerce platform while maintaining high performance and security standards.