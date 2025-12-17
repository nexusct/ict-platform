#!/usr/bin/env bash
#
# ICT Platform Test Runner
# ========================
# Runs all tests with coverage reporting
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PLUGIN_DIR="$PROJECT_DIR/wp-ict-platform"

echo -e "${CYAN}ICT Platform - Test Suite${NC}"
echo "=========================="
echo ""

cd "$PLUGIN_DIR"

# Track overall status
PHP_STATUS=0
JS_STATUS=0

# Run JavaScript/TypeScript tests
echo -e "${YELLOW}Running JavaScript tests...${NC}"
echo ""

if npm test -- --coverage --passWithNoTests; then
    echo -e "\n${GREEN}✓ JavaScript tests passed${NC}"
else
    echo -e "\n${RED}✗ JavaScript tests failed${NC}"
    JS_STATUS=1
fi

echo ""

# Run PHP tests
echo -e "${YELLOW}Running PHP tests...${NC}"
echo ""

if [ -f "vendor/bin/phpunit" ]; then
    if vendor/bin/phpunit --coverage-text; then
        echo -e "\n${GREEN}✓ PHP tests passed${NC}"
    else
        echo -e "\n${RED}✗ PHP tests failed${NC}"
        PHP_STATUS=1
    fi
else
    echo -e "${YELLOW}⚠ PHPUnit not installed, skipping PHP tests${NC}"
    echo "  Run: composer install"
fi

echo ""
echo "=========================="

# Summary
if [ $PHP_STATUS -eq 0 ] && [ $JS_STATUS -eq 0 ]; then
    echo -e "${GREEN}All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed!${NC}"
    exit 1
fi
