#!/bin/bash

# Riya Collections Integrated Application - Production Deployment Script
# This script deploys the integrated frontend-backend application to production environment
# IMPORTANT: Review and test thoroughly before using in production

set -e  # Exit on any error

# Configuration
PROJECT_NAME="riya-collections-integrated"
ENVIRONMENT="production"
PROJECT_ROOT="/var/www/riyacollections"
WEB_ROOT="$PROJECT_ROOT/public"
BACKUP_DIR="/var/backups/riyacollections"
LOG_FILE="$PROJECT_ROOT/logs/deployment-prod.log"
DEPLOY_USER="www-data"
DEPLOY_GROUP="www-data"

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

# Check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        error "This script must be run as root for production deployment"
    fi
    log "Running as root - OK"
}

# Pre-deployment checks
pre_deployment_checks() {
    log "Running pre-deployment checks..."
    
    # Check system requirements
    check_system_requirements
    
    # Check disk space
    check_disk_space
    
    # Check existing installation
    check_existing_installation
    
    # Verify configuration files
    verify_configuration
    
    success "Pre-deployment checks completed"
}

# Check system requirements
check_system_requirements() {
    log "Checking system requirements..."
    
    # Check PHP version
    if ! command -v php &> /dev/null; then
        error "PHP is not installed"
    fi
    
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    log "PHP version: $PHP_VERSION"
    
    # Check PHP version is 7.4 or higher
    if ! php -r "exit(version_compare(PHP_VERSION, '7.4.0', '>=') ? 0 : 1);"; then
        error "PHP 7.4 or higher is required"
    fi
    
    # Check required PHP extensions
    REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "json" "mbstring" "openssl" "curl" "gd" "zip" "xml")
    
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if ! php -m | grep -q "^$ext$"; then
            error "Required PHP extension not found: $ext"
        fi
    done
    
    # Check web server
    if ! (systemctl is-active --quiet apache2 || systemctl is-active --quiet nginx); then
        error "No active web server found (Apache or Nginx required)"
    fi
    
    # Check MySQL/MariaDB
    if ! command -v mysql &> /dev/null; then
        error "MySQL client not found"
    fi
    
    # Check Composer
    if ! command -v composer &> /dev/null; then
        error "Composer not found"
    fi
    
    success "System requirements check passed"
}

# Check disk space
check_disk_space() {
    log "Checking disk space..."
    
    REQUIRED_SPACE=1048576  # 1GB in KB
    AVAILABLE_SPACE=$(df "$PROJECT_ROOT" 2>/dev/null | awk 'NR==2 {print $4}' || echo "0")
    
    if [[ $AVAILABLE_SPACE -lt $REQUIRED_SPACE ]]; then
        error "Insufficient disk space. Required: 1GB, Available: $(($AVAILABLE_SPACE/1024))MB"
    fi
    
    success "Disk space check passed"
}

# Check existing installation
check_existing_installation() {
    if [[ -d "$PROJECT_ROOT" ]]; then
        log "Existing installation found at $PROJECT_ROOT"
        
        # Create backup before proceeding
        create_backup
    else
        log "No existing installation found"
    fi
}

# Verify configuration files exist
verify_configuration() {
    log "Verifying configuration files..."
    
    REQUIRED_FILES=(
        "app/config/environments/production.env"
        "app/config/webserver/apache-production.conf"
        "app/config/webserver/nginx-production.conf"
        "app/config/database/setup-production.sql"
        "public/index.php"
        "app/services/AssetServer.php"
        "app/services/SPARouteHandler.php"
    )
    
    for file in "${REQUIRED_FILES[@]}"; do
        if [[ ! -f "$file" ]]; then
            error "Required configuration file not found: $file"
        fi
    done
    
    success "Configuration files verified"
}

# Create backup
create_backup() {
    log "Creating backup of existing installation..."
    
    BACKUP_NAME="riyacollections-backup-$(date +%Y%m%d-%H%M%S)"
    BACKUP_PATH="$BACKUP_DIR/$BACKUP_NAME"
    
    mkdir -p "$BACKUP_DIR"
    
    # Backup files
    if [[ -d "$PROJECT_ROOT" ]]; then
        tar -czf "$BACKUP_PATH-files.tar.gz" -C "$(dirname "$PROJECT_ROOT")" "$(basename "$PROJECT_ROOT")"
        log "Files backed up to: $BACKUP_PATH-files.tar.gz"
    fi
    
    # Backup database
    if [[ -f "$PROJECT_ROOT/.env" ]]; then
        source "$PROJECT_ROOT/.env"
        if [[ -n "$DB_NAME" && -n "$DB_USER" ]]; then
            mysqldump -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" > "$BACKUP_PATH-database.sql"
            log "Database backed up to: $BACKUP_PATH-database.sql"
        fi
    fi
    
    success "Backup created successfully"
}

# Create directories and set permissions
setup_directories() {
    log "Setting up directories and permissions..."
    
    # Create project directory
    mkdir -p "$PROJECT_ROOT"
    
    # Create necessary subdirectories
    DIRECTORIES=(
        "$PROJECT_ROOT/logs"
        "$PROJECT_ROOT/storage/cache"
        "$PROJECT_ROOT/storage/backups"
        "$PROJECT_ROOT/storage/logs"
        "$PROJECT_ROOT/public/uploads"
        "$PROJECT_ROOT/public/uploads/products"
        "$PROJECT_ROOT/public/assets/cache"
        "$BACKUP_DIR"
    )
    
    for dir in "${DIRECTORIES[@]}"; do
        mkdir -p "$dir"
    done
    
    # Set ownership
    chown -R "$DEPLOY_USER:$DEPLOY_GROUP" "$PROJECT_ROOT"
    
    # Set permissions
    chmod -R 755 "$PROJECT_ROOT"
    chmod -R 775 "$PROJECT_ROOT/storage"
    chmod -R 775 "$PROJECT_ROOT/logs"
    chmod -R 775 "$PROJECT_ROOT/public/uploads"
    
    # Set web root permissions
    chmod 755 "$WEB_ROOT"
    chmod 644 "$WEB_ROOT/index.php"
    chmod 644 "$WEB_ROOT/.htaccess" 2>/dev/null || true
    
    # Secure sensitive files
    chmod 600 "$PROJECT_ROOT/.env" 2>/dev/null || true
    
    success "Directories and permissions configured"
}

# Install application files
install_application() {
    log "Installing application files..."
    
    cd "$PROJECT_ROOT"
    
    # Install PHP dependencies
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    
    # Clear any existing cache
    if [[ -d "storage/cache" ]]; then
        rm -rf storage/cache/*
    fi
    
    success "Application files installed"
}

# Setup production environment
setup_environment() {
    log "Setting up production environment..."
    
    # Copy production environment template
    ENV_SOURCE="app/config/environments/production.env"
    ENV_TARGET=".env"
    
    if [[ ! -f "$ENV_TARGET" ]]; then
        cp "$ENV_SOURCE" "$ENV_TARGET"
        log "Production environment template copied"
        
        warning "IMPORTANT: Update .env file with production values before continuing!"
        warning "Required updates:"
        warning "- Database credentials"
        warning "- JWT secrets"
        warning "- Email configuration"
        warning "- Razorpay credentials"
        warning "- Domain URLs"
        
        read -p "Press Enter after updating .env file..."
    fi
    
    # Secure environment file
    chmod 600 "$ENV_TARGET"
    chown "$DEPLOY_USER:$DEPLOY_GROUP" "$ENV_TARGET"
    
    success "Environment configuration completed"
}

# Setup database
setup_database() {
    log "Setting up production database..."
    
    # Run database setup
    php app/config/database/migrate.php production setup
    
    # Run any pending migrations
    php app/config/database/migrate.php production migrate
    
    success "Database setup completed"
}

# Configure web server
configure_webserver() {
    log "Configuring web server..."
    
    # Detect web server
    if systemctl is-active --quiet apache2; then
        configure_apache
    elif systemctl is-active --quiet nginx; then
        configure_nginx
    else
        error "No active web server detected"
    fi
}

# Configure Apache
configure_apache() {
    log "Configuring Apache..."
    
    APACHE_CONFIG="/etc/apache2/sites-available/riyacollections.conf"
    
    # Copy Apache configuration
    cp "app/config/webserver/apache-production.conf" "$APACHE_CONFIG"
    
    # Update paths in configuration
    sed -i "s|/var/www/riyacollections|$PROJECT_ROOT|g" "$APACHE_CONFIG"
    sed -i "s|DocumentRoot.*|DocumentRoot $WEB_ROOT|g" "$APACHE_CONFIG"
    
    # Enable site
    a2ensite riyacollections
    
    # Enable required modules
    a2enmod rewrite
    a2enmod ssl
    a2enmod headers
    a2enmod deflate
    
    # Test configuration
    apache2ctl configtest
    
    # Reload Apache
    systemctl reload apache2
    
    success "Apache configured successfully"
}

# Configure Nginx
configure_nginx() {
    log "Configuring Nginx..."
    
    NGINX_CONFIG="/etc/nginx/sites-available/riyacollections"
    
    # Copy Nginx configuration
    cp "app/config/webserver/nginx-production.conf" "$NGINX_CONFIG"
    
    # Update paths in configuration
    sed -i "s|/var/www/riyacollections|$PROJECT_ROOT|g" "$NGINX_CONFIG"
    sed -i "s|root.*;|root $WEB_ROOT;|g" "$NGINX_CONFIG"
    
    # Enable site
    ln -sf "$NGINX_CONFIG" "/etc/nginx/sites-enabled/"
    
    # Test configuration
    nginx -t
    
    # Reload Nginx
    systemctl reload nginx
    
    success "Nginx configured successfully"
}

# Setup SSL certificates
setup_ssl() {
    log "Setting up SSL certificates..."
    
    # Check if certificates exist
    if [[ -f "/etc/ssl/certs/riyacollections.com.crt" ]]; then
        log "SSL certificates found"
    else
        warning "SSL certificates not found"
        warning "Please install SSL certificates before going live"
        warning "Recommended: Use Let's Encrypt with certbot"
    fi
}

# Setup monitoring and logging
setup_monitoring() {
    log "Setting up monitoring and logging..."
    
    # Setup log rotation
    cat > /etc/logrotate.d/riyacollections << EOF
$PROJECT_ROOT/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 $DEPLOY_USER $DEPLOY_GROUP
    postrotate
        systemctl reload apache2 2>/dev/null || systemctl reload nginx 2>/dev/null || true
    endscript
}
EOF
    
    # Setup cron jobs for maintenance
    cat > /etc/cron.d/riyacollections << EOF
# Riya Collections maintenance tasks
0 2 * * * $DEPLOY_USER cd $PROJECT_ROOT && php app/scripts/cleanup.php >/dev/null 2>&1
0 3 * * 0 $DEPLOY_USER cd $PROJECT_ROOT && php app/scripts/backup.php >/dev/null 2>&1
*/5 * * * * $DEPLOY_USER cd $PROJECT_ROOT && php app/scripts/health-check.php >/dev/null 2>&1
EOF
    
    success "Monitoring and logging configured"
}

# Run production tests
run_production_tests() {
    log "Running production tests..."
    
    # Basic connectivity test
    if command -v curl &> /dev/null; then
        # Test main application
        if curl -f -s "http://localhost" > /dev/null; then
            success "Frontend application connectivity test passed"
        else
            warning "Frontend application connectivity test failed"
        fi
        
        # Test API endpoints
        if curl -f -s "http://localhost/api/health" > /dev/null; then
            success "API connectivity test passed"
        else
            warning "API connectivity test failed"
        fi
    fi
    
    # Database connectivity test
    if php -r "
        require_once 'app/config/environment.php';
        try {
            \$config = getDatabaseConfig();
            \$pdo = new PDO(
                \"mysql:host={\$config['host']};dbname={\$config['database']}\",
                \$config['username'],
                \$config['password']
            );
            echo 'Database connection successful';
        } catch (Exception \$e) {
            echo 'Database connection failed: ' . \$e->getMessage();
            exit(1);
        }
    "; then
        success "Database connectivity test passed"
    else
        error "Database connectivity test failed"
    fi
}

# Post-deployment tasks
post_deployment() {
    log "Running post-deployment tasks..."
    
    # Clear application cache
    if [[ -d "storage/cache" ]]; then
        rm -rf storage/cache/*
        log "Application cache cleared"
    fi
    
    # Warm up cache - test both frontend and API
    curl -s "http://localhost" > /dev/null || true
    curl -s "http://localhost/api/health" > /dev/null || true
    
    # Set final permissions
    chown -R "$DEPLOY_USER:$DEPLOY_GROUP" "$PROJECT_ROOT"
    
    success "Post-deployment tasks completed"
}

# Main deployment function
main() {
    log "Starting production deployment for $PROJECT_NAME"
    log "Environment: $ENVIRONMENT"
    log "Project root: $PROJECT_ROOT"
    
    check_root
    pre_deployment_checks
    setup_directories
    install_application
    setup_environment
    setup_database
    configure_webserver
    setup_ssl
    setup_monitoring
    run_production_tests
    post_deployment
    
    success "Production deployment completed successfully!"
    
    echo ""
    echo "Deployment Summary:"
    echo "- Application deployed to: $PROJECT_ROOT"
    echo "- Web root: $WEB_ROOT"
    echo "- Logs: $LOG_FILE"
    echo "- Backups: $BACKUP_DIR"
    echo ""
    echo "Important next steps:"
    echo "1. Verify SSL certificates are properly installed"
    echo "2. Test frontend application at https://your-domain.com/"
    echo "3. Test API endpoints at https://your-domain.com/api/"
    echo "4. Test SPA routing and asset serving"
    echo "5. Monitor logs for any issues"
    echo "6. Setup external monitoring and alerting"
    echo "7. Configure regular backups"
    echo ""
    warning "Remember to secure your server and keep it updated!"
    warning "Ensure web server document root points to: $WEB_ROOT"
}

# Run main function
main "$@"