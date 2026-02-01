# Riya Collections - Configuration Guide

This directory contains environment-specific configuration files for the Riya Collections application. These configurations support development, staging, and production environments with proper web server configurations and database setup scripts.

## Directory Structure

```
app/config/
├── environments/           # Environment-specific configuration files
│   ├── development.env    # Development environment settings
│   ├── staging.env        # Staging environment settings
│   └── production.env     # Production environment settings
├── webserver/             # Web server configuration files
│   ├── apache-development.conf    # Apache config for development
│   ├── apache-production.conf     # Apache config for production
│   ├── nginx-development.conf     # Nginx config for development
│   └── nginx-production.conf      # Nginx config for production
├── database/              # Database setup and migration scripts
│   ├── setup-development.sql      # Development database setup
│   ├── setup-production.sql       # Production database setup
│   └── migrate.php               # Database migration script
├── deployment/            # Deployment scripts
│   ├── deploy-development.sh      # Development deployment script
│   └── deploy-production.sh       # Production deployment script
└── README.md             # This file
```

## Environment Configuration Files

### Development Environment (`environments/development.env`)

**Purpose**: Local development with debugging enabled and relaxed security settings.

**Key Features**:
- Debug mode enabled
- Verbose logging
- Relaxed CORS settings
- Lower password hashing rounds for faster development
- Test email configuration (Mailtrap)
- Razorpay test mode
- Disabled caching for faster development cycles

**Usage**:
```bash
cp app/config/environments/development.env .env
```

### Staging Environment (`environments/staging.env`)

**Purpose**: Pre-production testing environment that closely mirrors production.

**Key Features**:
- Production-like security settings
- Test payment gateway credentials
- Staging-specific branding
- Moderate caching settings
- Enhanced logging for debugging
- SSL enabled but with relaxed settings

**Usage**:
```bash
cp app/config/environments/staging.env .env
# Update database credentials and other staging-specific values
```

### Production Environment (`environments/production.env`)

**Purpose**: Live production environment with maximum security and performance.

**Key Features**:
- Maximum security settings
- Production payment gateway credentials
- Aggressive caching and optimization
- Minimal logging (errors only)
- SSL/HTTPS enforced
- Rate limiting enabled
- All debugging features disabled

**Usage**:
```bash
cp app/config/environments/production.env .env
# IMPORTANT: Update all placeholder values before deployment
```

**Critical Production Updates Required**:
- Database credentials (`DB_*` variables)
- JWT secrets (`JWT_SECRET`, `JWT_REFRESH_SECRET`)
- Email configuration (`SMTP_*` variables)
- Razorpay live credentials (`RAZORPAY_*` variables)
- Domain URLs (`APP_URL`, `WEBSITE_URL`, etc.)
- Session secret (`SESSION_SECRET`)
- Admin email (`ERROR_REPORTING_EMAIL`)

## Web Server Configuration Files

### Apache Configuration

#### Development (`webserver/apache-development.conf`)
- Permissive CORS settings
- Detailed error reporting
- No caching for faster development
- Generous file upload limits
- Debug-friendly settings

#### Production (`webserver/apache-production.conf`)
- Maximum security headers
- SSL/HTTPS enforcement
- Aggressive caching for static assets
- Rate limiting and security filters
- Optimized for performance

**Installation**:
```bash
# Copy configuration to Apache sites directory
sudo cp app/config/webserver/apache-production.conf /etc/apache2/sites-available/riyacollections.conf

# Update paths in the configuration file
sudo sed -i 's|/var/www/riyacollections|/path/to/your/project|g' /etc/apache2/sites-available/riyacollections.conf

# Enable the site
sudo a2ensite riyacollections

# Enable required modules
sudo a2enmod rewrite ssl headers deflate

# Test and reload
sudo apache2ctl configtest
sudo systemctl reload apache2
```

### Nginx Configuration

#### Development (`webserver/nginx-development.conf`)
- No rate limiting
- Verbose logging
- No caching
- Permissive CORS
- Development-friendly timeouts

#### Production (`webserver/nginx-production.conf`)
- Comprehensive rate limiting
- Security headers
- Aggressive caching
- SSL optimization
- Performance tuning

**Installation**:
```bash
# Copy configuration to Nginx sites directory
sudo cp app/config/webserver/nginx-production.conf /etc/nginx/sites-available/riyacollections

# Update paths in the configuration file
sudo sed -i 's|/var/www/riyacollections|/path/to/your/project|g' /etc/nginx/sites-available/riyacollections

# Enable the site
sudo ln -s /etc/nginx/sites-available/riyacollections /etc/nginx/sites-enabled/

# Test and reload
sudo nginx -t
sudo systemctl reload nginx
```

## Database Setup Scripts

### Development Database (`database/setup-development.sql`)

**Features**:
- Creates development database with sample data
- Includes test users and products
- Optimized for development workflow
- Includes development-specific indexes

**Usage**:
```bash
mysql -u root -p < app/config/database/setup-development.sql
```

### Production Database (`database/setup-production.sql`)

**Features**:
- Production-optimized database schema
- Security-focused user permissions
- Performance indexes
- Audit logging tables
- Production MySQL settings

**Usage**:
```bash
# Review and customize before running
mysql -u root -p < app/config/database/setup-production.sql
```

### Database Migration Script (`database/migrate.php`)

**Features**:
- Environment-aware migrations
- Rollback support
- Migration status tracking
- Batch processing

**Usage**:
```bash
# Setup database for environment
php app/config/database/migrate.php development setup

# Run migrations
php app/config/database/migrate.php production migrate

# Check migration status
php app/config/database/migrate.php staging status

# Rollback last batch
php app/config/database/migrate.php development rollback
```

## Deployment Scripts

### Development Deployment (`deployment/deploy-development.sh`)

**Features**:
- Automated development environment setup
- System requirements checking
- Dependency installation
- Database setup
- Web server configuration guidance

**Usage**:
```bash
chmod +x app/config/deployment/deploy-development.sh
sudo ./app/config/deployment/deploy-development.sh
```

### Production Deployment (`deployment/deploy-production.sh`)

**Features**:
- Production-ready deployment automation
- Pre-deployment validation
- Automatic backups
- Security hardening
- Post-deployment testing

**Usage**:
```bash
chmod +x app/config/deployment/deploy-production.sh
sudo ./app/config/deployment/deploy-production.sh
```

## Security Considerations

### Development
- Use only for local development
- Never expose development environment to the internet
- Regularly update development dependencies

### Staging
- Use separate database from production
- Test payment gateway credentials only
- Restrict access to authorized personnel
- Regular security updates

### Production
- **Change all default passwords and secrets**
- Use strong, unique passwords
- Enable SSL/HTTPS with valid certificates
- Regular security audits and updates
- Monitor logs for suspicious activity
- Implement proper backup strategies

## Environment Variables Reference

### Application Settings
- `APP_NAME`: Application name
- `APP_ENV`: Environment (development/staging/production)
- `APP_DEBUG`: Enable/disable debug mode
- `APP_URL`: Application base URL

### Database Settings
- `DB_HOST`: Database host
- `DB_PORT`: Database port
- `DB_NAME`: Database name
- `DB_USER`: Database username
- `DB_PASSWORD`: Database password

### Security Settings
- `JWT_SECRET`: JWT signing secret (minimum 32 characters)
- `JWT_REFRESH_SECRET`: JWT refresh token secret
- `SESSION_SECRET`: Session encryption secret
- `BCRYPT_SALT_ROUNDS`: Password hashing rounds

### Email Settings
- `SMTP_HOST`: SMTP server host
- `SMTP_PORT`: SMTP server port
- `SMTP_USER`: SMTP username
- `SMTP_PASSWORD`: SMTP password

### Payment Settings
- `RAZORPAY_KEY_ID`: Razorpay key ID
- `RAZORPAY_KEY_SECRET`: Razorpay secret key
- `RAZORPAY_WEBHOOK_SECRET`: Webhook secret

## Troubleshooting

### Common Issues

1. **Permission Errors**
   ```bash
   sudo chown -R www-data:www-data /path/to/project
   sudo chmod -R 755 /path/to/project
   sudo chmod -R 775 /path/to/project/storage
   ```

2. **Database Connection Issues**
   - Verify database credentials in `.env`
   - Check database server is running
   - Ensure database exists and user has proper permissions

3. **Web Server Issues**
   - Check web server error logs
   - Verify configuration syntax
   - Ensure required modules are enabled

4. **SSL Certificate Issues**
   - Verify certificate files exist and are readable
   - Check certificate validity and chain
   - Ensure proper file permissions

### Log Locations
- Application logs: `logs/app.log`
- Deployment logs: `logs/deployment-{env}.log`
- Web server logs: `/var/log/apache2/` or `/var/log/nginx/`
- System logs: `/var/log/syslog`

## Best Practices

1. **Environment Separation**
   - Use separate databases for each environment
   - Never use production credentials in development/staging
   - Implement proper access controls

2. **Security**
   - Regularly update all dependencies
   - Use strong, unique passwords and secrets
   - Implement proper backup and recovery procedures
   - Monitor application and server logs

3. **Performance**
   - Enable caching in production
   - Optimize database queries
   - Use CDN for static assets
   - Monitor application performance

4. **Maintenance**
   - Regular backups
   - Keep software updated
   - Monitor disk space and resources
   - Test disaster recovery procedures

## Support

For issues related to configuration:
1. Check the troubleshooting section above
2. Review application logs
3. Verify environment-specific settings
4. Consult the main project documentation

Remember to always test configuration changes in development/staging before applying to production.