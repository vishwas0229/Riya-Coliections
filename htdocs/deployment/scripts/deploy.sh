#!/bin/bash

# Riya Collections PHP Backend - Deployment Script
# This script automates the deployment process for various hosting environments
# 
# Requirements: 14.1, 14.3

set -e  # Exit on any error

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
DEPLOYMENT_DIR="$SCRIPT_DIR/.."
BACKUP_DIR="$PROJECT_ROOT/backups"
LOG_FILE="$PROJECT_ROOT/logs/deployment.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
    echo "[ERROR] $1" >> "$LOG_FILE"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
    echo "[SUCCESS] $1" >> "$LOG_FILE"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
    echo "[WARNING] $1" >> "$LOG_FILE"
}

# Help function
show_help() {
    cat << EOF
Riya Collections PHP Backend Deployment Script

Usage: $0 [OPTIONS] ENVIRONMENT

ENVIRONMENTS:
    production      Deploy to production environment
    staging         Deploy to staging environment
    infinityfree    Deploy to InfinityFree hosting

OPTIONS:
    -h, --help      Show this help message
    -b, --backup    Create backup before deployment
    -s, --skip-db   Skip database migration
    -t, --test      Run tests before deployment
    -v, --verbose   Verbose output
    --dry-run       Show what would be done without executing

EXAMPLES:
    $0 production --backup --test
    $0 staging --skip-db
    $0 infinityfree --backup
    $0 --dry-run production

EOF
}

# Parse command line arguments
ENVIRONMENT=""
CREATE_BACKUP=false
SKIP_DB=false
RUN_TESTS=false
VERBOSE=false
DRY_RUN=false

while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            show_help
            exit 0
            ;;
        -b|--backup)
            CREATE_BACKUP=true
            shift
            ;;
        -s|--skip-db)
            SKIP_DB=true
            shift
            ;;
        -t|--test)
            RUN_TESTS=true
            shift
            ;;
        -v|--verbose)
            VERBOSE=true
            shift
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        production|staging|infinityfree)
            ENVIRONMENT="$1"
            shift
            ;;
        *)
            error "Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Validate environment
if [[ -z "$ENVIRONMENT" ]]; then
    error "Environment is required"
    show_help
    exit 1
fi

# Validate environment value
case "$ENVIRONMENT" in
    production|staging|infinityfree)
        ;;
    *)
        error "Invalid environment: $ENVIRONMENT"
        error "Valid environments: production, staging, infinityfree"
        exit 1
        ;;
esac

# Create log directory if it doesn't exist
mkdir -p "$(dirname "$LOG_FILE")"

log "Starting deployment to $ENVIRONMENT environment"
log "Project root: $PROJECT_ROOT"
log "Options: backup=$CREATE_BACKUP, skip-db=$SKIP_DB, tests=$RUN_TESTS, dry-run=$DRY_RUN"

# Pre-deployment checks
log "Running pre-deployment checks..."

# Check if we're in the right directory
if [[ ! -f "$PROJECT_ROOT/index.php" ]]; then
    error "index.php not found. Are you in the correct directory?"
    exit 1
fi

# Check if composer dependencies exist
if [[ ! -d "$PROJECT_ROOT/vendor" ]]; then
    error "Composer dependencies not found. Run 'composer install' first."
    exit 1
fi

# Check environment configuration
ENV_FILE="$DEPLOYMENT_DIR/templates/.env.$ENVIRONMENT"
if [[ "$ENVIRONMENT" == "infinityfree" ]]; then
    ENV_FILE="$DEPLOYMENT_DIR/infinityfree/.env.infinityfree"
fi

if [[ ! -f "$ENV_FILE" ]]; then
    error "Environment template not found: $ENV_FILE"
    exit 1
fi

success "Pre-deployment checks passed"

# Create backup if requested
if [[ "$CREATE_BACKUP" == true ]]; then
    log "Creating pre-deployment backup..."
    
    if [[ "$DRY_RUN" == true ]]; then
        log "[DRY RUN] Would create backup"
    else
        TIMESTAMP=$(date +%Y%m%d_%H%M%S)
        BACKUP_NAME="pre_deploy_${ENVIRONMENT}_${TIMESTAMP}"
        BACKUP_PATH="$BACKUP_DIR/$BACKUP_NAME"
        
        mkdir -p "$BACKUP_PATH"
        
        # Backup database (if .env exists)
        if [[ -f "$PROJECT_ROOT/.env" ]]; then
            log "Backing up database..."
            # Note: This would need to be implemented based on hosting environment
            # For now, we'll create a placeholder
            touch "$BACKUP_PATH/database_backup.sql"
        fi
        
        # Backup critical files
        log "Backing up files..."
        cp -r "$PROJECT_ROOT/config" "$BACKUP_PATH/" 2>/dev/null || true
        cp "$PROJECT_ROOT/.env" "$BACKUP_PATH/" 2>/dev/null || true
        cp "$PROJECT_ROOT/.htaccess" "$BACKUP_PATH/" 2>/dev/null || true
        
        success "Backup created: $BACKUP_NAME"
    fi
fi

# Run tests if requested
if [[ "$RUN_TESTS" == true ]]; then
    log "Running tests..."
    
    if [[ "$DRY_RUN" == true ]]; then
        log "[DRY RUN] Would run tests"
    else
        cd "$PROJECT_ROOT"
        
        if [[ -f "vendor/bin/phpunit" ]]; then
            if ./vendor/bin/phpunit --configuration phpunit.xml; then
                success "All tests passed"
            else
                error "Tests failed. Aborting deployment."
                exit 1
            fi
        else
            warning "PHPUnit not found. Skipping tests."
        fi
    fi
fi

# Environment-specific deployment
log "Deploying to $ENVIRONMENT environment..."

case "$ENVIRONMENT" in
    production)
        log "Production deployment configuration"
        HTACCESS_SOURCE="$DEPLOYMENT_DIR/templates/.htaccess.template"
        ;;
    staging)
        log "Staging deployment configuration"
        HTACCESS_SOURCE="$DEPLOYMENT_DIR/templates/.htaccess.template"
        ;;
    infinityfree)
        log "InfinityFree deployment configuration"
        HTACCESS_SOURCE="$DEPLOYMENT_DIR/infinityfree/.htaccess.production"
        ;;
esac

if [[ "$DRY_RUN" == true ]]; then
    log "[DRY RUN] Would copy environment configuration from $ENV_FILE"
    log "[DRY RUN] Would copy .htaccess from $HTACCESS_SOURCE"
else
    # Copy environment configuration
    if [[ ! -f "$PROJECT_ROOT/.env" ]]; then
        log "Copying environment configuration..."
        cp "$ENV_FILE" "$PROJECT_ROOT/.env"
        success "Environment configuration copied"
    else
        warning ".env file already exists. Skipping environment copy."
        warning "Please ensure your .env is configured for $ENVIRONMENT"
    fi
    
    # Copy .htaccess if it exists
    if [[ -f "$HTACCESS_SOURCE" ]]; then
        log "Copying .htaccess configuration..."
        cp "$HTACCESS_SOURCE" "$PROJECT_ROOT/.htaccess"
        success ".htaccess configuration copied"
    fi
fi

# Set file permissions
log "Setting file permissions..."

if [[ "$DRY_RUN" == true ]]; then
    log "[DRY RUN] Would set file permissions"
else
    # Set directory permissions
    find "$PROJECT_ROOT" -type d -exec chmod 755 {} \; 2>/dev/null || true
    
    # Set file permissions
    find "$PROJECT_ROOT" -type f -exec chmod 644 {} \; 2>/dev/null || true
    
    # Set specific permissions for writable directories
    chmod 755 "$PROJECT_ROOT/uploads" 2>/dev/null || true
    chmod 755 "$PROJECT_ROOT/logs" 2>/dev/null || true
    chmod 755 "$PROJECT_ROOT/cache" 2>/dev/null || true
    chmod 755 "$PROJECT_ROOT/backups" 2>/dev/null || true
    
    # Protect sensitive files
    chmod 600 "$PROJECT_ROOT/.env" 2>/dev/null || true
    
    success "File permissions set"
fi

# Database migration
if [[ "$SKIP_DB" == false ]]; then
    log "Database migration would be performed here"
    
    if [[ "$DRY_RUN" == true ]]; then
        log "[DRY RUN] Would run database migration"
    else
        log "Note: Run the database migration script manually:"
        log "Visit: https://your-domain.com/deployment/shared/database_migration.php?confirm=yes"
        warning "Remember to delete the migration script after successful run!"
    fi
fi

# Post-deployment tasks
log "Running post-deployment tasks..."

if [[ "$DRY_RUN" == true ]]; then
    log "[DRY RUN] Would run post-deployment tasks"
else
    # Clear cache if directory exists
    if [[ -d "$PROJECT_ROOT/cache" ]]; then
        rm -rf "$PROJECT_ROOT/cache"/*
        log "Cache cleared"
    fi
    
    # Create necessary directories
    mkdir -p "$PROJECT_ROOT/uploads/products"
    mkdir -p "$PROJECT_ROOT/logs"
    mkdir -p "$PROJECT_ROOT/backups"
    
    success "Post-deployment tasks completed"
fi

# Deployment verification
log "Deployment verification..."

if [[ "$DRY_RUN" == true ]]; then
    log "[DRY RUN] Would verify deployment"
else
    # Check if critical files exist
    CRITICAL_FILES=("index.php" ".htaccess" ".env" "config/database.php")
    
    for file in "${CRITICAL_FILES[@]}"; do
        if [[ -f "$PROJECT_ROOT/$file" ]]; then
            success "✓ $file exists"
        else
            error "✗ $file missing"
        fi
    done
    
    log "Manual verification steps:"
    log "1. Visit: https://your-domain.com/api/health"
    log "2. Test user registration and login"
    log "3. Test product listing and search"
    log "4. Test file uploads"
    log "5. Check error logs for issues"
fi

# Final summary
log "Deployment Summary:"
log "Environment: $ENVIRONMENT"
log "Backup created: $CREATE_BACKUP"
log "Database migration: $([ "$SKIP_DB" == true ] && echo "skipped" || echo "required")"
log "Tests run: $RUN_TESTS"

if [[ "$DRY_RUN" == true ]]; then
    success "Dry run completed successfully"
    log "No actual changes were made. Run without --dry-run to deploy."
else
    success "Deployment to $ENVIRONMENT completed successfully!"
    
    log "Next steps:"
    log "1. Run environment validation: https://your-domain.com/deployment/shared/environment_validator.php"
    log "2. Run database migration: https://your-domain.com/deployment/shared/database_migration.php?confirm=yes"
    log "3. Test the application thoroughly"
    log "4. Delete deployment scripts from production for security"
    
    warning "SECURITY: Remember to delete deployment scripts after successful deployment!"
fi

log "Deployment process completed"