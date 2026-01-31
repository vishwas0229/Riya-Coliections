# InfinityFree Deployment Guide

This guide provides step-by-step instructions for deploying the Riya Collections PHP backend to InfinityFree hosting.

## Prerequisites

- InfinityFree hosting account
- Domain name configured
- FTP/File Manager access
- MySQL database created through InfinityFree control panel

## Step 1: Prepare Your InfinityFree Account

### 1.1 Create MySQL Database
1. Log into your InfinityFree control panel
2. Go to "MySQL Databases"
3. Create a new database (note the database name, username, and password)
4. Note your MySQL hostname (usually `sqlXXX.infinityfree.com`)

### 1.2 Enable SSL (Recommended)
1. Go to "SSL Certificates" in control panel
2. Enable free SSL certificate for your domain
3. Force HTTPS redirects

## Step 2: Upload Files

### 2.1 Upload via File Manager (Recommended)
1. Access File Manager from InfinityFree control panel
2. Navigate to `htdocs` folder (or `public_html` depending on your setup)
3. Upload all files from your local `htdocs` directory
4. Ensure proper file permissions (755 for directories, 644 for files)

### 2.2 Upload via FTP
```bash
# Example FTP upload (adjust paths as needed)
ftp your-domain.com
# Enter your FTP credentials
cd htdocs
put -r /path/to/your/local/htdocs/* .
```

## Step 3: Configure Environment

### 3.1 Create .env File
1. Copy the InfinityFree environment template:
   ```bash
   cp deployment/infinityfree/.env.infinityfree .env
   ```

2. Edit `.env` with your InfinityFree database credentials:
   ```env
   # Database Configuration (from InfinityFree control panel)
   DB_HOST=sqlXXX.infinityfree.com
   DB_NAME=epiz_XXXXXXXX_riya_collections
   DB_USER=epiz_XXXXXXXX
   DB_PASSWORD=your_database_password
   
   # Application Configuration
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.com
   
   # Security (IMPORTANT: Generate strong secrets)
   JWT_SECRET=your_super_secure_jwt_secret_minimum_32_characters_long
   SESSION_SECRET=your_session_secret_here
   
   # Email Configuration (use your email provider)
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USER=your-email@gmail.com
   SMTP_PASSWORD=your-app-password
   
   # Razorpay Configuration
   RAZORPAY_KEY_ID=your_razorpay_key_id
   RAZORPAY_KEY_SECRET=your_razorpay_key_secret
   ```

### 3.2 Set File Permissions
```bash
# Set proper permissions for InfinityFree
chmod 755 htdocs/
chmod 755 htdocs/uploads/
chmod 755 htdocs/logs/
chmod 755 htdocs/cache/
chmod 644 htdocs/.env
chmod 644 htdocs/.htaccess
```

## Step 4: Database Setup

### 4.1 Import Database Schema
1. Access phpMyAdmin from InfinityFree control panel
2. Select your database
3. Import the database schema:
   - Go to "Import" tab
   - Upload `migrations/complete_schema.sql`
   - Click "Go"

### 4.2 Run Migration Script
1. Access your domain: `https://your-domain.com/deployment/shared/database_migration.php`
2. This will create any missing tables and indexes
3. Delete the migration script after successful run for security

## Step 5: Configure .htaccess

### 5.1 Use Production .htaccess
```bash
cp deployment/infinityfree/.htaccess.production .htaccess
```

### 5.2 Verify URL Rewriting
The production `.htaccess` includes:
- URL rewriting for API endpoints
- Security headers
- File upload restrictions
- Cache control
- HTTPS enforcement

## Step 6: Test Deployment

### 6.1 Run Health Check
1. Visit: `https://your-domain.com/api/health`
2. Should return JSON with system status
3. Check database connectivity

### 6.2 Test API Endpoints
```bash
# Test basic endpoints
curl https://your-domain.com/api/health
curl https://your-domain.com/api/products

# Test authentication
curl -X POST https://your-domain.com/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"testpass123","first_name":"Test","last_name":"User"}'
```

### 6.3 Verify File Uploads
1. Test product image upload through admin panel
2. Check that images are properly resized and stored
3. Verify image URLs are accessible

## Step 7: Security Configuration

### 7.1 Secure Sensitive Files
Ensure these files are not publicly accessible:
- `.env` (protected by .htaccess)
- `config/` directory
- `logs/` directory
- `vendor/` directory

### 7.2 Enable Security Features
1. Verify HTTPS is working
2. Check security headers in browser dev tools
3. Test rate limiting on API endpoints

## Step 8: Performance Optimization

### 8.1 Enable Caching
InfinityFree supports basic caching:
1. Verify `.htaccess` cache headers are working
2. Test static file caching
3. Monitor page load times

### 8.2 Optimize Images
1. Ensure image compression is working
2. Test WebP format support
3. Verify responsive image serving

## Troubleshooting

### Common Issues

#### Database Connection Errors
```
Error: SQLSTATE[HY000] [2002] Connection refused
```
**Solution:** 
- Verify database credentials in `.env`
- Check InfinityFree database hostname
- Ensure database exists and user has permissions

#### File Permission Errors
```
Warning: file_put_contents(): Permission denied
```
**Solution:**
- Set proper directory permissions (755)
- Ensure web server can write to logs/ and uploads/
- Contact InfinityFree support if permissions persist

#### .htaccess Errors
```
Internal Server Error (500)
```
**Solution:**
- Check .htaccess syntax
- Verify mod_rewrite is enabled
- Use simpler .htaccess if needed

#### Memory Limit Errors
```
Fatal error: Allowed memory size exhausted
```
**Solution:**
- InfinityFree has memory limits
- Optimize code for lower memory usage
- Consider upgrading to premium hosting

### Performance Issues

#### Slow Database Queries
- InfinityFree has query time limits
- Optimize database queries
- Add proper indexes
- Use pagination for large datasets

#### File Upload Issues
- Check file size limits in `.env`
- Verify upload directory permissions
- Test with smaller files first

## Maintenance

### Regular Tasks
1. **Monitor logs:** Check `logs/` directory regularly
2. **Update dependencies:** Keep Composer packages updated
3. **Backup database:** Use InfinityFree backup tools
4. **Security updates:** Monitor for PHP security updates

### Monitoring
1. Set up uptime monitoring (external service)
2. Monitor error logs for issues
3. Check disk space usage
4. Monitor database performance

## Support and Resources

- **InfinityFree Documentation:** https://infinityfree.net/support
- **InfinityFree Community:** https://forum.infinityfree.net/
- **PHP Documentation:** https://php.net/docs.php
- **Project Issues:** Contact development team

## Next Steps

After successful deployment:
1. Configure domain DNS if needed
2. Set up email delivery monitoring
3. Configure backup schedules
4. Set up monitoring and alerts
5. Test all functionality thoroughly
6. Train users on the new system

---

**Important Notes:**
- InfinityFree has resource limitations (CPU, memory, database queries)
- Monitor usage to avoid account suspension
- Consider upgrading to premium hosting for production use
- Always test thoroughly before going live