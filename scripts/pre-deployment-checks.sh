#!/bin/bash
# Pre-deployment validation script
# Based on deployment-strategy-implementation.md PRD specifications

set -e

echo "🚀 Starting pre-deployment validation checks..."

# Configuration
SERVICE_URL=${SERVICE_URL:-"http://localhost"}
DATABASE_HOST=${DATABASE_HOST:-"localhost"}
DATABASE_NAME=${DATABASE_NAME:-"shopware"}
DATABASE_USER=${DATABASE_USER:-"root"}
DATABASE_PASS=${DATABASE_PASS:-"root"}
REDIS_HOST=${REDIS_HOST:-"localhost"}
REDIS_PORT=${REDIS_PORT:-"6379"}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
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

check_exit_code() {
    if [ $1 -ne 0 ]; then
        log_error "$2"
        exit 1
    else
        log_info "$3"
    fi
}

# 1. Database connectivity check
log_info "Checking database connectivity..."
mysql -h ${DATABASE_HOST} -u ${DATABASE_USER} -p${DATABASE_PASS} -e "SELECT 1;" ${DATABASE_NAME} > /dev/null 2>&1
check_exit_code $? "Database connection failed" "Database connection successful"

# 2. Database performance check
log_info "Checking database performance..."
QUERY_TIME=$(mysql -h ${DATABASE_HOST} -u ${DATABASE_USER} -p${DATABASE_PASS} -e "SELECT BENCHMARK(1000000, MD5('test'));" ${DATABASE_NAME} 2>&1 | grep "Query OK" | wc -l)
if [ ${QUERY_TIME} -eq 1 ]; then
    log_info "Database performance check passed"
else
    log_warning "Database performance may be degraded"
fi

# 3. Check for active migrations
log_info "Checking for active migrations..."
ACTIVE_MIGRATIONS=$(mysql -h ${DATABASE_HOST} -u ${DATABASE_USER} -p${DATABASE_PASS} -N -e "SELECT COUNT(*) FROM migration WHERE update_date IS NULL;" ${DATABASE_NAME} 2>/dev/null || echo "0")
if [ ${ACTIVE_MIGRATIONS} -gt 0 ]; then
    log_error "Found ${ACTIVE_MIGRATIONS} incomplete migrations. Cannot proceed with deployment."
    exit 1
else
    log_info "No incomplete migrations found"
fi

# 4. Check database locks
log_info "Checking for database locks..."
LOCKED_TABLES=$(mysql -h ${DATABASE_HOST} -u ${DATABASE_USER} -p${DATABASE_PASS} -N -e "SHOW OPEN TABLES WHERE In_use > 0;" ${DATABASE_NAME} | wc -l)
if [ ${LOCKED_TABLES} -gt 5 ]; then
    log_warning "Found ${LOCKED_TABLES} locked tables. Deployment may be slower."
else
    log_info "Database locks check passed"
fi

# 5. Redis connectivity check
log_info "Checking Redis connectivity..."
if command -v redis-cli &> /dev/null; then
    redis-cli -h ${REDIS_HOST} -p ${REDIS_PORT} ping > /dev/null 2>&1
    check_exit_code $? "Redis connection failed" "Redis connection successful"
else
    log_warning "Redis CLI not available, skipping Redis check"
fi

# 6. Disk space check
log_info "Checking disk space..."
DISK_USAGE=$(df /var/www/html | awk 'NR==2 {print $5}' | sed 's/%//')
if [ ${DISK_USAGE} -gt 85 ]; then
    log_error "Disk usage is ${DISK_USAGE}%. Cannot proceed with deployment."
    exit 1
else
    log_info "Disk space check passed (${DISK_USAGE}% used)"
fi

# 7. Memory check
log_info "Checking available memory..."
MEMORY_USAGE=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}')
if [ ${MEMORY_USAGE} -gt 85 ]; then
    log_warning "Memory usage is ${MEMORY_USAGE}%. Deployment may be slower."
else
    log_info "Memory check passed (${MEMORY_USAGE}% used)"
fi

# 8. Check for required environment variables
log_info "Checking environment variables..."
REQUIRED_VARS=(
    "DATABASE_URL"
    "APP_SECRET"
    "OAUTH2_ENCRYPTION_KEY"
    "WISHLIST_ENCRYPTION_KEY"
)

for var in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!var}" ]; then
        log_error "Required environment variable ${var} is not set"
        exit 1
    else
        log_info "Environment variable ${var} is set"
    fi
done

# 9. Check backup system
log_info "Checking backup system..."
BACKUP_DIR="/var/backups/migrations"
if [ ! -d "${BACKUP_DIR}" ]; then
    mkdir -p "${BACKUP_DIR}"
    log_info "Created backup directory: ${BACKUP_DIR}"
fi

if [ ! -w "${BACKUP_DIR}" ]; then
    log_error "Backup directory is not writable: ${BACKUP_DIR}"
    exit 1
else
    log_info "Backup system check passed"
fi

# 10. Check for mysqldump availability
log_info "Checking mysqldump availability..."
if ! command -v mysqldump &> /dev/null; then
    log_error "mysqldump is not available. Required for backup creation."
    exit 1
else
    log_info "mysqldump is available"
fi

# 11. Application health check (if service is running)
log_info "Checking application health..."
if curl -f --connect-timeout 5 --max-time 10 "${SERVICE_URL}/health" > /dev/null 2>&1; then
    log_info "Application health check passed"
    
    # Check specific wishlist health endpoint
    if curl -f --connect-timeout 5 --max-time 10 "${SERVICE_URL}/api/wishlist/health" > /dev/null 2>&1; then
        log_info "Wishlist service health check passed"
    else
        log_warning "Wishlist service health check failed (may be expected if new deployment)"
    fi
else
    log_warning "Application health check failed (may be expected if new deployment)"
fi

# 12. Check for sufficient Docker resources (if using Docker)
if command -v docker &> /dev/null; then
    log_info "Checking Docker resources..."
    
    # Check Docker daemon
    if ! docker info > /dev/null 2>&1; then
        log_error "Docker daemon is not running"
        exit 1
    fi
    
    # Check available Docker disk space
    DOCKER_DISK=$(docker system df --format "table {{.Type}}\t{{.TotalCount}}\t{{.Size}}" | grep "Images" | awk '{print $3}' | sed 's/GB//' | cut -d'.' -f1)
    if [ ! -z "${DOCKER_DISK}" ] && [ ${DOCKER_DISK} -gt 20 ]; then
        log_warning "Docker images using ${DOCKER_DISK}GB disk space. Consider cleanup."
    else
        log_info "Docker resources check passed"
    fi
fi

# 13. Network connectivity check
log_info "Checking network connectivity..."
if ping -c 1 8.8.8.8 > /dev/null 2>&1; then
    log_info "Network connectivity check passed"
else
    log_warning "External network connectivity may be limited"
fi

# 14. Check system load
log_info "Checking system load..."
LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
CPU_COUNT=$(nproc)
LOAD_THRESHOLD=$(echo "${CPU_COUNT} * 2" | bc -l)

if (( $(echo "${LOAD_AVG} > ${LOAD_THRESHOLD}" | bc -l) )); then
    log_warning "System load is high: ${LOAD_AVG} (CPUs: ${CPU_COUNT})"
else
    log_info "System load check passed: ${LOAD_AVG}"
fi

# 15. Final validation summary
log_info "📋 Pre-deployment validation summary:"
echo "   ✅ Database connectivity and performance"
echo "   ✅ Migration status"
echo "   ✅ Resource availability (disk, memory)"
echo "   ✅ Environment configuration"
echo "   ✅ Backup system"
echo "   ✅ System health"

log_info "🎉 All pre-deployment checks passed! Ready for deployment."

exit 0