# Deployment Guide - Riya Collections Integrated Application

## Overview

This guide covers deploying the integrated Riya Collections application that combines the PHP backend and frontend into a unified structure.

## Pre-Deployment Checklist

- [ ] Web server with PHP 7.4+ support
- [ ] MySQL 5.7+ or MariaDB 10.2+
- [ ] Apache with mod_rewrite enabled (or Nginx with URL rewriting)
- [ ] Composer installed (for PHP dependencies)
- [ ] SSL certificate configured (recommended for production)

## Deployment Steps

### 1. File Upload and Structure

Upload all files to your web server, ensuring the directory structure is preserved:

```
your-domain.com/
├── public/              # Web root (point your domain here)
├── app/                 # Application logic (outside web root)
├── storage/             # Storage directory (outside web root)
├── database/            # Database files (outside web root)
├── .env                 # Environment configuration
└── composer.json        # PHP dependencies
```

**Important**: Only the `public/` directory should be accessible via web browser.

### 2. Web Server Configuration

#### Apache Configuration

If using Apache, ensure the document root points to the `public/` directory:

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/project/public
    
    <Directory /path/to/project/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Optional: Redirect to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /path/to/project/public
    
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    <Directory /path/to/project/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx Configuration

For Nginx, use this configuration:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /path/to/project/public;
    index index.php index.html;

    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options SAMEORIGIN;
    add_header X-XSS-Protection "1; mode=block";

    # Handle API requests
    location /api/ {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Handle static assets with caching
    location ~* \.(css|js|png|jpg|jpeg|gif|svg|webp|woff|woff2|ttf|otf|eot|ico|pdf)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Handle uploads
    location /uploads/ {
        expires 1M;
        add_header Cache-Control "public";
        try_files $uri =404;
    }

    # Handle PHP files
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Handle frontend SPA routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Block access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ \.(env|log|sql|md|txt|json|lock|yml|yaml|xml|ini|conf)$ {
        deny all;
    }
}
```

### 3. Environment Configuration

Create and configure the `.env` file:

```bash
cp .env.example .env
```

Edit `.env` with your production settings:

```env
# Application Environment
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASSWORD=your_secure_password

# Security
JWT_SECRET=your-very-secure-random-string-here
ENCRYPTION_KEY=another-secure-random-string

# Cache Settings
CACHE_ROUTES=true
ENABLE_COMPRESSION=true
ASSET_CACHE_DURATION=2592000

# CORS Settings (adjust for your domain)
CORS_ORIGINS=https://your-domain.com

# Email Configuration (if using email features)
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls

# Payment Configuration (if using payments)
RAZORPAY_KEY_ID=your_razorpay_key
RAZORPAY_KEY_SECRET=your_razorpay_secret

# File Upload Settings
MAX_UPLOAD_SIZE=10485760
ALLOWED_FILE_TYPES=jpg,jpeg,png,gif,pdf
```

### 4. Database Setup

1. **Create Database**:
   ```sql
   CREATE DATABASE your_database_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Import Schema** (if you have a SQL dump):
   ```bash
   mysql -u username -p your_database_name < database/schema.sql
   ```

3. **Run Migrations** (if available):
   ```bash
   php database/migrate.php
   ```

### 5. Install Dependencies

Install PHP dependencies using Composer:

```bash
composer install --no-dev --optimize-autoloader
```

### 6. Set File Permissions

Set appropriate file permissions:

```bash
# Make storage directories writable
chmod -R 755 storage/
chmod -R 755 public/uploads/

# Make sure the web server can read all files
chown -R www-data:www-data /path/to/project/
# OR for some systems:
chown -R apache:apache /path/to/project/

# Secure sensitive files
chmod 600 .env
chmod -R 700 app/config/
```

### 7. Test the Deployment

1. **Test API Endpoints**:
   ```bash
   curl https://your-domain.com/api/health
   ```

2. **Test Frontend**:
   Visit `https://your-domain.com` in your browser

3. **Test Static Assets**:
   Check if CSS/JS files load properly

4. **Test Admin Panel**:
   Visit `https://your-domain.com/admin`

## Post-Deployment Configuration

### 1. SSL Certificate

Ensure SSL is properly configured:
- Use Let's Encrypt for free certificates
- Configure automatic renewal
- Test SSL configuration with SSL Labs

### 2. Performance Optimization

#### Enable OPcache (PHP)

Add to your PHP configuration:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

#### Configure Caching

- Enable Redis or Memcached if available
- Configure browser caching via headers
- Use CDN for static assets if needed

### 3. Security Hardening

#### Hide PHP Version

Add to `.htaccess`:
```apache
Header unset X-Powered-By
ServerTokens Prod
```

#### Implement Rate Limiting

Consider using fail2ban or similar tools to prevent abuse.

#### Regular Updates

- Keep PHP updated
- Update dependencies regularly
- Monitor security advisories

### 4. Monitoring and Logging

#### Log Rotation

Set up log rotation for application logs:

```bash
# Add to /etc/logrotate.d/riya-collections
/path/to/project/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
```

#### Health Monitoring

Set up monitoring for:
- Application uptime
- Database connectivity
- Disk space usage
- Error rates

### 5. Backup Strategy

#### Database Backups

Create automated database backups:

```bash
#!/bin/bash
# backup-db.sh
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u username -p your_database_name > /backups/db_backup_$DATE.sql
gzip /backups/db_backup_$DATE.sql

# Keep only last 30 days
find /backups -name "db_backup_*.sql.gz" -mtime +30 -delete
```

#### File Backups

Backup uploaded files and configuration:

```bash
#!/bin/bash
# backup-files.sh
DATE=$(date +%Y%m%d_%H%M%S)
tar -czf /backups/files_backup_$DATE.tar.gz \
    /path/to/project/public/uploads/ \
    /path/to/project/.env \
    /path/to/project/storage/

# Keep only last 7 days
find /backups -name "files_backup_*.tar.gz" -mtime +7 -delete
```

## Troubleshooting

### Common Issues

1. **500 Internal Server Error**
   - Check Apache/Nginx error logs
   - Verify file permissions
   - Check `.htaccess` syntax
   - Ensure PHP extensions are installed

2. **Database Connection Errors**
   - Verify database credentials in `.env`
   - Check if database server is accessible
   - Ensure database exists

3. **Static Assets Not Loading**
   - Check file paths in frontend code
   - Verify web server configuration
   - Check file permissions

4. **API Endpoints Returning 404**
   - Ensure mod_rewrite is enabled (Apache)
   - Check URL rewriting configuration
   - Verify `.htaccess` file is present

### Log Files

Check these log files for debugging:

- **Application Logs**: `storage/logs/app-YYYY-MM-DD.log`
- **Error Logs**: `storage/logs/error-YYYY-MM-DD.log`
- **Web Server Logs**: `/var/log/apache2/error.log` or `/var/log/nginx/error.log`
- **PHP Logs**: `/var/log/php_errors.log`

### Performance Issues

If experiencing slow performance:

1. Enable OPcache
2. Optimize database queries
3. Use caching (Redis/Memcached)
4. Enable compression
5. Use a CDN for static assets
6. Optimize images

## Maintenance

### Regular Tasks

- **Weekly**: Check error logs and application health
- **Monthly**: Update dependencies and security patches
- **Quarterly**: Review and optimize database performance
- **Annually**: Review and update SSL certificates

### Updates

When updating the application:

1. Backup database and files
2. Put site in maintenance mode
3. Update files
4. Run any database migrations
5. Clear caches
6. Test functionality
7. Remove maintenance mode

## Support

For deployment issues:

1. Check the application logs in `storage/logs/`
2. Verify configuration in `.env`
3. Test API endpoints using `/api/test`
4. Review web server error logs
5. Consult the main README.md for additional information