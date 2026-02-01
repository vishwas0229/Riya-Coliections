# InfinityFree Deployment Guide - Riya Collections

This guide provides step-by-step instructions for deploying the Riya Collections integrated application to InfinityFree hosting using the clean, unified project structure.

## Prerequisites

- InfinityFree hosting account
- Domain name configured
- FTP/File Manager access
- MySQL database created through InfinityFree control panel

## Project Structure Overview

The clean project structure you'll be deploying:
```
project/
├── public/              # Web root (upload to htdocs/)
├── app/                 # Application logic (upload to htdocs/)
├── storage/             # Storage directory (upload to htdocs/)
├── database/            # Database migrations (for setup only)
├── .env.example         # Environment template
└── composer.json        # Dependencies
```

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

### 2.1 Upload Complete Project Structure
1. Access File Manager from InfinityFree control panel
2. Navigate to `htdocs` folder (this will be your web root)
3. Upload the **entire project structure** to `htdocs/`:
   ```
   htdocs/
   ├── public/          # Frontend assets and entry point
   ├── app/             # PHP application logic
   ├── storage/         # Logs, cache, backups
   ├── database/        # Migration files (for setup)
   ├── .env             # Your environment config
   └── composer.json    # Dependencies
   ```

### 2.2 Configure Web Server Document Root
**CRITICAL:** InfinityFree serves files from `htdocs/`, but your app expects `public/` to be the web root.

**Option A: Move public/ contents to htdocs/ root (Recommended)**
```bash
# After upload, move public/ contents to htdocs/ root
mv htdocs/public/* htdocs/
mv htdocs/public/.htaccess htdocs/
rmdir htdocs/public/
```

**Option B: Use .htaccess redirect (Alternative)**
Create `htdocs/.htaccess` to redirect to public/:
```apache
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/public/
RewriteRule ^(.*)$ /public/$1 [L]
```

### 2.3 Upload via FTP (Alternative)
```bash
# Upload entire project to htdocs
ftp your-domain.com
cd htdocs
put -r /path/to/your/project/* .
```

## Step 3: Configure Environment

### 3.1 Create .env File
1. Copy the environment template:
   ```bash
   cp .env.example .env
   ```

2. Edit `.env` with your InfinityFree database credentials:
   ```env
   # Application Configuration
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.com
   
   # Database Configuration (from InfinityFree control panel)
   DB_HOST=sqlXXX.infinityfree.com
   DB_PORT=3306
   DB_NAME=epiz_XXXXXXXX_riya_collections
   DB_USER=epiz_XXXXXXXX
   DB_PASSWORD=your_database_password
   
   # JWT Configuration (IMPORTANT: Generate strong secrets)
   JWT_SECRET=your_super_secure_jwt_secret_minimum_32_characters_long
   JWT_EXPIRES_IN=24h
   JWT_REFRESH_SECRET=your_super_secure_refresh_secret_minimum_32_characters
   JWT_REFRESH_EXPIRES_IN=7d
   JWT_ISSUER=riya-collections
   JWT_AUDIENCE=riya-collections-users
   
   # Password Hashing
   BCRYPT_SALT_ROUNDS=12
   
   # Security
   FORCE_HTTPS=true
   VALID_API_KEYS=
   
   # Email Configuration (use your email provider)
   MAIL_DRIVER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=your-email@gmail.com
   MAIL_PASSWORD=your-app-password
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@your-domain.com
   MAIL_FROM_NAME="Riya Collections"
   
   # Razorpay Configuration
   RAZORPAY_KEY_ID=your_razorpay_key_id
   RAZORPAY_KEY_SECRET=your_razorpay_key_secret
   RAZORPAY_WEBHOOK_SECRET=your_webhook_secret
   ```

### 3.2 Set File Permissions
```bash
# Set proper permissions for InfinityFree
chmod 755 storage/
chmod 755 storage/logs/
chmod 755 storage/cache/
chmod 755 storage/backups/
chmod 755 uploads/
chmod 644 .env
chmod 644 .htaccess
chmod 644 index.php
```

### 3.3 Update File Paths (if using Option A)
If you moved `public/` contents to `htdocs/` root, update `index.php`:
```php
// Change these paths in index.php:
require_once __DIR__ . '/app/config/environment.php';
require_once __DIR__ . '/app/utils/Logger.php';
// etc. (remove the '../' since files are now in same directory)
```

## Step 4: Database Setup

### 4.1 Import Database Schema
1. Access phpMyAdmin from InfinityFree control panel
2. Select your database
3. Import the database schema in order:
   ```sql
   -- Import these files from database/migrations/ in order:
   001_create_auth_tables.sql
   002_create_payments_table.sql
   003_create_emails_table.sql
   004_create_admin_tables.sql
   ```

### 4.2 Verify Database Connection
1. Create a test file `test_db.php` in your web root:
   ```php
   <?php
   require_once 'app/config/environment.php';
   require_once 'app/models/Database.php';
   
   try {
       $db = Database::getInstance();
       echo "Database connection successful!";
       
       // Test a simple query
       $result = $db->query("SHOW TABLES");
       echo "<br>Tables found: " . count($result);
   } catch (Exception $e) {
       echo "Database connection failed: " . $e->getMessage();
   }
   ?>
   ```
2. Visit `https://your-domain.com/test_db.php`
3. Delete the test file after verification

## Step 5: Configure .htaccess

### 5.1 Production .htaccess Configuration
Create/update `.htaccess` in your web root:
```apache
# Riya Collections - Production .htaccess for InfinityFree

RewriteEngine On

# Security Headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'"

# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# API Routes - Route to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} ^/api/
RewriteRule ^(.*)$ index.php [QSA,L]

# Frontend Routes - Route to index.php for SPA
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} !^/api/
RewriteCond %{REQUEST_URI} !^/assets/
RewriteCond %{REQUEST_URI} !^/uploads/
RewriteRule ^(.*)$ index.php [QSA,L]

# Cache Control for Static Assets
<FilesMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$">
    ExpiresActive On
    ExpiresDefault "access plus 1 month"
    Header append Cache-Control "public, immutable"
</FilesMatch>

# Protect sensitive files
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<FilesMatch "^(composer\.(json|lock)|\.git.*|\.env.*|README\.md)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Protect directories
RedirectMatch 403 ^/app/
RedirectMatch 403 ^/storage/
RedirectMatch 403 ^/database/
RedirectMatch 403 ^/tests/
RedirectMatch 403 ^/vendor/

# File Upload Security
<FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">
    <IfModule mod_dir.c>
        DirectoryIndex disabled
    </IfModule>
    <Files "index.php">
        Order allow,deny
        Allow from all
    </Files>
    Order allow,deny
    Deny from all
</FilesMatch>

# Limit file upload size (adjust as needed)
php_value upload_max_filesize 10M
php_value post_max_size 10M
php_value max_execution_time 300
php_value max_input_time 300
```

## Step 6: Test Deployment

### 6.1 Test Basic Functionality
1. **Homepage:** Visit `https://your-domain.com`
   - Should load the frontend application
   - Check browser console for errors

2. **API Health Check:** Visit `https://your-domain.com/api/health`
   - Should return JSON with system status
   - Verify database connectivity

3. **Static Assets:** Check `https://your-domain.com/assets/css/style.css`
   - Should load CSS files properly
   - Verify caching headers

### 6.2 Test API Endpoints
```bash
# Test health endpoint
curl https://your-domain.com/api/health

# Test products endpoint
curl https://your-domain.com/api/products

# Test user registration
curl -X POST https://your-domain.com/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email":"test@example.com",
    "password":"testpass123",
    "first_name":"Test",
    "last_name":"User"
  }'

# Test user login
curl -X POST https://your-domain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email":"test@example.com",
    "password":"testpass123"
  }'
```

### 6.3 Test Frontend Features
1. **User Registration/Login:**
   - Go to `/pages/register.html`
   - Create a test account
   - Login with the account

2. **Product Browsing:**
   - Visit `/pages/products.html`
   - Check if products load (may be empty initially)

3. **Admin Panel:**
   - Visit `/pages/admin-login.html`
   - Test admin authentication

### 6.4 Test File Uploads
1. Login to admin panel
2. Try uploading a product image
3. Verify image is stored in `uploads/products/`
4. Check that image URLs are accessible

## Step 7: Security Configuration

### 7.1 Verify File Protection
Test that sensitive files are protected:
```bash
# These should return 403 Forbidden
curl https://your-domain.com/.env
curl https://your-domain.com/app/config/database.php
curl https://your-domain.com/storage/logs/
curl https://your-domain.com/composer.json
```

### 7.2 Test Security Headers
1. Open browser developer tools
2. Check Network tab for security headers:
   - `X-Content-Type-Options: nosniff`
   - `X-Frame-Options: DENY`
   - `X-XSS-Protection: 1; mode=block`
   - `Content-Security-Policy`

### 7.3 Verify HTTPS Enforcement
1. Try accessing `http://your-domain.com`
2. Should automatically redirect to `https://`

## Step 8: Performance Optimization

### 8.1 Enable Compression
Add to `.htaccess` if not already present:
```apache
# Enable Gzip Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
```

### 8.2 Test Caching
1. Load a CSS/JS file twice
2. Check Network tab - second load should be from cache
3. Verify `Cache-Control` headers are present

### 8.3 Monitor Performance
1. Use browser dev tools to check page load times
2. Monitor API response times
3. Check for any slow database queries in logs

## Troubleshooting

### Common Issues

#### Database Connection Errors
```
Error: SQLSTATE[HY000] [2002] Connection refused
```
**Solution:** 
- Verify database credentials in `.env`
- Check InfinityFree database hostname format: `sqlXXX.infinityfree.com`
- Ensure database exists and user has permissions
- Test connection with the test script from Step 4.2

#### File Permission Errors
```
Warning: file_put_contents(): Permission denied
```
**Solution:**
- Set proper directory permissions: `chmod 755 storage/`
- Ensure web server can write to `storage/logs/`, `storage/cache/`, `uploads/`
- Contact InfinityFree support if permissions persist

#### .htaccess Errors (500 Internal Server Error)
```
Internal Server Error (500)
```
**Solution:**
- Check `.htaccess` syntax carefully
- Remove complex rules and add them back one by one
- Verify Apache modules are available on InfinityFree
- Use simpler rewrite rules if needed:
  ```apache
  RewriteEngine On
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^(.*)$ index.php [QSA,L]
  ```

#### Path Resolution Errors
```
Warning: require_once(app/config/environment.php): failed to open stream
```
**Solution:**
- If you moved `public/` contents to root, update paths in `index.php`
- Change `../app/` to `app/` in all require statements
- Verify file structure matches your deployment method

#### Memory Limit Errors
```
Fatal error: Allowed memory size exhausted
```
**Solution:**
- InfinityFree has strict memory limits (64MB typically)
- Optimize code to use less memory
- Reduce image processing operations
- Consider upgrading to premium hosting for production

#### File Upload Issues
```
Error: File upload failed
```
**Solution:**
- Check `uploads/` directory permissions: `chmod 755 uploads/`
- Verify file size limits in `.env` and `.htaccess`
- Test with smaller files first
- Check InfinityFree file upload limits

### Performance Issues

#### Slow Database Queries
- InfinityFree has query time limits (5-10 seconds)
- Optimize database queries with proper indexes
- Use pagination for large datasets
- Avoid complex JOIN operations

#### Slow Page Loading
- InfinityFree has CPU time limits
- Optimize PHP code for efficiency
- Enable caching where possible
- Minimize external API calls

#### Resource Limit Exceeded
```
Error 508: Resource Limit Is Reached
```
**Solution:**
- Monitor resource usage in InfinityFree control panel
- Optimize code to use fewer resources
- Consider caching strategies
- Upgrade to premium hosting if needed

### Debugging Tips

#### Enable Debug Mode (Temporarily)
In `.env`:
```env
APP_DEBUG=true
```
**Remember to set back to `false` for production!**

#### Check Error Logs
1. Access File Manager
2. Check `storage/logs/` directory
3. Look for recent error logs
4. Check InfinityFree error logs in control panel

#### Test Individual Components
Create test scripts to isolate issues:
```php
<?php
// test_config.php - Test configuration loading
require_once 'app/config/environment.php';
echo "Config loaded successfully";

// test_database.php - Test database connection
require_once 'app/models/Database.php';
$db = Database::getInstance();
echo "Database connected";

// test_auth.php - Test authentication
require_once 'app/services/AuthService.php';
echo "Auth service loaded";
?>
```

## Maintenance

### Regular Tasks
1. **Monitor logs:** Check `storage/logs/` directory regularly for errors
2. **Update dependencies:** Keep Composer packages updated (when possible)
3. **Backup database:** Use InfinityFree backup tools or phpMyAdmin export
4. **Security updates:** Monitor for PHP security updates
5. **Clean cache:** Clear `storage/cache/` periodically
6. **Monitor disk space:** Check file usage in InfinityFree control panel

### Monitoring Setup
1. **Uptime monitoring:** Use external service (UptimeRobot, Pingdom)
2. **Error monitoring:** Check logs daily for PHP errors
3. **Performance monitoring:** Monitor page load times
4. **Resource monitoring:** Check InfinityFree resource usage

### Backup Strategy
1. **Database backups:**
   - Export via phpMyAdmin weekly
   - Store backups off-site (Google Drive, Dropbox)
   
2. **File backups:**
   - Download `uploads/` directory regularly
   - Backup configuration files (`.env`, `.htaccess`)

### Update Process
1. **Test updates locally first**
2. **Backup before any changes**
3. **Update during low-traffic periods**
4. **Monitor for issues after updates**

## InfinityFree Specific Considerations

### Resource Limits
- **CPU Time:** 30 seconds per script execution
- **Memory:** 64MB per script
- **Database:** 400MB storage, limited queries per hour
- **File Storage:** 5GB total
- **Bandwidth:** Unlimited (fair use)

### Restrictions
- **No cron jobs** (use external services like cron-job.org)
- **No shell access** (FTP/File Manager only)
- **Limited PHP extensions** (check availability)
- **No custom PHP.ini** (use .htaccess for basic settings)

### Best Practices for InfinityFree
1. **Optimize for low resource usage**
2. **Use efficient database queries**
3. **Implement proper caching**
4. **Monitor resource usage regularly**
5. **Keep file sizes small**
6. **Use external services for heavy tasks**

## Support and Resources

- **InfinityFree Documentation:** https://infinityfree.net/support
- **InfinityFree Community:** https://forum.infinityfree.net/
- **PHP Documentation:** https://php.net/docs.php
- **Project Repository:** [Your GitHub/GitLab URL]

## Post-Deployment Checklist

### Immediate Tasks (Day 1)
- [ ] Verify all pages load correctly
- [ ] Test user registration and login
- [ ] Test API endpoints
- [ ] Verify file uploads work
- [ ] Check security headers
- [ ] Test HTTPS enforcement
- [ ] Verify database connectivity

### Short-term Tasks (Week 1)
- [ ] Set up uptime monitoring
- [ ] Configure backup schedule
- [ ] Test all user workflows
- [ ] Monitor error logs
- [ ] Check performance metrics
- [ ] Train users on the system

### Long-term Tasks (Month 1)
- [ ] Monitor resource usage trends
- [ ] Optimize slow queries
- [ ] Review security logs
- [ ] Plan for scaling if needed
- [ ] Document any custom configurations
- [ ] Create disaster recovery plan

## Next Steps After Deployment

1. **Domain Configuration:**
   - Configure custom domain if using subdomain
   - Set up proper DNS records
   - Configure email forwarding

2. **SEO and Analytics:**
   - Add Google Analytics
   - Submit sitemap to search engines
   - Configure meta tags

3. **Production Optimization:**
   - Monitor and optimize performance
   - Set up error alerting
   - Plan for traffic growth

4. **User Training:**
   - Create user documentation
   - Train administrators
   - Set up support processes

---

**Important Notes for InfinityFree:**
- InfinityFree is great for development and small projects
- Monitor resource usage to avoid account suspension
- Consider upgrading to premium hosting for high-traffic production use
- Always test thoroughly before going live
- Keep backups of everything important

**Success Indicators:**
- ✅ Homepage loads without errors
- ✅ API endpoints return proper responses
- ✅ User registration/login works
- ✅ File uploads function correctly
- ✅ Admin panel is accessible
- ✅ Database operations work smoothly
- ✅ Security headers are present
- ✅ HTTPS is enforced