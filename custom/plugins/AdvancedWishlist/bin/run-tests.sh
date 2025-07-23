#!/bin/bash

# Advanced Wishlist Plugin - Comprehensive Test Suite Runner
# Quality-Assurance-Guardian Agent Implementation
# Target: 80%+ Code Coverage and Production Readiness Validation

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PHPUNIT_BIN="./vendor/bin/phpunit"
COVERAGE_THRESHOLD=80
TEST_DB="shopware_test"

echo -e "${BLUE}🧪 Advanced Wishlist Plugin - Comprehensive Test Suite${NC}"
echo -e "${BLUE}===================================================${NC}"
echo

# Function to print status
print_status() {
    local status=$1
    local message=$2
    if [ "$status" = "success" ]; then
        echo -e "${GREEN}✅ $message${NC}"
    elif [ "$status" = "warning" ]; then
        echo -e "${YELLOW}⚠️  $message${NC}"
    elif [ "$status" = "error" ]; then
        echo -e "${RED}❌ $message${NC}"
    else
        echo -e "${BLUE}ℹ️  $message${NC}"
    fi
}

# Function to run test suite with coverage
run_test_suite() {
    local suite_name=$1
    local description=$2
    
    print_status "info" "Running $description..."
    
    if [ "$suite_name" = "all" ]; then
        $PHPUNIT_BIN --testsuite AdvancedWishlist --coverage-html coverage-html --coverage-clover coverage.xml
    else
        $PHPUNIT_BIN --testsuite "$suite_name"
    fi
    
    local exit_code=$?
    
    if [ $exit_code -eq 0 ]; then
        print_status "success" "$description completed successfully"
    else
        print_status "error" "$description failed with exit code $exit_code"
        return $exit_code
    fi
}

# Function to validate coverage
validate_coverage() {
    if [ -f "coverage.xml" ]; then
        # Extract coverage percentage from clover XML (simplified)
        local coverage=$(grep -o 'statements="[0-9]*"' coverage.xml | head -1 | grep -o '[0-9]*')
        local covered=$(grep -o 'coveredstatements="[0-9]*"' coverage.xml | head -1 | grep -o '[0-9]*')
        
        if [ -n "$coverage" ] && [ -n "$covered" ] && [ "$coverage" -gt 0 ]; then
            local percentage=$((covered * 100 / coverage))
            
            echo
            print_status "info" "Code Coverage Analysis:"
            echo -e "${BLUE}  Total Statements: $coverage${NC}"
            echo -e "${BLUE}  Covered Statements: $covered${NC}"
            echo -e "${BLUE}  Coverage Percentage: $percentage%${NC}"
            
            if [ "$percentage" -ge "$COVERAGE_THRESHOLD" ]; then
                print_status "success" "Coverage target achieved: $percentage% >= $COVERAGE_THRESHOLD%"
                return 0
            else
                print_status "error" "Coverage target not met: $percentage% < $COVERAGE_THRESHOLD%"
                return 1
            fi
        else
            print_status "warning" "Could not extract coverage data from coverage.xml"
            return 1
        fi
    else
        print_status "warning" "Coverage report not found"
        return 1
    fi
}

# Function to setup test environment
setup_test_environment() {
    print_status "info" "Setting up test environment..."
    
    # Check if PHPUnit exists
    if [ ! -f "$PHPUNIT_BIN" ]; then
        print_status "error" "PHPUnit not found at $PHPUNIT_BIN"
        echo "Please run: composer install"
        exit 1
    fi
    
    # Create necessary directories
    mkdir -p coverage-html
    mkdir -p .phpunit.cache
    
    # Check PHP version
    local php_version=$(php -r "echo PHP_VERSION;")
    print_status "info" "PHP Version: $php_version"
    
    # Check if Xdebug is available for coverage
    if php -m | grep -q xdebug; then
        print_status "success" "Xdebug available for coverage reporting"
    else
        print_status "warning" "Xdebug not available - coverage reporting may be limited"
    fi
    
    print_status "success" "Test environment setup complete"
}

# Function to run security tests
run_security_tests() {
    print_status "info" "🛡️  Running Security Test Suite..."
    echo
    
    # SQL Injection Tests
    print_status "info" "Testing SQL injection prevention..."
    $PHPUNIT_BIN --filter "testSqlInjectionPrevention" tests/Integration/Security/SecurityIntegrationTest.php
    
    # XSS Prevention Tests
    print_status "info" "Testing XSS prevention..."
    $PHPUNIT_BIN --filter "testXssPreventionInOutput" tests/Integration/Security/SecurityIntegrationTest.php
    
    # CSRF Protection Tests
    print_status "info" "Testing CSRF protection..."
    $PHPUNIT_BIN --filter "testCsrfTokenValidation" tests/Integration/Security/SecurityIntegrationTest.php
    
    # Rate Limiting Tests
    print_status "info" "Testing rate limiting..."
    $PHPUNIT_BIN --testsuite Security
    
    print_status "success" "Security tests completed"
}

# Function to run performance tests
run_performance_tests() {
    print_status "info" "⚡ Running Performance Test Suite..."
    echo
    
    # Database Performance
    print_status "info" "Testing database performance..."
    $PHPUNIT_BIN tests/Performance/WishlistPerformanceTest.php
    
    # Query Optimization
    print_status "info" "Testing query optimization..."
    $PHPUNIT_BIN tests/Integration/Database/DatabasePerformanceTest.php
    
    print_status "success" "Performance tests completed"
}

# Function to run PHP 8.4 compatibility tests
run_php84_tests() {
    print_status "info" "🚀 Running PHP 8.4 Compatibility Tests..."
    echo
    
    # Property Hooks Tests
    print_status "info" "Testing property hooks..."
    $PHPUNIT_BIN --filter "PropertyHooks" tests/Unit/Core/Content/Wishlist/WishlistEntityTest.php
    
    # Asymmetric Visibility Tests
    print_status "info" "Testing asymmetric visibility..."
    $PHPUNIT_BIN --filter "AsymmetricVisibility" tests/Unit/Core/Content/Wishlist/WishlistEntityTest.php
    
    print_status "success" "PHP 8.4 compatibility tests completed"
}

# Function to generate detailed report
generate_report() {
    print_status "info" "📊 Generating Test Report..."
    
    local report_file="test-report-$(date +%Y%m%d-%H%M%S).txt"
    
    {
        echo "Advanced Wishlist Plugin - Test Execution Report"
        echo "Generated: $(date)"
        echo "="
        echo
        
        echo "Test Suite Results:"
        echo "- Unit Tests: PASSED"
        echo "- Integration Tests: PASSED"
        echo "- Security Tests: PASSED"
        echo "- Performance Tests: PASSED"
        echo "- PHP 8.4 Compatibility: PASSED"
        echo "- End-to-End Tests: PASSED"
        echo
        
        if [ -f "coverage.xml" ]; then
            echo "Code Coverage:"
            local coverage=$(grep -o 'statements="[0-9]*"' coverage.xml | head -1 | grep -o '[0-9]*')
            local covered=$(grep -o 'coveredstatements="[0-9]*"' coverage.xml | head -1 | grep -o '[0-9]*')
            if [ -n "$coverage" ] && [ -n "$covered" ] && [ "$coverage" -gt 0 ]; then
                local percentage=$((covered * 100 / coverage))
                echo "- Overall Coverage: $percentage%"
                echo "- Statements Covered: $covered/$coverage"
            fi
        fi
        
        echo
        echo "Production Readiness: ✅ VALIDATED"
        echo "Quality Gates: ✅ PASSED"
        echo "Security Validation: ✅ PASSED"
        echo "Performance Validation: ✅ PASSED"
        
    } > "$report_file"
    
    print_status "success" "Report generated: $report_file"
}

# Main execution
main() {
    local test_type=${1:-"all"}
    
    case $test_type in
        "setup")
            setup_test_environment
            ;;
        "unit")
            setup_test_environment
            run_test_suite "Unit" "Unit Tests"
            ;;
        "integration")
            setup_test_environment
            run_test_suite "Integration" "Integration Tests"
            ;;
        "security")
            setup_test_environment
            run_security_tests
            ;;
        "performance")
            setup_test_environment
            run_performance_tests
            ;;
        "php84")
            setup_test_environment
            run_php84_tests
            ;;
        "e2e")
            setup_test_environment
            run_test_suite "E2E" "End-to-End Tests"
            ;;
        "coverage")
            setup_test_environment
            run_test_suite "all" "Full Test Suite with Coverage"
            validate_coverage
            ;;
        "all")
            setup_test_environment
            echo
            
            # Run all test suites
            run_test_suite "Unit" "Unit Tests" || exit 1
            run_test_suite "Integration" "Integration Tests" || exit 1
            run_security_tests || exit 1
            run_performance_tests || exit 1
            run_php84_tests || exit 1
            run_test_suite "E2E" "End-to-End Tests" || exit 1
            
            # Generate coverage report
            print_status "info" "Generating comprehensive coverage report..."
            $PHPUNIT_BIN --testsuite AdvancedWishlist --coverage-html coverage-html --coverage-clover coverage.xml
            
            # Validate coverage
            validate_coverage || exit 1
            
            # Generate report
            generate_report
            
            echo
            print_status "success" "🎉 All tests passed! Plugin is production-ready."
            echo
            echo -e "${GREEN}📋 Test Summary:${NC}"
            echo -e "${GREEN}✅ 80%+ Code Coverage Achieved${NC}"
            echo -e "${GREEN}✅ Security Vulnerabilities Tested${NC}"
            echo -e "${GREEN}✅ Performance Optimizations Validated${NC}"
            echo -e "${GREEN}✅ PHP 8.4 Compatibility Confirmed${NC}"
            echo -e "${GREEN}✅ Production Readiness Validated${NC}"
            ;;
        *)
            echo "Usage: $0 [setup|unit|integration|security|performance|php84|e2e|coverage|all]"
            echo
            echo "Commands:"
            echo "  setup       - Setup test environment"
            echo "  unit        - Run unit tests only"
            echo "  integration - Run integration tests only"
            echo "  security    - Run security tests only"
            echo "  performance - Run performance tests only"
            echo "  php84       - Run PHP 8.4 compatibility tests"
            echo "  e2e         - Run end-to-end tests only"
            echo "  coverage    - Run all tests with coverage"
            echo "  all         - Run complete test suite (default)"
            exit 1
            ;;
    esac
}

# Execute main function with all arguments
main "$@"
