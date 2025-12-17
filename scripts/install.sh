#!/usr/bin/env bash
#
# ICT Platform Installation Script
# ================================
# Sets up the development environment
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

echo -e "${CYAN}ICT Platform - Development Setup${NC}"
echo "=================================="
echo ""

# Check Node.js
echo -e "${YELLOW}Checking Node.js...${NC}"
if command -v node &> /dev/null; then
    NODE_VERSION=$(node --version)
    echo -e "${GREEN}✓ Node.js ${NODE_VERSION} installed${NC}"
else
    echo -e "${RED}✗ Node.js not found${NC}"
    echo "  Install from: https://nodejs.org/"
    exit 1
fi

# Check npm
if command -v npm &> /dev/null; then
    NPM_VERSION=$(npm --version)
    echo -e "${GREEN}✓ npm ${NPM_VERSION} installed${NC}"
else
    echo -e "${RED}✗ npm not found${NC}"
    exit 1
fi

# Check PHP
echo -e "\n${YELLOW}Checking PHP...${NC}"
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n1 | cut -d" " -f2)
    echo -e "${GREEN}✓ PHP ${PHP_VERSION} installed${NC}"
else
    echo -e "${RED}✗ PHP not found${NC}"
    echo "  Required for running tests locally"
fi

# Check Composer
if command -v composer &> /dev/null; then
    COMPOSER_VERSION=$(composer --version | cut -d" " -f3)
    echo -e "${GREEN}✓ Composer ${COMPOSER_VERSION} installed${NC}"
else
    echo -e "${YELLOW}⚠ Composer not found${NC}"
    echo "  Install from: https://getcomposer.org/"
fi

# Check Docker
echo -e "\n${YELLOW}Checking Docker...${NC}"
if command -v docker &> /dev/null; then
    DOCKER_VERSION=$(docker --version | cut -d" " -f3 | tr -d ',')
    echo -e "${GREEN}✓ Docker ${DOCKER_VERSION} installed${NC}"
else
    echo -e "${YELLOW}⚠ Docker not found${NC}"
    echo "  Install for local WordPress environment"
fi

if command -v docker-compose &> /dev/null; then
    DC_VERSION=$(docker-compose --version | cut -d" " -f4 | tr -d ',')
    echo -e "${GREEN}✓ Docker Compose ${DC_VERSION} installed${NC}"
fi

# Install npm dependencies
echo -e "\n${YELLOW}Installing npm dependencies...${NC}"
cd "$PLUGIN_DIR"
npm install
echo -e "${GREEN}✓ npm dependencies installed${NC}"

# Install Composer dependencies
if command -v composer &> /dev/null; then
    echo -e "\n${YELLOW}Installing Composer dependencies...${NC}"
    composer install
    echo -e "${GREEN}✓ Composer dependencies installed${NC}"
fi

# Create environment file if needed
if [ ! -f "$PROJECT_DIR/.env" ]; then
    echo -e "\n${YELLOW}Creating .env file from template...${NC}"
    cp "$PROJECT_DIR/.env.example" "$PROJECT_DIR/.env"
    echo -e "${GREEN}✓ .env file created${NC}"
    echo -e "${YELLOW}  Please update .env with your configuration${NC}"
fi

# Run initial build
echo -e "\n${YELLOW}Running initial build...${NC}"
npm run build:dev
echo -e "${GREEN}✓ Initial build complete${NC}"

echo ""
echo -e "${GREEN}=====================================${NC}"
echo -e "${GREEN}Development environment ready!${NC}"
echo -e "${GREEN}=====================================${NC}"
echo ""
echo "Quick start:"
echo "  make dev          - Start Docker + watch mode"
echo "  make test         - Run all tests"
echo "  make build        - Production build"
echo "  make help         - Show all commands"
echo ""
echo "WordPress will be available at: http://localhost:8080"
echo ""
