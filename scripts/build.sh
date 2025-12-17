#!/usr/bin/env bash
#
# ICT Platform Build Script
# =========================
# Builds production-ready assets for the ICT Platform plugin
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PLUGIN_DIR="$PROJECT_DIR/wp-ict-platform"

echo -e "${CYAN}ICT Platform - Production Build${NC}"
echo "================================"

# Check if we're in the right directory
if [ ! -d "$PLUGIN_DIR" ]; then
    echo -e "${RED}Error: Plugin directory not found at $PLUGIN_DIR${NC}"
    exit 1
fi

cd "$PLUGIN_DIR"

# Check for required tools
echo -e "\n${YELLOW}Checking requirements...${NC}"

if ! command -v node &> /dev/null; then
    echo -e "${RED}Error: Node.js is not installed${NC}"
    exit 1
fi

if ! command -v npm &> /dev/null; then
    echo -e "${RED}Error: npm is not installed${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Node.js $(node --version)${NC}"
echo -e "${GREEN}✓ npm $(npm --version)${NC}"

# Install dependencies if needed
if [ ! -d "node_modules" ]; then
    echo -e "\n${YELLOW}Installing npm dependencies...${NC}"
    npm ci
fi

if [ ! -d "vendor" ] && [ -f "composer.json" ]; then
    echo -e "\n${YELLOW}Installing composer dependencies...${NC}"
    if command -v composer &> /dev/null; then
        composer install --no-dev --optimize-autoloader
    else
        echo -e "${YELLOW}Warning: Composer not found, skipping PHP dependencies${NC}"
    fi
fi

# Clean previous build
echo -e "\n${YELLOW}Cleaning previous build...${NC}"
rm -rf assets/js/dist/*
rm -rf assets/css/*.css 2>/dev/null || true

# Run linting
echo -e "\n${YELLOW}Running linters...${NC}"
npm run lint || {
    echo -e "${YELLOW}Warning: Linting issues found (continuing build)${NC}"
}

# Type checking
echo -e "\n${YELLOW}Running TypeScript type check...${NC}"
npm run type-check || {
    echo -e "${RED}Error: TypeScript type check failed${NC}"
    exit 1
}

# Build production assets
echo -e "\n${YELLOW}Building production assets...${NC}"
npm run build

if [ $? -eq 0 ]; then
    echo -e "\n${GREEN}✓ Build completed successfully!${NC}"
    echo ""
    echo "Build artifacts:"
    ls -lh assets/js/dist/*.js 2>/dev/null | awk '{print "  " $9 " (" $5 ")"}'
    ls -lh assets/css/*.css 2>/dev/null | awk '{print "  " $9 " (" $5 ")"}'
else
    echo -e "\n${RED}✗ Build failed!${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}Ready for deployment!${NC}"
