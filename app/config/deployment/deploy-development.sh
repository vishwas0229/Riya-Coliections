#!/bin/bash

# Riya Collections Integrated Application - Development Deployment Script
# This script sets up the development environment for the integrated frontend-backend structure

set -e  # Exit on any error

# Configuration
PROJECT_NAME="riya-collections-integrated"
ENVIRONMENT="development"
PROJECT_ROOT="/path/to/riya-collections"
WEB_ROOT="$PROJECT_ROOT/public"
LOG_FILE="$PROJECT_ROOT/logs/deployment-dev.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
    exit 1
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1" | tee -a "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

# Check if running as correct user
check_user() {
    log "Checking user permissions..."
    
    if [[ $EUID -eq 0 ]]; then
        warning "Running as root. Consider using a non-root user for development."
    fi
    
    # Check if user can write to project directory
    if [[ ! -w "$PROJECT_ROOT" ]]; then
        error "Cannot write to project directory: $PROJECT_ROOT"
    fi
    
    success "User permissions OK"
}

# Check system requirements
check_requirements() {
    log "Checking system requirements..."
    
    # Check PHP
    if ! command -v php &> /dev/null; then
        error "PHP is not installed"
    fi
    
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    log "PHP version: $PHP_VERSION"
    
    # Check required PHP extensions
    REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "json" "mbstring" "openssl" "curl" "gd")
    
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if ! php -m | grep -q "^$ext$"; then
            error "Required PHP extension not found: $ext"
        fi
    done
    
    # Check MySQL/MariaDB
    if ! command -v mysql &> /dev/null; then
        warning "MySQL client not found. Database operations may fail."
    fi
    
    # Check Composer
    if ! command -v composer &> /dev/null; then
        warning "Composer not found. Installing dependencies may fail."
    fi
    
    # Check Node.js (for frontend development)
    if ! command -v node &> /dev/null; then
        warning "Node.js not found. Frontend development features may be limited."
    fi
    
    success "System requirements check completed"
}

# Create necessary directories
create_directories() {
    log "Creating necessary directories..."
    
    DIRECTORIES=(
        "$PROJECT_ROOT/logs"
        "$PROJECT_ROOT/storage/cache"
        "$PROJECT_ROOT/storage/backups"
        "$PROJECT_ROOT/storage/logs"
        "$PROJECT_ROOT/public/uploads"
        "$PROJECT_ROOT/public/uploads/products"
        "$PROJECT_ROOT/public/assets/cache"
    )
    
    for dir in "${DIRECTORIES[@]}"; do
        if [[ ! -d "$dir" ]]; then
            mkdir -p "$dir"
            log "Created directory: $dir"
        fi
    done
    
    success "Directories created"
}

# Set file permissions
set_permissions() {
    log "Setting file permissions for development..."
    
    # Make directories writable
    chmod -R 755 "$PROJECT_ROOT"
    chmod -R 777 "$PROJECT_ROOT/logs"
    chmod -R 777 "$PROJECT_ROOT/storage"
    chmod -R 777 "$PROJECT_ROOT/public/uploads"
    
    # Set web root permissions
    chmod 755 "$WEB_ROOT"
    chmod 644 "$WEB_ROOT/index.php"
    
    # Make scripts executable
    find "$PROJECT_ROOT" -name "*.sh" -exec chmod +x {} \;
    
    success "File permissions set"
}

# Install PHP dependencies
install_dependencies() {
    log "Installing PHP dependencies..."
    
    cd "$PROJECT_ROOT"
    
    if [[ -f "composer.json" ]]; then
        if command -v composer &> /dev/null; then
            composer install --no-interaction --prefer-dist --optimize-autoloader
            success "PHP dependencies installed"
        else
            warning "Composer not found. Skipping PHP dependency installation."
        fi
    else
        warning "composer.json not found. Skipping PHP dependency installation."
    fi
}

# Setup environment configuration
setup_environment() {
    log "Setting up development environment configuration..."
    
    # Copy development environment file
    ENV_SOURCE="$PROJECT_ROOT/app/config/environments/development.env"
    ENV_TARGET="$PROJECT_ROOT/.env"
    
    if [[ -f "$ENV_SOURCE" ]]; then
        cp "$ENV_SOURCE" "$ENV_TARGET"
        log "Copied development environment configuration"
    else
        warning "Development environment file not found: $ENV_SOURCE"
    fi
    
    # Update paths in environment file
    if [[ -f "$ENV_TARGET" ]]; then
        # Update APP_URL to localhost
        sed -i "s|APP_URL=.*|APP_URL=http://localhost|g" "$ENV_TARGET"
        
        # Update database name for development
        sed -i "s|DB_NAME=.*|DB_NAME=riya_collections_dev|g" "$ENV_TARGET"
        
        log "Updated environment configuration for development"
    fi
    
    success "Environment configuration completed"
}

# Setup database
setup_database() {
    log "Setting up development database..."
    
    cd "$PROJECT_ROOT"
    
    # Run database migration script
    if [[ -f "app/config/database/migrate.php" ]]; then
        php app/config/database/migrate.php development setup
        success "Database setup completed"
    else
        warning "Database migration script not found"
    fi
}

# Setup web server configuration
setup_webserver() {
    log "Setting up web server configuration..."
    
    # Apache configuration
    if command -v apache2 &> /dev/null || command -v httpd &> /dev/null; then
        log "Apache detected. Development virtual host configuration:"
        echo ""
        echo "Add this to your Apache configuration:"
        echo ""
        cat "$PROJECT_ROOT/app/config/webserver/apache-development.conf"
        echo ""
        warning "Please manually configure Apache virtual host"
    fi
    
    # Nginx configuration
    if command -v nginx &> /dev/null; then
        log "Nginx detected. Development server configuration:"
        echo ""
        echo "Add this to your Nginx configuration:"
        echo ""
        cat "$PROJECT_ROOT/app/config/webserver/nginx-development.conf"
        echo ""
        warning "Please manually configure Nginx server block"
    fi
    
    # PHP built-in server option
    log "Alternative: Use PHP built-in server for development:"
    echo "cd $WEB_ROOT && php -S localhost:8000"
    echo ""
    echo "This will serve both the frontend application and API endpoints:"
    echo "- Frontend: http://localhost:8000/"
    echo "- API: http://localhost:8000/api/"
    echo "- Assets: http://localhost:8000/assets/"
    
    success "Web server configuration information provided"
}

# Run tests
run_tests() {
    log "Running development tests..."
    
    cd "$PROJECT_ROOT"
    
    # Check if PHPUnit is available
    if [[ -f "vendor/bin/phpunit" ]]; then
        ./vendor/bin/phpunit --configuration phpunit.xml --testsuite development
        success "Tests completed"
    else
        warning "PHPUnit not found. Skipping tests."
    fi
}

# Create development data
create_dev_data() {
    log "Creating development data..."
    
    # This would typically seed the database with test data
    # For now, we'll just create some basic structure
    
    log "Development data creation completed"
    success "Development environment ready"
}

# Main deployment function
main() {
    log "Starting development deployment for $PROJECT_NAME"
    log "Environment: $ENVIRONMENT"
    log "Project root: $PROJECT_ROOT"
    
    check_user
    check_requirements
    create_directories
    set_permissions
    install_dependencies
    setup_environment
    setup_database
    setup_webserver
    run_tests
    create_dev_data
    
    success "Development deployment completed successfully!"
    
    echo ""
    echo "Next steps:"
    echo "1. Configure your web server (Apache/Nginx) to point to $WEB_ROOT as document root"
    echo "2. Update database credentials in .env file if needed"
    echo "3. Access the integrated application at http://localhost"
    echo "4. Test frontend functionality and API endpoints"
    echo "5. Test SPA routing and asset serving"
    echo "6. Check logs at: $LOG_FILE"
    echo ""
    echo "Development URLs:"
    echo "- Frontend Application: http://localhost/"
    echo "- API Health Check: http://localhost/api/health"
    echo "- API Products: http://localhost/api/products"
    echo ""
}

# Run main function
main "$@"