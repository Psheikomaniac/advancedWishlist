#!/bin/bash
# Automated Backup and Restore Script for AdvancedWishlist
# Based on deployment-strategy-implementation.md PRD specifications

set -e

# Configuration
BACKUP_TYPE=${1:-"incremental"}  # incremental, full, database, application
BACKUP_DIR="/var/backups/advanced-wishlist"
S3_BUCKET="${S3_BACKUP_BUCKET:-advanced-wishlist-backups}"
RETENTION_DAYS=${RETENTION_DAYS:-30}
MYSQL_HOST=${DATABASE_HOST:-"localhost"}
MYSQL_USER=${DATABASE_USER:-"root"}
MYSQL_PASS=${DATABASE_PASS:-"root"}
MYSQL_DB=${DATABASE_NAME:-"shopware"}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_backup() {
    echo -e "${BLUE}[BACKUP]${NC} $1"
}

check_exit_code() {
    if [ $1 -ne 0 ]; then
        log_error "$2"
        exit 1
    else
        log_info "$3"
    fi
}

# Create backup directory
create_backup_dir() {
    if [ ! -d "${BACKUP_DIR}" ]; then
        mkdir -p "${BACKUP_DIR}"
        log_info "Created backup directory: ${BACKUP_DIR}"
    fi
    
    # Create subdirectories
    mkdir -p "${BACKUP_DIR}/database"
    mkdir -p "${BACKUP_DIR}/application"
    mkdir -p "${BACKUP_DIR}/full"
    mkdir -p "${BACKUP_DIR}/logs"
}

# Database backup functions
create_database_backup() {
    local backup_type=$1
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_file="${BACKUP_DIR}/database/${backup_type}_${timestamp}.sql"
    
    log_backup "Creating ${backup_type} database backup..."
    
    case ${backup_type} in
        "full")
            mysqldump --single-transaction --routines --triggers \
                      --master-data=2 --flush-logs \
                      -h ${MYSQL_HOST} -u ${MYSQL_USER} -p${MYSQL_PASS} ${MYSQL_DB} \
                      > "${backup_file}"
            ;;
        "incremental")
            # For incremental, we'll use binary logs
            mysqldump --single-transaction --routines --triggers \
                      --master-data=2 --flush-logs \
                      --where="updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)" \
                      -h ${MYSQL_HOST} -u ${MYSQL_USER} -p${MYSQL_PASS} ${MYSQL_DB} \
                      > "${backup_file}"
            ;;
        "schema-only")
            mysqldump --no-data --routines --triggers \
                      -h ${MYSQL_HOST} -u ${MYSQL_USER} -p${MYSQL_PASS} ${MYSQL_DB} \
                      > "${backup_file}"
            ;;
    esac
    
    check_exit_code $? "Database backup failed" "Database backup created: ${backup_file}"
    
    # Compress backup
    gzip "${backup_file}"
    backup_file="${backup_file}.gz"
    
    # Upload to S3 if configured
    if command -v aws &> /dev/null && [ ! -z "${S3_BUCKET}" ]; then
        log_info "Uploading backup to S3..."
        aws s3 cp "${backup_file}" "s3://${S3_BUCKET}/database/"
        check_exit_code $? "S3 upload failed" "Backup uploaded to S3"
    fi
    
    # Log backup info
    echo "$(date): ${backup_type} database backup created: $(basename ${backup_file})" >> "${BACKUP_DIR}/logs/backup.log"
    
    return 0
}

# Application backup functions
create_application_backup() {
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_file="${BACKUP_DIR}/application/app_${timestamp}.tar.gz"
    
    log_backup "Creating application backup..."
    
    # Backup application files (excluding cache and logs)
    tar -czf "${backup_file}" \
        --exclude='/var/www/html/var/cache/*' \
        --exclude='/var/www/html/var/log/*' \
        --exclude='/var/www/html/vendor' \
        --exclude='/var/www/html/node_modules' \
        /var/www/html
    
    check_exit_code $? "Application backup failed" "Application backup created: ${backup_file}"
    
    # Upload to S3 if configured
    if command -v aws &> /dev/null && [ ! -z "${S3_BUCKET}" ]; then
        log_info "Uploading application backup to S3..."
        aws s3 cp "${backup_file}" "s3://${S3_BUCKET}/application/"
        check_exit_code $? "S3 upload failed" "Application backup uploaded to S3"
    fi
    
    # Log backup info
    echo "$(date): Application backup created: $(basename ${backup_file})" >> "${BACKUP_DIR}/logs/backup.log"
    
    return 0
}

# Full system backup
create_full_backup() {
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_dir="${BACKUP_DIR}/full/full_${timestamp}"
    
    log_backup "Creating full system backup..."
    
    mkdir -p "${backup_dir}"
    
    # Database backup
    log_info "Backing up database..."
    mysqldump --single-transaction --routines --triggers \
              -h ${MYSQL_HOST} -u ${MYSQL_USER} -p${MYSQL_PASS} ${MYSQL_DB} \
              > "${backup_dir}/database.sql"
    
    # Application files backup
    log_info "Backing up application files..."
    tar -czf "${backup_dir}/application.tar.gz" \
        --exclude='/var/www/html/var/cache/*' \
        --exclude='/var/www/html/var/log/*' \
        --exclude='/var/www/html/vendor' \
        /var/www/html
    
    # Configuration backup
    log_info "Backing up configuration..."
    cp -r /etc/nginx "${backup_dir}/" 2>/dev/null || true
    cp -r /etc/mysql "${backup_dir}/" 2>/dev/null || true
    
    # Docker configuration if present
    if [ -f "/var/www/html/docker-compose.yml" ]; then
        cp /var/www/html/docker-compose*.yml "${backup_dir}/" 2>/dev/null || true
    fi
    
    # Compress full backup
    tar -czf "${backup_dir}.tar.gz" -C "${BACKUP_DIR}/full" "$(basename ${backup_dir})"
    rm -rf "${backup_dir}"
    
    check_exit_code $? "Full backup failed" "Full backup created: ${backup_dir}.tar.gz"
    
    # Upload to S3 if configured
    if command -v aws &> /dev/null && [ ! -z "${S3_BUCKET}" ]; then
        log_info "Uploading full backup to S3..."
        aws s3 cp "${backup_dir}.tar.gz" "s3://${S3_BUCKET}/full/"
        check_exit_code $? "S3 upload failed" "Full backup uploaded to S3"
    fi
    
    # Log backup info
    echo "$(date): Full backup created: $(basename ${backup_dir}).tar.gz" >> "${BACKUP_DIR}/logs/backup.log"
    
    return 0
}

# Restore functions
restore_database() {
    local backup_file=$1
    
    if [ -z "${backup_file}" ]; then
        log_error "No backup file specified for database restore"
        return 1
    fi
    
    if [ ! -f "${backup_file}" ]; then
        log_error "Backup file not found: ${backup_file}"
        return 1
    fi
    
    log_warning "⚠️  DESTRUCTIVE OPERATION: This will replace the current database!"
    read -p "Are you sure you want to restore the database? (yes/no): " confirm
    
    if [ "${confirm}" != "yes" ]; then
        log_info "Database restore cancelled"
        return 0
    fi
    
    log_backup "Restoring database from: ${backup_file}"
    
    # Create backup of current database before restore
    local current_backup="${BACKUP_DIR}/database/pre_restore_$(date +%Y%m%d_%H%M%S).sql"
    log_info "Creating backup of current database..."
    mysqldump --single-transaction -h ${MYSQL_HOST} -u ${MYSQL_USER} -p${MYSQL_PASS} ${MYSQL_DB} > "${current_backup}"
    gzip "${current_backup}"
    
    # Restore from backup
    if [[ "${backup_file}" == *.gz ]]; then
        gunzip -c "${backup_file}" | mysql -h ${MYSQL_HOST} -u ${MYSQL_USER} -p${MYSQL_PASS} ${MYSQL_DB}
    else
        mysql -h ${MYSQL_HOST} -u ${MYSQL_USER} -p${MYSQL_PASS} ${MYSQL_DB} < "${backup_file}"
    fi
    
    check_exit_code $? "Database restore failed" "Database restored successfully"
    
    # Log restore info
    echo "$(date): Database restored from: $(basename ${backup_file})" >> "${BACKUP_DIR}/logs/restore.log"
    
    return 0
}

restore_application() {
    local backup_file=$1
    
    if [ -z "${backup_file}" ]; then
        log_error "No backup file specified for application restore"
        return 1
    fi
    
    if [ ! -f "${backup_file}" ]; then
        log_error "Backup file not found: ${backup_file}"
        return 1
    fi
    
    log_warning "⚠️  DESTRUCTIVE OPERATION: This will replace application files!"
    read -p "Are you sure you want to restore the application? (yes/no): " confirm
    
    if [ "${confirm}" != "yes" ]; then
        log_info "Application restore cancelled"
        return 0
    fi
    
    log_backup "Restoring application from: ${backup_file}"
    
    # Create backup of current application
    local current_backup="${BACKUP_DIR}/application/pre_restore_$(date +%Y%m%d_%H%M%S).tar.gz"
    log_info "Creating backup of current application..."
    tar -czf "${current_backup}" /var/www/html
    
    # Stop application services
    log_info "Stopping application services..."
    docker-compose down 2>/dev/null || systemctl stop apache2 nginx php8.4-fpm 2>/dev/null || true
    
    # Restore application files
    tar -xzf "${backup_file}" -C /
    
    # Set proper permissions
    chown -R www-data:www-data /var/www/html
    chmod -R 755 /var/www/html
    
    # Restart application services
    log_info "Starting application services..."
    docker-compose up -d 2>/dev/null || systemctl start apache2 nginx php8.4-fpm 2>/dev/null || true
    
    check_exit_code $? "Application restore failed" "Application restored successfully"
    
    # Log restore info
    echo "$(date): Application restored from: $(basename ${backup_file})" >> "${BACKUP_DIR}/logs/restore.log"
    
    return 0
}

# Cleanup old backups
cleanup_old_backups() {
    log_info "Cleaning up backups older than ${RETENTION_DAYS} days..."
    
    find "${BACKUP_DIR}/database" -name "*.sql.gz" -mtime +${RETENTION_DAYS} -delete 2>/dev/null || true
    find "${BACKUP_DIR}/application" -name "*.tar.gz" -mtime +${RETENTION_DAYS} -delete 2>/dev/null || true
    find "${BACKUP_DIR}/full" -name "*.tar.gz" -mtime +${RETENTION_DAYS} -delete 2>/dev/null || true
    
    # Cleanup S3 backups if configured
    if command -v aws &> /dev/null && [ ! -z "${S3_BUCKET}" ]; then
        log_info "Cleaning up S3 backups older than ${RETENTION_DAYS} days..."
        
        local cutoff_date=$(date -d "${RETENTION_DAYS} days ago" +%Y-%m-%d)
        
        aws s3 ls "s3://${S3_BUCKET}/database/" | while read -r line; do
            local file_date=$(echo $line | awk '{print $1}')
            local file_name=$(echo $line | awk '{print $4}')
            
            if [[ "${file_date}" < "${cutoff_date}" ]]; then
                aws s3 rm "s3://${S3_BUCKET}/database/${file_name}"
                log_info "Deleted old S3 backup: ${file_name}"
            fi
        done
    fi
    
    log_info "Cleanup completed"
}

# List available backups
list_backups() {
    log_info "📋 Available backups:"
    
    echo ""
    echo "Database Backups:"
    ls -lh "${BACKUP_DIR}/database/" 2>/dev/null | grep -v "^total" || echo "  No database backups found"
    
    echo ""
    echo "Application Backups:"
    ls -lh "${BACKUP_DIR}/application/" 2>/dev/null | grep -v "^total" || echo "  No application backups found"
    
    echo ""
    echo "Full Backups:"
    ls -lh "${BACKUP_DIR}/full/" 2>/dev/null | grep -v "^total" || echo "  No full backups found"
    
    if command -v aws &> /dev/null && [ ! -z "${S3_BUCKET}" ]; then
        echo ""
        echo "S3 Backups:"
        aws s3 ls "s3://${S3_BUCKET}/" --recursive 2>/dev/null || echo "  No S3 backups found or S3 not configured"
    fi
}

# Main function
main() {
    create_backup_dir
    
    case "${BACKUP_TYPE}" in
        "incremental")
            create_database_backup "incremental"
            ;;
        "full")
            create_full_backup
            ;;
        "database")
            create_database_backup "full"
            ;;
        "application")
            create_application_backup
            ;;
        "cleanup")
            cleanup_old_backups
            ;;
        "list")
            list_backups
            ;;
        "restore-database")
            restore_database "${2}"
            ;;
        "restore-application")
            restore_application "${2}"
            ;;
        *)
            echo "Usage: $0 {incremental|full|database|application|cleanup|list|restore-database|restore-application} [backup_file]"
            echo ""
            echo "Backup types:"
            echo "  incremental     - Create incremental database backup"
            echo "  full            - Create full system backup"
            echo "  database        - Create full database backup"
            echo "  application     - Create application files backup"
            echo "  cleanup         - Remove old backups"
            echo "  list            - List available backups"
            echo "  restore-database <file>    - Restore database from backup"
            echo "  restore-application <file> - Restore application from backup"
            exit 1
            ;;
    esac
}

# Run main function
main "$@"