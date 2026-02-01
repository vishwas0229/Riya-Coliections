#!/bin/bash

# Riya Collections PHP Backend - Rollback Script
# This script handles rollback to a previous deployment state
# 
# Requirements: 14.3

set -e  # Exit on any error

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
BACKUP_DIR="$PROJECT_ROOT/backups"
LOG_FILE="$PROJECT_ROOT/logs/rollback.log"

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
Riya Collections PHP Backend Rollback Script

Usage: $0 [OPTIONS] BACKUP_NAME

ARGUMENTS:
    BACKUP_NAME     Name of the backup to restore (without path)

OPTIONS:
    -h, --help      Show this help message
    -l, --list      List available backups
    -f, --force     Force rollback without confirmation
    -d, --db-only   Rollback database only
    -F, --files-only Rollback files only
    -v, --verbose   Verbose output
    --dry-run       Show what would be done without executing

EXAMPLES:
    $0 --list
    $0 pre_deploy_backup_2024-01-15_14-30-25
    $0 --db-only pre_deploy_backup_2024-01-15_14-30-25
    $0 --force --files-only backup_name
    $0 --dry-run backup_name

EOF
}

# List available backups
list_backups() {
    log "Available backups in $BACKUP_DIR:"
    
    if [[ ! -d "$BACKUP_DIR" ]]; then
        error "Backup directory does not exist: $BACKUP_DIR"
        return 1
    fi
    
    local backups=($(ls -1t "$BACKUP_DIR" 2>/dev/null | grep -E '^(pre_deploy_|backup_)' || true))
    
    if [[ ${#backups[@]} -eq 0 ]]; then
        warning "No backups found in $BACKUP_DIR"
        return 1
    fi
    
    echo
    printf "%-40s %-20s %-15s %s\n" "BACKUP NAME" "DATE" "SIZE" "STATUS"
    printf "%-40s %-20s %-15s %s\n" "$(printf '%*s' 40 | tr ' ' '-')" "$(printf '%*s' 20 | tr ' ' '-')" "$(printf '%*s' 15 | tr ' ' '-')" "$(printf '%*s' 10 | tr ' ' '-')"
    
    for backup in "${backups[@]}"; do
        local backup_path="$BACKUP_DIR/$backup"
        local date_created=""
        local size=""
        local status="UNKNOWN"
        
        if [[ -d "$backup_path" ]]; then
            # Get creation date
            if [[ -f "$backup_path/backup_manifest.json" ]]; then
                date_created=$(grep -o '"created_at": "[^"]*"' "$backup_path/backup_manifest.json" 2>/dev/null | cut -d'"' -f4 || echo "Unknown")
                status="COMPLETE"
            else
                date_created=$(stat -c %y "$backup_path" 2>/dev/null | cut -d' ' -f1 || echo "Unknown")
                status="PARTIAL"
            fi
            
            # Get size
            size=$(du -sh "$backup_path" 2>/dev/null | cut -f1 || echo "Unknown")
            
            # Check if database backup exists
            if [[ -f "$backup_path/database_backup.sql" ]]; then
                status="${status}+DB"
            fi
            
            # Check if files backup exists
            if [[ -d "$backup_path/files" ]]; then
                status="${status}+FILES"
            fi
        fi
        
        printf "%-40s %-20s %-15s %s\n" "$backup" "$date_created" "$size" "$status"
    done
    
    echo
    log "Use the backup name (first column) with this script to perform rollback"
}

# Validate backup
validate_backup() {
    local backup_name="$1"
    local backup_path="$BACKUP_DIR/$backup_name"
    
    log "Validating backup: $backup_name"
    
    if [[ ! -d "$backup_path" ]]; then
        error "Backup directory does not exist: $backup_path"
        return 1
    fi
    
    local has_db=false
    local has_files=false
    
    # Check database backup
    if [[ -f "$backup_path/database_backup.sql" ]]; then
        local db_size=$(stat -c%s "$backup_path/database_backup.sql" 2>/dev/null || echo "0")
        if [[ $db_size -gt 0 ]]; then
            has_db=true
            success "✓ Database backup found (${db_size} bytes)"
        else
            warning "⚠ Database backup file is empty"
        fi
    else
        warning "⚠ Database backup not found"
    fi
    
    # Check files backup
    if [[ -d "$backup_path/files" ]]; then
        local file_count=$(find "$backup_path/files" -type f 2>/dev/null | wc -l)
        if [[ $file_count -gt 0 ]]; then
            has_files=true
            success "✓ Files backup found ($file_count files)"
        else
            warning "⚠ Files backup directory is empty"
        fi
    else
        warning "⚠ Files backup not found"
    fi
    
    # Check manifest
    if [[ -f "$backup_path/backup_manifest.json" ]]; then
        success "✓ Backup manifest found"
    else
        warning "⚠ Backup manifest not found"
    fi
    
    if [[ "$has_db" == false && "$has_files" == false ]]; then
        error "Backup contains no valid data"
        return 1
    fi
    
    return 0
}

# Rollback database
rollback_database() {
    local backup_path="$1"
    local db_backup_file="$backup_path/database_backup.sql"
    
    log "Rolling back database..."
    
    if [[ ! -f "$db_backup_file" ]]; then
        error "Database backup file not found: $db_backup_file"
        return 1
    fi
    
    if [[ "$DRY_RUN" == true ]]; then
        log "[DRY RUN] Would restore database from: $db_backup_file"
        return 0
    fi
    
    # Load database configuration
    if [[ ! -f "$PROJECT_ROOT/.env" ]]; then
        error ".env file not found. Cannot determine database configuration."
        return 1
    fi
    
    # Extract database configuration from .env
    local db_host=$(grep "^DB_HOST=" "$PROJECT_ROOT/.env" | cut -d'=' -f2 | tr -d '"' || echo "localhost")
    local db_name=$(grep "^DB_NAME=" "$PROJECT_ROOT/.env" | cut -d'=' -f2 | tr -d '"')
    local db_user=$(grep "^DB_USER=" "$PROJECT_ROOT/.env" | cut -d'=' -f2 | tr -d '"')
    local db_password=$(grep "^DB_PASSWORD=" "$PROJECT_ROOT/.env" | cut -d'=' -f2 | tr -d '"')
    
    if [[ -z "$db_name" || -z "$db_user" ]]; then
        error "Database configuration incomplete in .env file"
        return 1
    fi
    
    log "Restoring database: $db_name"
    
    # Create current backup before rollback
    local current_backup_file="$backup_path/current_state_backup_$(date +%Y%m%d_%H%M%S).sql"
    log "Creating backup of current state..."
    
    if command -v mysqldump >/dev/null 2>&1; then
        if [[ -n "$db_password" ]]; then
            mysqldump -h "$db_host" -u "$db_user" -p"$db_password" "$db_name" > "$current_backup_file" 2>/dev/null || {
                warning "Failed to create current state backup"
            }
        else
            mysqldump -h "$db_host" -u "$db_user" "$db_name" > "$current_backup_file" 2>/dev/null || {
                warning "Failed to create current state backup"
            }
        fi
    else
        warning "mysqldump not available. Skipping current state backup."
    fi
    
    # Restore database
    log "Restoring database from backup..."
    
    if command -v mysql >/dev/null 2>&1; then
        if [[ -n "$db_password" ]]; then
            mysql -h "$db_host" -u "$db_user" -p"$db_password" "$db_name" < "$db_backup_file"
        else
            mysql -h "$db_host" -u "$db_user" "$db_name" < "$db_backup_file"
        fi
        
        if [[ $? -eq 0 ]]; then
            success "Database rollback completed successfully"
        else
            error "Database rollback failed"
            return 1
        fi
    else
        error "mysql command not available. Cannot restore database."
        return 1
    fi
}

# Rollback files
rollback_files() {
    local backup_path="$1"
    local files_backup_dir="$backup_path/files"
    
    log "Rolling back files..."
    
    if [[ ! -d "$files_backup_dir" ]]; then
        error "Files backup directory not found: $files_backup_dir"
        return 1
    fi
    
    if [[ "$DRY_RUN" == true ]]; then
        log "[DRY RUN] Would restore files from: $files_backup_dir"
        return 0
    fi
    
    # Create current files backup
    local current_files_backup="$backup_path/current_files_backup_$(date +%Y%m%d_%H%M%S)"
    log "Creating backup of current files..."
    mkdir -p "$current_files_backup"
    
    # Backup current critical files
    local critical_files=(".env" ".htaccess" "config" "uploads")
    
    for file in "${critical_files[@]}"; do
        local source_path="$PROJECT_ROOT/$file"
        local dest_path="$current_files_backup/$file"
        
        if [[ -e "$source_path" ]]; then
            if [[ -d "$source_path" ]]; then
                cp -r "$source_path" "$dest_path" 2>/dev/null || true
            else
                cp "$source_path" "$dest_path" 2>/dev/null || true
            fi
        fi
    done
    
    # Restore files from backup
    log "Restoring files from backup..."
    
    cd "$files_backup_dir"
    find . -type f | while read -r file; do
        local source_file="$files_backup_dir/$file"
        local dest_file="$PROJECT_ROOT/$file"
        local dest_dir=$(dirname "$dest_file")
        
        # Create destination directory if it doesn't exist
        mkdir -p "$dest_dir"
        
        # Copy file
        if cp "$source_file" "$dest_file"; then
            log "Restored: $file"
        else
            warning "Failed to restore: $file"
        fi
    done
    
    success "Files rollback completed"
}

# Parse command line arguments
BACKUP_NAME=""
LIST_BACKUPS=false
FORCE=false
DB_ONLY=false
FILES_ONLY=false
VERBOSE=false
DRY_RUN=false

while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            show_help
            exit 0
            ;;
        -l|--list)
            LIST_BACKUPS=true
            shift
            ;;
        -f|--force)
            FORCE=true
            shift
            ;;
        -d|--db-only)
            DB_ONLY=true
            shift
            ;;
        -F|--files-only)
            FILES_ONLY=true
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
        *)
            if [[ -z "$BACKUP_NAME" ]]; then
                BACKUP_NAME="$1"
            else
                error "Unknown option: $1"
                show_help
                exit 1
            fi
            shift
            ;;
    esac
done

# Create log directory if it doesn't exist
mkdir -p "$(dirname "$LOG_FILE")"

log "Starting rollback process"

# Handle list backups
if [[ "$LIST_BACKUPS" == true ]]; then
    list_backups
    exit 0
fi

# Validate backup name
if [[ -z "$BACKUP_NAME" ]]; then
    error "Backup name is required"
    show_help
    exit 1
fi

# Validate backup
if ! validate_backup "$BACKUP_NAME"; then
    error "Backup validation failed"
    exit 1
fi

BACKUP_PATH="$BACKUP_DIR/$BACKUP_NAME"

# Confirmation (unless forced)
if [[ "$FORCE" != true && "$DRY_RUN" != true ]]; then
    echo
    warning "WARNING: This will rollback your application to a previous state!"
    warning "Current data may be lost. Make sure you have a recent backup."
    echo
    echo "Backup to restore: $BACKUP_NAME"
    echo "Backup path: $BACKUP_PATH"
    echo
    
    if [[ "$DB_ONLY" == true ]]; then
        echo "Operation: Database rollback only"
    elif [[ "$FILES_ONLY" == true ]]; then
        echo "Operation: Files rollback only"
    else
        echo "Operation: Full rollback (database + files)"
    fi
    
    echo
    read -p "Are you sure you want to proceed? (yes/no): " -r
    if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        log "Rollback cancelled by user"
        exit 0
    fi
fi

# Perform rollback
log "Starting rollback of $BACKUP_NAME"

if [[ "$FILES_ONLY" != true ]]; then
    log "Rolling back database..."
    if ! rollback_database "$BACKUP_PATH"; then
        error "Database rollback failed"
        exit 1
    fi
fi

if [[ "$DB_ONLY" != true ]]; then
    log "Rolling back files..."
    if ! rollback_files "$BACKUP_PATH"; then
        error "Files rollback failed"
        exit 1
    fi
fi

# Final verification
if [[ "$DRY_RUN" != true ]]; then
    log "Verifying rollback..."
    
    # Check if critical files exist
    local critical_files=("index.php" ".htaccess" ".env")
    local verification_failed=false
    
    for file in "${critical_files[@]}"; do
        if [[ -f "$PROJECT_ROOT/$file" ]]; then
            success "✓ $file exists"
        else
            error "✗ $file missing after rollback"
            verification_failed=true
        fi
    done
    
    if [[ "$verification_failed" == true ]]; then
        error "Rollback verification failed"
        exit 1
    fi
fi

# Success
if [[ "$DRY_RUN" == true ]]; then
    success "Dry run completed successfully"
    log "No actual changes were made. Run without --dry-run to perform rollback."
else
    success "Rollback completed successfully!"
    
    log "Post-rollback steps:"
    log "1. Test your application functionality"
    log "2. Check error logs for any issues"
    log "3. Verify database connectivity"
    log "4. Test critical user workflows"
    
    warning "Remember to clear any caches and restart services if needed"
fi

log "Rollback process completed"