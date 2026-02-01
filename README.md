# Riya Collections - Integrated Application

This is the integrated version of Riya Collections that combines the PHP backend and frontend into a unified structure for seamless deployment and operation.

## Project Structure

```
riya-collections-integrated/
├── public/                          # Web root directory
│   ├── index.php                   # Main entry point (enhanced)
│   ├── .htaccess                   # Apache configuration
│   ├── index.html                  # Frontend main HTML
│   ├── assets/                     # Frontend static assets
│   │   ├── css/                    # Stylesheets
│   │   ├── js/                     # JavaScript files
│   │   └── images/                 # Image assets
│   ├── pages/                      # Frontend HTML pages
│   └── uploads/                    # User uploaded files
├── app/                            # Application logic
│   ├── controllers/                # PHP controllers
│   ├── models/                     # Data models
│   ├── services/                   # Business logic
│   ├── middleware/                 # Request middleware
│   ├── config/                     # Configuration files
│   └── utils/                      # Utility classes
├── storage/                        # Storage directory
│   ├── logs/                       # Application logs
│   ├── cache/                      # Cache files
│   └── backups/                    # Database backups
├── database/                       # Database related files
│   └── migrations/                 # Database migrations
├── tests/                          # Test files
├── deployment/                     # Deployment scripts
└── docs/                          # Documentation
```

## Features

### Unified Entry Point
- Single `public/index.php` handles all requests
- API requests routed to PHP controllers
- Static assets served with proper caching
- Frontend SPA routing support

### Enhanced Routing
- Automatic detection of request types (API, assets, frontend)
- Proper MIME type handling for all asset types
- HTTP caching with ETags and Last-Modified headers
- Compression support for better performance

### Security Features
- Path traversal protection
- CORS handling for API requests
- Security headers (X-Content-Type-Options, X-Frame-Options, etc.)
- Input validation and sanitization

### Performance Optimizations
- Route caching for improved performance
- Asset compression (gzip)
- Proper cache headers for static assets
- Request/response logging and monitoring

## Installation

1. **Clone or extract the integrated project**
2. **Configure environment variables**
   ```bash
   cp .env.example .env
   # Edit .env with your database and other settings
   ```

3. **Install PHP dependencies** (if composer.json exists)
   ```bash
   composer install
   ```

4. **Set up web server**
   - Point document root to `public/` directory
   - Ensure Apache mod_rewrite is enabled
   - The `.htaccess` file handles URL rewriting

5. **Configure database**
   - Create MySQL database
   - Import database schema if available
   - Update database credentials in `.env`

## API Endpoints

All API endpoints are available under `/api/` prefix:

- **Authentication**: `/api/auth/*`
- **Products**: `/api/products/*`
- **Orders**: `/api/orders/*`
- **Admin**: `/api/admin/*`
- **Health**: `/api/health/*`

## Frontend Routes

Frontend single-page application routes are handled automatically:

- `/` - Home page
- `/products` - Product listing
- `/product/{id}` - Product details
- `/cart` - Shopping cart
- `/checkout` - Checkout process
- `/profile` - User profile
- `/admin` - Admin dashboard

## Configuration

### Environment Variables

Key environment variables in `.env`:

```env
# Application
APP_ENV=development
APP_DEBUG=true

# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=riya_collections
DB_USER=root
DB_PASSWORD=

# Cache
CACHE_ROUTES=true
ENABLE_COMPRESSION=true
ASSET_CACHE_DURATION=86400

# Security
JWT_SECRET=your-secret-key
CORS_ORIGINS=http://localhost:3000,http://localhost:8000
```

### Frontend Configuration

Frontend configuration is in `public/assets/js/config.js`:

- API base URL automatically set to `/api`
- Feature flags for enabling/disabling functionality
- UI constants and breakpoints
- Error and success messages

## Development

### Local Development Setup

1. **Using PHP built-in server**:
   ```bash
   cd public
   php -S localhost:8000
   ```

2. **Using Apache/Nginx**:
   - Configure virtual host pointing to `public/` directory
   - Ensure URL rewriting is enabled

### File Structure Guidelines

- **Controllers**: Place in `app/controllers/`
- **Models**: Place in `app/models/`
- **Services**: Place in `app/services/`
- **Middleware**: Place in `app/middleware/`
- **Frontend Assets**: Place in `public/assets/`
- **Static Files**: Place in `public/`

### Adding New Routes

API routes are defined in `public/index.php` in the `initializeRoutes()` method:

```php
$this->routes['GET']['/api/new-endpoint'] = [
    'handler' => ['ControllerName', 'methodName'],
    'middleware' => ['AuthMiddleware'] // optional
];
```

## Deployment

### Production Deployment

1. **Upload files** to web server
2. **Configure web server** to point to `public/` directory
3. **Set environment variables** for production
4. **Install dependencies**: `composer install --no-dev --optimize-autoloader`
5. **Set proper file permissions**:
   ```bash
   chmod -R 755 storage/
   chmod -R 755 public/uploads/
   ```

### Apache Configuration

The included `.htaccess` file handles:
- URL rewriting for API and frontend routes
- Static asset serving with caching
- Security headers and restrictions
- CORS handling for API requests

### Nginx Configuration

For Nginx, use a configuration similar to:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/project/public;
    index index.php index.html;

    # Handle API requests
    location /api/ {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Handle static assets
    location ~* \.(css|js|png|jpg|jpeg|gif|svg|webp|woff|woff2|ttf|otf|eot|ico)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Handle PHP files
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Handle frontend routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

## Troubleshooting

### Common Issues

1. **404 errors for API endpoints**
   - Check if mod_rewrite is enabled (Apache)
   - Verify `.htaccess` file is present and readable
   - Check web server configuration

2. **Static assets not loading**
   - Verify file paths in frontend code
   - Check file permissions
   - Ensure assets are in `public/assets/` directory

3. **Database connection errors**
   - Verify database credentials in `.env`
   - Check if database server is running
   - Ensure database exists and is accessible

4. **CORS errors**
   - Check CORS configuration in `.htaccess`
   - Verify allowed origins match your frontend URL
   - Ensure preflight requests are handled properly

### Logging

Application logs are stored in `storage/logs/`:
- `app-YYYY-MM-DD.log` - General application logs
- `error-YYYY-MM-DD.log` - Error logs
- `security-YYYY-MM-DD.log` - Security-related logs

## Support

For issues and questions:
1. Check the logs in `storage/logs/`
2. Verify configuration in `.env`
3. Test API endpoints using the built-in test interface at `/api/test`
4. Review the documentation in `docs/` directory