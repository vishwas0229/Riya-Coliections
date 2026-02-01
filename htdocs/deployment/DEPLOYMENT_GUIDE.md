# Riya Collections PHP Backend - Complete Deployment Guide

This comprehensive guide covers the complete deployment process for the Riya Collections PHP backend, from preparation to post-deployment verification.

## Table of Contents

1. [Pre-Deployment Preparation](#pre-deployment-preparation)
2. [Environment-Specific Deployment](#environment-specific-deployment)
3. [Database Setup and Migration](#database-setup-and-migration)
4. [Post-Deployment Verification](#post-deployment-verification)
5. [Troubleshooting](#troubleshooting)
6. [Maintenance and Monitoring](#maintenance-and-monitoring)

## Pre-Deployment Preparation

### 1. System Requirements

**Minimum Requirements:**
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server with URL rewriting support
- SSL certificate (recommended)
- 128MB RAM minimum (256MB recommended)
- 100MB disk space minimum

**Required PHP Extensions:**
- pdo
- pdo_mysql
- json
- curl
- gd
- mbstring
- openssl
- fileinfo
- filter
- hash

### 2. Pre-Deployment Checklist

- [ ] Hosting account set up and configured
- [ ] Domain name configured with DNS
- [ ] SSL certificate installed
- [ ] Database created with appropriate permissions
- [ ] SMTP email service configured
- [ ] Razorpay account set up (for payments)
- [ ] All code tested in staging environment
- [ ] Backup of current system (if upgrading)

### 3. Create Backup (If Upgrading)

Before deploying over an existing system, create a complete backup:

```bash
# Using the backup script
https://your-domain.com/deployment/shared/backup_before_deploy.php?confirm=backup

# Or manually via command line
./deployment/scripts/deploy.sh production --backup
```

## Environment-Specific Deployment

### Option 1: InfinityFree Hosting

InfinityFree is a popular free hosting service. Follow these steps:

#### Step 1: Prepare InfinityFree Account
1. Create MySQL database in control panel
2. Note database credentials (host, name, username, password)
3. Enable SSL certificate for your domain

#### Step 2: Upload Files
```bash
# Upload all files to htdocs directory via File Manager or FTP
# Ensure proper file permissions (755 for directories, 644 for files)
```

#### Step 3: Configure Environment
```bash
# Copy InfinityFree environment template
cp deployment/infinityfree/.env.infinityfree .env

# Edit .env with your specific credentials
# Update database settings, JWT secrets, email configuration
```

#### Step 4: Set Up .htaccess
```bash
# Copy production .htaccess
cp deployment/infinityfree/.htaccess.production .htaccess
```

**Detailed InfinityFree Guide:** [deployment/infinityfree/deploy.md](infinityfree/deploy.md)

### Option 2: Generic PHP Hosting

For other PHP hosting providers:

#### Step 1: Upload Files
Upload all files to your web root directory (usually `public_html` or `htdocs`)

#### Step 2: Configure Environment
```bash
# Copy appropriate environment template
cp deployment/templates/.env.production .env  # For production
cp deployment/templates/.env.staging .env     # For staging

# Edit .env with your hosting-specific settings
```

#### Step 3: Set Permissions
```bash
chmod 755 uploads/ logs/ cache/ backups/
chmod 644 .env .htaccess
chmod 600 config/*.php  # If supported
```

### Option 3: Automated Deployment

Use the deployment script for automated deployment:

```bash
# Production deployment with backup and tests
./deployment/scripts/deploy.sh production --backup --test

# Staging deployment
./deployment/scripts/deploy.sh staging

# Dry run to see what would be done
./deployment/scripts/deploy.sh production --dry-run

# Get help
./deployment/scripts/deploy.sh --help
```

## Database Setup and Migration

### Step 1: Validate Environment

Before setting up the database, validate your environment:

```bash
# Visit the environment validator
https://your-domain.com/deployment/shared/environment_validator.php
```

This will check:
- PHP version and extensions
- Environment variables
- Database connectivity
- File permissions
- Security configuration

### Step 2: Run Database Migration

```bash
# Visit the migration script
https://your-domain.com/deployment/shared/database_migration.php?confirm=yes
```

The migration script will:
- Create all required database tables
- Set up proper indexes and foreign keys
- Insert default data (categories, admin user)
- Optimize database tables
- Verify installation

**Default Admin Credentials:**
- Username: `admin`
- Password: `admin123`
- **⚠️ CHANGE IMMEDIATELY AFTER FIRST LOGIN**

### Step 3: Verify Database Setup

After migration, verify the database:
- Check that all tables were created
- Verify default data was inserted
- Test database connectivity
- Check for any error messages

## Post-Deployment Verification

### Step 1: Run Health Check

```bash
# Basic health check
https://your-domain.com/api/health

# Comprehensive health check
https://your-domain.com/deployment/scripts/health_check.php
```

### Step 2: Run Deployment Verification

```bash
# Complete deployment verification
https://your-domain.com/deployment/scripts/verify_deployment.php?verify=deployment
```

This comprehensive test will verify:
- Environment configuration
- Database connectivity and schema
- API endpoint functionality
- Authentication system
- File system permissions
- Security configuration
- Performance metrics

### Step 3: Manual Testing

Test critical functionality manually:

#### Authentication Tests
```bash
# Test user registration
curl -X POST https://your-domain.com/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"testpass123","first_name":"Test","last_name":"User"}'

# Test user login
curl -X POST https://your-domain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"testpass123"}'
```

#### Product Tests
```bash
# Test product listing
curl https://your-domain.com/api/products

# Test product search
curl "https://your-domain.com/api/products?search=test"
```

#### Admin Tests
1. Login to admin panel with default credentials
2. Change admin password immediately
3. Test product management
4. Test order management
5. Verify dashboard functionality

### Step 4: Frontend Integration

If you have a frontend application:
1. Update API base URL in frontend configuration
2. Test all user workflows end-to-end
3. Verify CORS configuration
4. Test file uploads
5. Test payment processing

## Security Hardening

### Step 1: Change Default Credentials

**Immediately after deployment:**
1. Change default admin password
2. Create additional admin users if needed
3. Remove or disable default test accounts

### Step 2: Secure File Access

Verify these files are not publicly accessible:
- `.env` - Environment configuration
- `config/` - Application configuration
- `logs/` - Application logs
- `vendor/` - Composer dependencies
- `deployment/` - Deployment scripts

### Step 3: Remove Deployment Scripts

**After successful deployment and verification:**
```bash
# Remove deployment scripts for security
rm -rf deployment/
```

### Step 4: Configure Security Headers

Ensure your `.htaccess` includes security headers:
- X-Content-Type-Options
- X-XSS-Protection
- X-Frame-Options
- Strict-Transport-Security
- Content-Security-Policy

## Troubleshooting

### Common Issues

#### Database Connection Errors
```
Error: SQLSTATE[HY000] [2002] Connection refused
```
**Solutions:**
- Verify database credentials in `.env`
- Check database server is running
- Verify database user permissions
- Check firewall settings

#### File Permission Errors
```
Warning: file_put_contents(): Permission denied
```
**Solutions:**
- Set directory permissions to 755
- Set file permissions to 644
- Ensure web server can write to uploads/, logs/, cache/
- Contact hosting provider if issues persist

#### .htaccess Errors
```
Internal Server Error (500)
```
**Solutions:**
- Check .htaccess syntax
- Verify mod_rewrite is enabled
- Use simpler .htaccess if needed
- Check server error logs

#### Memory Limit Errors
```
Fatal error: Allowed memory size exhausted
```
**Solutions:**
- Increase PHP memory_limit
- Optimize code for lower memory usage
- Consider upgrading hosting plan

### Debug Mode

For troubleshooting, temporarily enable debug mode:

```env
# In .env file
APP_DEBUG=true
LOG_LEVEL=debug
SHOW_ERROR_DETAILS=true
```

**⚠️ Remember to disable debug mode in production!**

### Log Files

Check these log files for issues:
- `logs/app.log` - Application logs
- `logs/error.log` - PHP error logs
- Server error logs (location varies by hosting)

## Maintenance and Monitoring

### Regular Maintenance Tasks

#### Daily
- Monitor error logs
- Check system health
- Verify backup completion

#### Weekly
- Review security logs
- Update dependencies if needed
- Check disk space usage
- Monitor performance metrics

#### Monthly
- Review and rotate log files
- Update PHP and system packages
- Review security configuration
- Test backup restoration

### Monitoring Setup

#### Health Monitoring
```bash
# Set up automated health checks
curl -f https://your-domain.com/api/health || echo "Health check failed"
```

#### Log Monitoring
```bash
# Monitor error logs
tail -f logs/error.log | grep -i error
```

#### Performance Monitoring
- Monitor response times
- Track memory usage
- Monitor database performance
- Check disk space usage

### Backup Strategy

#### Automated Backups
Set up automated backups using cron jobs or hosting provider tools:

```bash
# Daily database backup
0 2 * * * /usr/bin/mysqldump -u username -p password database > /path/to/backup/db_$(date +\%Y\%m\%d).sql

# Weekly full backup
0 3 * * 0 tar -czf /path/to/backup/full_$(date +\%Y\%m\%d).tar.gz /path/to/application/
```

#### Backup Verification
Regularly test backup restoration:
1. Restore backup to staging environment
2. Verify data integrity
3. Test application functionality
4. Document any issues

### Updates and Upgrades

#### Security Updates
- Monitor for PHP security updates
- Update dependencies regularly
- Review security advisories
- Apply patches promptly

#### Feature Updates
- Test updates in staging environment
- Create backup before updating
- Follow deployment process
- Verify functionality after update

## Support and Resources

### Documentation
- [InfinityFree Deployment Guide](infinityfree/deploy.md)
- [Deployment Checklist](shared/deployment_checklist.md)
- [Environment Validator](shared/environment_validator.php)
- [Database Migration](shared/database_migration.php)

### Scripts and Tools
- `deploy.sh` - Automated deployment script
- `rollback.sh` - Rollback to previous version
- `health_check.php` - System health verification
- `verify_deployment.php` - Comprehensive deployment verification
- `backup_before_deploy.php` - Pre-deployment backup

### Getting Help

If you encounter issues:
1. Check the troubleshooting section above
2. Review log files for error messages
3. Run the health check and verification scripts
4. Check hosting provider documentation
5. Contact support with specific error messages and log entries

### Best Practices

1. **Always backup before deployment**
2. **Test in staging environment first**
3. **Use version control for all changes**
4. **Monitor logs regularly**
5. **Keep dependencies updated**
6. **Follow security best practices**
7. **Document all configuration changes**
8. **Test disaster recovery procedures**

---

**Remember:** This deployment guide ensures a robust, secure, and maintainable deployment of the Riya Collections PHP backend. Follow all steps carefully and don't skip security measures.