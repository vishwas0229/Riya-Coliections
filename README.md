# Riya Collections

E-commerce application with PHP backend and integrated frontend for seamless deployment and operation.

## Quick Start

```bash
# Install dependencies
composer install

# Configure environment
cp .env.example .env
# Edit .env with your database credentials

# Setup database
# Import migrations from database/migrations/ in order (001, 002, 003, 004)

# Start development server
cd public && php -S localhost:8000
```

Access at: `http://localhost:8000`

## Project Structure

See [STRUCTURE.md](STRUCTURE.md) for complete directory structure and file organization.

## Features

- **Unified Entry Point**: Single `public/index.php` handles API, assets, and frontend routing
- **JWT Authentication**: Secure user authentication with token-based system
- **Payment Integration**: Razorpay payment gateway integration
- **Image Management**: Product image upload and optimization
- **Admin Dashboard**: Complete admin panel for managing products and orders
- **Email Notifications**: Automated email system for orders and updates
- **Real-time Updates**: Polling system for order status updates
- **Security**: Input validation, CORS, rate limiting, and security headers
- **Performance**: Route caching, asset compression, HTTP caching

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite (or Nginx)
- Composer
- Required PHP extensions: pdo, pdo_mysql, json, curl, gd, mbstring, openssl

## Testing

```bash
# Run all tests
./vendor/bin/phpunit tests/

# Run specific test
./vendor/bin/phpunit tests/AuthServiceTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

## API Endpoints

All API endpoints under `/api/` prefix:

- `/api/auth/*` - Authentication (login, register, logout)
- `/api/products/*` - Product management
- `/api/orders/*` - Order management
- `/api/payments/*` - Payment processing
- `/api/admin/*` - Admin operations
- `/api/health` - Health check

## Frontend Routes

- `/` - Home page
- `/products` - Product listing
- `/product/{id}` - Product details
- `/cart` - Shopping cart
- `/checkout` - Checkout
- `/profile` - User profile
- `/admin` - Admin dashboard

## Configuration

Key environment variables in `.env`:

```env
# Application
APP_ENV=development
APP_DEBUG=true

# Database
DB_HOST=localhost
DB_NAME=riya_collections
DB_USER=root
DB_PASSWORD=

# Security
JWT_SECRET=your-secret-key

# Email (optional)
MAIL_HOST=smtp.gmail.com
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password

# Payment (optional)
RAZORPAY_KEY_ID=your-key
RAZORPAY_KEY_SECRET=your-secret
```

## Deployment

See [deployment/README.md](deployment/README.md) for complete deployment instructions.

### Quick Deploy

```bash
# Production deployment
./deployment/scripts/deploy.sh production --backup --test

# Staging deployment
./deployment/scripts/deploy.sh staging
```

### Manual Deployment

1. Upload files to web server
2. Point document root to `public/` directory
3. Configure `.env` file
4. Install dependencies: `composer install --no-dev --optimize-autoloader`
5. Import database migrations
6. Set permissions: `chmod -R 755 storage/ public/uploads/`

## Troubleshooting

### Common Issues

1. **404 errors**: Check mod_rewrite is enabled and `.htaccess` exists
2. **Database errors**: Verify credentials in `.env`
3. **Permission errors**: Set `chmod -R 755 storage/ public/uploads/`
4. **CORS errors**: Check CORS configuration in `.htaccess`

### Logs

Application logs in `storage/logs/`:
- `app-YYYY-MM-DD.log` - Application logs
- `error-YYYY-MM-DD.log` - Error logs
- `security-YYYY-MM-DD.log` - Security logs

## Documentation

- [STRUCTURE.md](STRUCTURE.md) - Project structure
- [deployment/](deployment/) - Deployment guides
- [docs/](docs/) - Implementation documentation

## License

See [LICENSE](LICENSE) file for details.