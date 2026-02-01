# Riya Collections Integrated Application - Deployment Guide

## Overview

This guide covers the deployment of the Riya Collections integrated frontend-backend application. The integration consolidates the PHP backend and frontend application into a unified structure where the PHP backend serves both API endpoints and frontend assets.

## Architecture

The integrated application follows this structure:

```
riya-collections-integrated/
├── public/                          # Web root directory (DOCUMENT ROOT)
│   ├── index.php                   # Main entry point (enhanced router)
│   ├── .htaccess                   # Apache configuration
│   ├── assets/                     # Frontend static assets
│   │   ├── css/                    # Stylesheets
│   │   ├── js/                     # JavaScript files
│   │   ├── images/                 # Image assets
│   │   └── fonts/                  # Font files
│   ├── pages/                      # Frontend HTML pages
│   └── uploads/                    # User uploaded files
├── app/                            # Application logic (PROTECTED)
│   ├── controllers/                # PHP controllers
│   ├── models/                     # Data models
│   ├── services/                   # Business logic & integration services
│   │   ├── AssetServer.php         # Static asset serving
│   │   ├── SPARouteHandler.php     # Frontend routing
│   │   └── FrontendConfigManager.php # Frontend configuration
│   ├── middleware/                 # Request middleware
│   └── config/                     # Configuration files
│       ├── environments/           # Environment-specific configs
│       ├── webserver/             # Web server configurations
│       └── deployment/            # Deployment scripts
├── storage/                        # Storage directory (PROTECTED)
│   ├── logs/                       # Application logs
│   ├── cache/                      # Cache files
│   └── backups/                    # Database backups
└── logs/                          # Additional logs
```

## Key Integration Components

### 1. Enhanced Router (public/index.php)
- Handles API requests (`/api/*`)
- Serves static assets (`/assets/*`, `/uploads/*`)
- Routes frontend SPA requests to main HTML
- Provides unified entry point for all requests

### 2. AssetServer Class
- Serves static files with proper MIME types
- Implements HTTP caching strategies
- Supports gzip compression
- Provides security validation

### 3. SPARouteHandler Class
- Handles frontend single-page application routing
- Serves main HTML for frontend routes
- Supports browser refresh on frontend routes
- Distinguishes between API and frontend requests

### 4. FrontendConfigManager Class
- Generates environment-specific JavaScript configuration
- Manages API base URLs for different environments
- Handles feature flags and environment variables

## Deployment Environments

### Development Environment

**Requirements:**
- PHP 7.4+ with required extensions
- MySQL/MariaDB
- Apache/Nginx or PHP built-in server

**Quick Setup:**
```bash
# Navigate to project root
cd /path/to/riya-collections

# Run development deployment script
bash app/config/deployment/deploy-development.sh

# Start PHP built-in server (alternative)
cd public && php -S localhost:8000
```

**Access URLs:**
- Frontend: `http://localhost:8000/`
- API: `http://localhost:8000/api/`
- Health Check: `http://localhost:8000/api/health`

### Staging Environment

**Setup:**
```bash
# Run main deployment script
bash deployment/scripts/deploy.sh staging --backup --test

# Verify deployment
curl https://staging.yourdomain.com/deployment/scripts/validate_integration.php?validate=integration
```

### Production Environment

**Requirements:**
- Linux server with root access
- PHP 7.4+ with all required extensions
- MySQL/MariaDB 5.7+
- Apache 2.4+ or Nginx 1.18+
- SSL certificate

**Setup:**
```bash
# Run production deployment script (requires root)
sudo bash app/config/deployment/deploy-production.sh

# Verify deployment
curl https://yourdomain.com/deployment/scripts/verify_deployment.php?verify=deployment
```

## Web Server Configuration

### Apache Configuration

**Virtual Host Example:**
```apache
<VirtualHost *:443>
    ServerName yourdomain.com
    DocumentRoot /var/www/riyacollections/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    # Security Headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    
    # Directory Configuration
    <Directory /var/www/riyacollections/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Protect sensitive directories
    <Directory /var/www/riyacollections/app>
        Require all denied
    </Directory>
    
    <Directory /var/www/riyacollections/storage>
        Require all denied
    </Directory>
    
    # Error and Access Logs
    ErrorLog /var/log/apache2/riyacollections_error.log
    CustomLog /var/log/apache2/riyacollections_access.log combined
</VirtualHost>
```

### Nginx Configuration

**Server Block Example:**
```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /var/www/riyacollections/public;
    index index.php index.html;
    
    # SSL Configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    # Security Headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    
    # Static Assets with Caching
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1M;
        add_header Cache-Control "public, immutable";
    }
    
    # Protect sensitive directories
    location ~ ^/(app|storage|logs)/ {
        deny all;
        return 404;
    }
    
    # PHP Processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Frontend SPA Routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # Logs
    access_log /var/log/nginx/riyacollections_access.log;
    error_log /var/log/nginx/riyacollections_error.log;
}
```

## Environment Configuration

### Environment Files

Create environment-specific configuration files:

**Development (.env):**
```env
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_HOST=localhost
DB_NAME=riya_collections_dev
DB_USER=dev_user
DB_PASSWORD=dev_password

JWT_SECRET=your-development-jwt-secret-key-here
```

**Production (.env):**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_HOST=localhost
DB_NAME=riya_collections_prod
DB_USER=prod_user
DB_PASSWORD=secure-production-password

JWT_SECRET=your-secure-production-jwt-secret-key-here
```

## Database Setup

### Migration Commands

```bash
# Setup database for development
php app/config/database/migrate.php development setup

# Setup database for production
php app/config/database/migrate.php production setup

# Run migrations
php app/config/database/migrate.php production migrate
```

## Deployment Process

### Automated Deployment

1. **Pre-deployment Checks:**
   ```bash
   # Verify system requirements
   php -v
   composer --version
   mysql --version
   ```

2. **Run Deployment Script:**
   ```bash
   # For production
   bash deployment/scripts/deploy.sh production --backup --test
   
   # For staging
   bash deployment/scripts/deploy.sh staging --backup
   ```

3. **Post-deployment Validation:**
   ```bash
   # Health check
   curl https://yourdomain.com/deployment/scripts/health_check.php
   
   # Integration validation
   curl https://yourdomain.com/deployment/scripts/validate_integration.php?validate=integration
   
   # Full verification
   curl https://yourdomain.com/deployment/scripts/verify_deployment.php?verify=deployment
   ```

### Manual Deployment Steps

1. **Upload Files:**
   - Upload all files to server
   - Set document root to `public/` directory

2. **Set Permissions:**
   ```bash
   chmod -R 755 /var/www/riyacollections
   chmod -R 775 /var/www/riyacollections/storage
   chmod -R 775 /var/www/riyacollections/logs
   chmod -R 775 /var/www/riyacollections/public/uploads
   chmod 600 /var/www/riyacollections/.env
   ```

3. **Install Dependencies:**
   ```bash
   cd /var/www/riyacollections
   composer install --no-dev --optimize-autoloader
   ```

4. **Configure Environment:**
   - Copy appropriate environment file to `.env`
   - Update database credentials and other settings

5. **Setup Database:**
   - Create database and user
   - Run migration scripts

6. **Configure Web Server:**
   - Point document root to `public/` directory
   - Configure virtual host/server block
   - Enable SSL

## Testing the Integration

### Functional Tests

1. **Frontend Application:**
   - Visit main URL and verify application loads
   - Test navigation between pages
   - Verify browser refresh works on frontend routes

2. **API Endpoints:**
   - Test `/api/health` returns JSON response
   - Test `/api/products` returns product data
   - Test authentication endpoints

3. **Static Assets:**
   - Verify CSS files load with correct MIME type
   - Test JavaScript files execute properly
   - Check images display correctly

4. **Integration Features:**
   - Test frontend forms that call backend APIs
   - Verify file upload functionality
   - Test user authentication flows

### Performance Tests

```bash
# Test page load times
curl -w "@curl-format.txt" -o /dev/null -s https://yourdomain.com/

# Test API response times
curl -w "@curl-format.txt" -o /dev/null -s https://yourdomain.com/api/health

# Test static asset caching
curl -I https://yourdomain.com/assets/css/style.css
```

### Security Tests

```bash
# Verify sensitive files are protected
curl -I https://yourdomain.com/.env          # Should return 403/404
curl -I https://yourdomain.com/app/           # Should return 403/404
curl -I https://yourdomain.com/storage/       # Should return 403/404

# Test security headers
curl -I https://yourdomain.com/
```

## Monitoring and Maintenance

### Log Files

- **Application Logs:** `logs/app.log`
- **Error Logs:** `logs/error.log`
- **Security Logs:** `logs/security.log`
- **Web Server Logs:** `/var/log/apache2/` or `/var/log/nginx/`

### Health Monitoring

Set up automated health checks:

```bash
# Add to crontab
*/5 * * * * curl -f https://yourdomain.com/api/health > /dev/null 2>&1 || echo "Health check failed" | mail admin@yourdomain.com
```

### Backup Strategy

```bash
# Database backup
mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql

# File backup
tar -czf backup_files_$(date +%Y%m%d_%H%M%S).tar.gz /var/www/riyacollections
```

## Troubleshooting

### Common Issues

1. **404 Errors on Frontend Routes:**
   - Check web server configuration
   - Verify .htaccess file exists in public/
   - Ensure mod_rewrite is enabled (Apache)

2. **Static Assets Not Loading:**
   - Check file permissions
   - Verify AssetServer class is working
   - Check web server error logs

3. **API Endpoints Not Working:**
   - Verify database connection
   - Check PHP error logs
   - Test database credentials

4. **Frontend Not Connecting to API:**
   - Check frontend configuration
   - Verify API base URLs
   - Test CORS settings

### Debug Mode

Enable debug mode for troubleshooting:

```env
APP_DEBUG=true
```

Then check logs for detailed error information.

## Security Considerations

1. **File Permissions:**
   - Ensure app/ and storage/ directories are not web-accessible
   - Set restrictive permissions on .env file

2. **Web Server Security:**
   - Use HTTPS in production
   - Configure security headers
   - Disable directory browsing

3. **Database Security:**
   - Use strong passwords
   - Limit database user permissions
   - Enable SSL for database connections

4. **Regular Updates:**
   - Keep PHP and extensions updated
   - Update Composer dependencies regularly
   - Monitor security advisories

## Support and Maintenance

### Regular Tasks

- Monitor log files for errors
- Update SSL certificates before expiration
- Perform regular database backups
- Update dependencies and security patches
- Monitor server resource usage

### Performance Optimization

- Enable PHP OPcache
- Configure proper caching headers
- Optimize database queries
- Use CDN for static assets
- Monitor and optimize server resources

This deployment guide provides comprehensive instructions for deploying the integrated Riya Collections application across different environments while maintaining security, performance, and reliability standards.