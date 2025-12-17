#!/usr/bin/env bash
#
# ICT Platform Release Script
# ===========================
# Creates a production-ready release package
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PLUGIN_DIR="$PROJECT_DIR/wp-ict-platform"
RELEASE_DIR="$PROJECT_DIR/releases"

# Get version from argument or plugin file
if [ -n "$1" ]; then
    VERSION="$1"
else
    VERSION=$(grep "Version:" "$PLUGIN_DIR/ict-platform.php" | sed 's/.*Version:[[:space:]]*//' | tr -d ' ')
fi

RELEASE_NAME="ict-platform-${VERSION}"
RELEASE_FILE="${RELEASE_DIR}/${RELEASE_NAME}.zip"

echo -e "${CYAN}ICT Platform - Release Builder${NC}"
echo "================================"
echo -e "Version: ${GREEN}${VERSION}${NC}"
echo ""

# Create releases directory
mkdir -p "$RELEASE_DIR"

# Check if release already exists
if [ -f "$RELEASE_FILE" ]; then
    echo -e "${YELLOW}Warning: Release file already exists!${NC}"
    read -p "Overwrite? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Aborted."
        exit 1
    fi
fi

# Run production build
echo -e "\n${YELLOW}Running production build...${NC}"
"$SCRIPT_DIR/build.sh"

# Create temporary directory for release
TEMP_DIR=$(mktemp -d)
TEMP_PLUGIN_DIR="$TEMP_DIR/ict-platform"

echo -e "\n${YELLOW}Preparing release package...${NC}"

# Copy plugin files
cp -r "$PLUGIN_DIR" "$TEMP_PLUGIN_DIR"

# Remove development files
cd "$TEMP_PLUGIN_DIR"

echo "Removing development files..."
rm -rf node_modules
rm -rf tests
rm -rf src/components/*/__tests__
rm -rf src/hooks/__tests__
rm -rf .phpunit.cache
rm -rf coverage
rm -rf coverage-report

rm -f .eslintrc.json
rm -f .prettierrc.json
rm -f jest.config.js
rm -f tsconfig.json
rm -f webpack.config.js
rm -f phpcs.xml
rm -f phpunit.xml
rm -f package.json
rm -f package-lock.json
rm -f composer.json
rm -f composer.lock
rm -f .gitignore
rm -f .editorconfig

# Remove source TypeScript files (keep compiled JS)
find src -name "*.ts" -delete 2>/dev/null || true
find src -name "*.tsx" -delete 2>/dev/null || true

# Update version in plugin file
sed -i "s/Version:.*$/Version:           ${VERSION}/" ict-platform.php
sed -i "s/define('ICT_PLATFORM_VERSION', '.*');/define('ICT_PLATFORM_VERSION', '${VERSION}');/" ict-platform.php

# Create zip file
echo -e "\n${YELLOW}Creating release archive...${NC}"
cd "$TEMP_DIR"
zip -r "$RELEASE_FILE" ict-platform -x "*.git*" -x "*.DS_Store"

# Cleanup
rm -rf "$TEMP_DIR"

# Show result
if [ -f "$RELEASE_FILE" ]; then
    SIZE=$(ls -lh "$RELEASE_FILE" | awk '{print $5}')
    echo -e "\n${GREEN}✓ Release package created successfully!${NC}"
    echo ""
    echo -e "File: ${CYAN}${RELEASE_FILE}${NC}"
    echo -e "Size: ${SIZE}"
    echo ""

    # Generate checksum
    CHECKSUM=$(sha256sum "$RELEASE_FILE" | awk '{print $1}')
    echo -e "SHA256: ${CHECKSUM}"
    echo "$CHECKSUM  ${RELEASE_NAME}.zip" > "${RELEASE_DIR}/${RELEASE_NAME}.sha256"

    echo ""
    echo -e "${GREEN}Ready for distribution!${NC}"
else
    echo -e "\n${RED}✗ Failed to create release package${NC}"
    exit 1
fi
