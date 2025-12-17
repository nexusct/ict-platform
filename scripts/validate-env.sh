#!/usr/bin/env bash
#
# ICT Platform Environment Validation Script
# ==========================================
# Validates that all required configuration is in place
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

echo -e "${CYAN}ICT Platform - Environment Validation${NC}"
echo "======================================="
echo ""

ERRORS=0
WARNINGS=0

# Function to check for required variable
check_required() {
    if [ -z "${!1}" ]; then
        echo -e "${RED}✗ Missing required: $1${NC}"
        ((ERRORS++))
    else
        echo -e "${GREEN}✓ $1 is set${NC}"
    fi
}

# Function to check for optional variable
check_optional() {
    if [ -z "${!1}" ]; then
        echo -e "${YELLOW}⚠ Optional not set: $1${NC}"
        ((WARNINGS++))
    else
        echo -e "${GREEN}✓ $1 is set${NC}"
    fi
}

# Load environment
if [ -f "$PROJECT_DIR/.env" ]; then
    echo -e "${GREEN}✓ .env file found${NC}"
    export $(grep -v '^#' "$PROJECT_DIR/.env" | xargs)
else
    echo -e "${RED}✗ .env file not found${NC}"
    echo "  Create from: cp .env.example .env"
    exit 1
fi

echo ""
echo -e "${YELLOW}Database Configuration:${NC}"
check_required "DB_NAME"
check_required "DB_USER"
check_required "DB_PASSWORD"

echo ""
echo -e "${YELLOW}Zoho Integration:${NC}"
check_optional "ZOHO_CRM_CLIENT_ID"
check_optional "ZOHO_CRM_CLIENT_SECRET"
check_optional "ZOHO_FSM_CLIENT_ID"
check_optional "ZOHO_BOOKS_CLIENT_ID"
check_optional "ZOHO_PEOPLE_CLIENT_ID"
check_optional "ZOHO_DESK_CLIENT_ID"

echo ""
echo -e "${YELLOW}QuoteWerks Integration:${NC}"
check_optional "QUOTEWERKS_API_URL"
check_optional "QUOTEWERKS_API_KEY"
check_optional "QUOTEWERKS_WEBHOOK_SECRET"

echo ""
echo -e "${YELLOW}Security:${NC}"
check_optional "ICT_ENCRYPTION_KEY"

echo ""
echo -e "${YELLOW}Deployment:${NC}"
check_optional "DEPLOY_HOST"
check_optional "DEPLOY_USER"
check_optional "DEPLOY_PATH"

echo ""
echo "======================================="

if [ $ERRORS -gt 0 ]; then
    echo -e "${RED}Validation failed with $ERRORS error(s)${NC}"
    exit 1
elif [ $WARNINGS -gt 0 ]; then
    echo -e "${YELLOW}Validation passed with $WARNINGS warning(s)${NC}"
    echo "Some integrations may not work without optional configuration"
    exit 0
else
    echo -e "${GREEN}Validation passed!${NC}"
    exit 0
fi
