# Riya Collections - Integrated Application

This is the integrated version of Riya Collections that combines the PHP backend and frontend into a unified, clean structure for seamless deployment and operation.

## Project Structure

```
project/
├── public/                          # Web root directory (ONLY entry point)
│   ├── index.php                   # Unified entry point (handles API + assets + SPA)
│   ├── .htaccess                   # Apache configuration
│   ├── index.html                  # Frontend main HTML
│   ├── assets/                     # Frontend static assets
│   │   ├── css/                    # Stylesheets
│   │   ├── js/                     # JavaScript files
│   │   └── images/                 # Image assets
│   ├── pages/                      # Frontend HTML pages
│   └── uploads/                    # User uploaded files
├── app/                            # Application logic (SINGLE source)
│   ├── controllers/                # PHP controllers
│   ├── models/                     # Data models
│   ├── services/                   # Business logic services
│   ├── middleware/                 # Request middleware
│   ├── config/                     # Configuration files
│   └── utils/                      # Utility classes
├── storage/                        # Storage directory
│   ├── logs/                       # Application logs
│   ├── cache/                      # Cache files
│   └── backups/                    # Database backups
├── database/                       # Database related files
│   └── migrations/                 # Database migrations
├── tests/                          # All test files (consolidated)
├── deployment/                     # Deployment scripts and guides
├── docs/                          # Documentation (single location)
├── composer.json                   # Single dependency file
└── .env.example                    # Single environment template
```

## Features

### Unified Entry Point
- Single `public/index.php` handles all requests (API, assets, frontend)
- Automatic request type detection and routing
- No duplicate files or configurations
- Clean, maintainable structure

### Enhanced Routing
- API requests routed to PHP controllers in `app/controllers/`
- Static assets served with proper caching headers
- Frontend SPA routing support for seamless navigation
- Proper MIME type handling for all asset types

### Security Features
- Path traversal protection
- CORS handling for API requests
- Security headers (X-Content-Type-Options, X-Frame-Options, etc.)
- Input validation and sanitization
- JWT-based authentication

### Performance Optimizations
- Route caching for improved performance
- Asset compression (gzip)
- HTTP caching with ETags and Last-Modified headers
- Request/response logging and monitoring

### Clean Architecture
- Single source of truth for all components
- No duplicate files or directories
- Consolidated test suite
- Unified configuration management

## Installation

1. **Clone or extract the project**
   ```bash
   git clone <repository-url>
   cd riya-collections
   ```

2. **Configure environment variables**
   ```bash
   cp .env.example .env
   # Edit .env with your database and other settings
   ```

3. **Install PHP dependencies**
   ```bash
   composer install
   ```

4. **Set up database**
   ```bash
   # Create database and run migrations from database/migrations/
   # Import the SQL files in order: 001, 002, 003, 004
   ```

5. **Configure web server**
   - Point document root to `public/` directory
   - Ensure Apache mod_rewrite is enabled (or configure Nginx)
   - The `.htaccess` file in `public/` handles URL rewriting

6. **Set permissions**
   ```bash
   chmod -R 755 storage/
   chmod -R 755 public/uploads/
   ```

## Development

### Running Tests
```bash
# Run all tests
./vendor/bin/phpunit tests/

# Run specific test
./vendor/bin/phpunit tests/AuthServiceTest.php
```

### Project Structure Guidelines
- **public/**: Web-accessible files only (entry point, assets, uploads)
- **app/**: All PHP application logic (controllers, models, services)
- **tests/**: All test files using PHPUnit
- **storage/**: Non-web-accessible storage (logs, cache, backups)
- **database/**: Database migrations and schema
- **deployment/**: Deployment scripts and documentation
- **docs/**: Project documentation

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