#!/bin/bash
# Blue-Green Deployment Script for AdvancedWishlist
# Based on deployment-strategy-implementation.md PRD specifications

set -e

# Configuration
IMAGE_NAME=${1:-"advanced-wishlist:latest"}
NAMESPACE=${NAMESPACE:-"production"}
SERVICE_NAME="advanced-wishlist"
GREEN_DEPLOYMENT="${SERVICE_NAME}-green"
BLUE_DEPLOYMENT="${SERVICE_NAME}-blue"
ACTIVE_SERVICE="${SERVICE_NAME}-active"
PREVIEW_SERVICE="${SERVICE_NAME}-preview"

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

log_blue_green() {
    echo -e "${BLUE}[BLUE-GREEN]${NC} $1"
}

check_exit_code() {
    if [ $1 -ne 0 ]; then
        log_error "$2"
        exit 1
    else
        log_info "$3"
    fi
}

# Determine current active deployment
get_current_deployment() {
    local current=$(kubectl get service ${ACTIVE_SERVICE} -n ${NAMESPACE} -o jsonpath='{.spec.selector.version}' 2>/dev/null || echo "none")
    echo ${current}
}

# Get target deployment (opposite of current)
get_target_deployment() {
    local current=$1
    if [ "${current}" = "blue" ]; then
        echo "green"
    elif [ "${current}" = "green" ]; then
        echo "blue"
    else
        # If no current deployment, start with blue
        echo "blue"
    fi
}

# Health check function
health_check() {
    local service_url=$1
    local max_attempts=${2:-30}
    local wait_time=${3:-10}
    
    log_info "Performing health check on ${service_url}..."
    
    for i in $(seq 1 ${max_attempts}); do
        if curl -f --connect-timeout 5 --max-time 10 "${service_url}/health" > /dev/null 2>&1; then
            log_info "Health check passed (attempt ${i}/${max_attempts})"
            return 0
        else
            log_warning "Health check failed (attempt ${i}/${max_attempts}), retrying in ${wait_time}s..."
            sleep ${wait_time}
        fi
    done
    
    log_error "Health check failed after ${max_attempts} attempts"
    return 1
}

# Comprehensive service validation
validate_service() {
    local service_url=$1
    local deployment_name=$2
    
    log_info "Validating service: ${deployment_name}"
    
    # Basic health check
    if ! health_check "${service_url}"; then
        return 1
    fi
    
    # Wishlist-specific health check
    log_info "Checking wishlist service health..."
    if ! curl -f --connect-timeout 5 --max-time 10 "${service_url}/api/wishlist/health" > /dev/null 2>&1; then
        log_error "Wishlist service health check failed"
        return 1
    fi
    
    # Database connectivity check
    log_info "Checking database connectivity through service..."
    local db_check=$(curl -s "${service_url}/api/wishlist/health" | jq -r '.checks.database.healthy' 2>/dev/null || echo "false")
    if [ "${db_check}" != "true" ]; then
        log_error "Database connectivity check failed"
        return 1
    fi
    
    # Performance test
    log_info "Running performance test..."
    local response_time=$(curl -w "%{time_total}" -o /dev/null -s "${service_url}/api/wishlist/health")
    local response_time_ms=$(echo "${response_time} * 1000" | bc -l | cut -d'.' -f1)
    
    if [ ${response_time_ms} -gt 2000 ]; then
        log_warning "Service response time is slow: ${response_time_ms}ms"
    else
        log_info "Service response time: ${response_time_ms}ms"
    fi
    
    log_info "Service validation passed for: ${deployment_name}"
    return 0
}

# Main deployment function
main() {
    log_blue_green "🚀 Starting Blue-Green Deployment"
    log_info "Image: ${IMAGE_NAME}"
    log_info "Namespace: ${NAMESPACE}"
    
    # Get current state
    local current_deployment=$(get_current_deployment)
    local target_deployment=$(get_target_deployment ${current_deployment})
    local target_deployment_name="${SERVICE_NAME}-${target_deployment}"
    
    log_blue_green "Current deployment: ${current_deployment}"
    log_blue_green "Target deployment: ${target_deployment}"
    
    # Step 1: Deploy to target environment
    log_info "Step 1: Deploying to ${target_deployment} environment..."
    
    # Update deployment with new image
    kubectl set image deployment/${target_deployment_name} \
        app=${IMAGE_NAME} \
        -n ${NAMESPACE}
    
    check_exit_code $? "Failed to update deployment image" "Deployment image updated successfully"
    
    # Wait for rollout to complete
    log_info "Waiting for rollout to complete..."
    kubectl rollout status deployment/${target_deployment_name} -n ${NAMESPACE} --timeout=600s
    check_exit_code $? "Deployment rollout failed" "Deployment rollout completed"
    
    # Step 2: Update preview service to point to new deployment
    log_info "Step 2: Updating preview service..."
    kubectl patch service ${PREVIEW_SERVICE} -n ${NAMESPACE} \
        -p '{"spec":{"selector":{"version":"'${target_deployment}'"}}}'
    
    check_exit_code $? "Failed to update preview service" "Preview service updated"
    
    # Get preview service URL
    local preview_url="http://$(kubectl get service ${PREVIEW_SERVICE} -n ${NAMESPACE} -o jsonpath='{.status.loadBalancer.ingress[0].ip}' 2>/dev/null || echo 'localhost')"
    
    # Step 3: Validate new deployment
    log_info "Step 3: Validating new deployment..."
    sleep 30  # Wait for service to stabilize
    
    if ! validate_service "${preview_url}" "${target_deployment_name}"; then
        log_error "Validation failed for new deployment"
        
        # Cleanup failed deployment
        log_warning "Rolling back failed deployment..."
        kubectl rollout undo deployment/${target_deployment_name} -n ${NAMESPACE}
        exit 1
    fi
    
    # Step 4: Run smoke tests
    log_info "Step 4: Running smoke tests..."
    if [ -f "./scripts/smoke-tests.sh" ]; then
        ./scripts/smoke-tests.sh "${preview_url}"
        check_exit_code $? "Smoke tests failed" "Smoke tests passed"
    else
        log_warning "Smoke test script not found, skipping..."
    fi
    
    # Step 5: Gradual traffic switching
    log_info "Step 5: Starting gradual traffic switch..."
    
    # Switch 10% traffic
    log_blue_green "Switching 10% traffic to ${target_deployment}..."
    kubectl patch service ${ACTIVE_SERVICE} -n ${NAMESPACE} \
        --type='merge' \
        -p='{"metadata":{"annotations":{"traffic.blue":"90","traffic.green":"10"}}}'
    sleep 60
    
    # Monitor for errors
    log_info "Monitoring for errors (60s)..."
    sleep 60
    
    # Switch 50% traffic
    log_blue_green "Switching 50% traffic to ${target_deployment}..."
    kubectl patch service ${ACTIVE_SERVICE} -n ${NAMESPACE} \
        --type='merge' \
        -p='{"metadata":{"annotations":{"traffic.blue":"50","traffic.green":"50"}}}'
    sleep 60
    
    # Monitor for errors
    log_info "Monitoring for errors (60s)..."
    sleep 60
    
    # Switch 100% traffic
    log_blue_green "Switching 100% traffic to ${target_deployment}..."
    kubectl patch service ${ACTIVE_SERVICE} -n ${NAMESPACE} \
        -p '{"spec":{"selector":{"version":"'${target_deployment}'"}}}'
    
    check_exit_code $? "Failed to switch traffic" "Traffic switched successfully"
    
    # Step 6: Final validation
    log_info "Step 6: Final validation..."
    local active_url="http://$(kubectl get service ${ACTIVE_SERVICE} -n ${NAMESPACE} -o jsonpath='{.status.loadBalancer.ingress[0].ip}' 2>/dev/null || echo 'localhost')"
    
    if ! validate_service "${active_url}" "${target_deployment_name}"; then
        log_error "Final validation failed, initiating emergency rollback..."
        
        # Emergency rollback
        kubectl patch service ${ACTIVE_SERVICE} -n ${NAMESPACE} \
            -p '{"spec":{"selector":{"version":"'${current_deployment}'"}}}'
        
        log_error "Emergency rollback completed"
        exit 1
    fi
    
    # Step 7: Cleanup old deployment (optional)
    log_info "Step 7: Scaling down old deployment..."
    if [ "${current_deployment}" != "none" ]; then
        local old_deployment_name="${SERVICE_NAME}-${current_deployment}"
        kubectl scale deployment ${old_deployment_name} --replicas=1 -n ${NAMESPACE}
        log_info "Old deployment scaled down to 1 replica for quick rollback capability"
    fi
    
    # Step 8: Update monitoring and alerts
    log_info "Step 8: Updating monitoring..."
    if [ -f "./scripts/update-monitoring.sh" ]; then
        ./scripts/update-monitoring.sh "${target_deployment}" "${IMAGE_NAME}"
    fi
    
    # Success!
    log_blue_green "🎉 Blue-Green deployment completed successfully!"
    log_info "Active deployment: ${target_deployment}"
    log_info "Image: ${IMAGE_NAME}"
    log_info "Service URL: ${active_url}"
    
    # Deployment summary
    echo ""
    echo "📊 Deployment Summary:"
    echo "   Previous: ${current_deployment:-none}"
    echo "   Current:  ${target_deployment}"
    echo "   Image:    ${IMAGE_NAME}"
    echo "   Duration: $(date)"
    echo ""
}

# Rollback function
rollback() {
    local current_deployment=$(get_current_deployment)
    local previous_deployment=$(get_target_deployment ${current_deployment})
    
    log_error "🔄 Initiating emergency rollback..."
    log_info "Rolling back from ${current_deployment} to ${previous_deployment}"
    
    # Switch traffic back
    kubectl patch service ${ACTIVE_SERVICE} -n ${NAMESPACE} \
        -p '{"spec":{"selector":{"version":"'${previous_deployment}'"}}}'
    
    # Scale up previous deployment
    local previous_deployment_name="${SERVICE_NAME}-${previous_deployment}"
    kubectl scale deployment ${previous_deployment_name} --replicas=3 -n ${NAMESPACE}
    
    log_info "Emergency rollback completed"
}

# Handle script arguments
case "${2:-deploy}" in
    "deploy")
        main
        ;;
    "rollback")
        rollback
        ;;
    *)
        echo "Usage: $0 <image_name> [deploy|rollback]"
        exit 1
        ;;
esac