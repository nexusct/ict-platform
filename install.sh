#!/bin/bash

#############################################
#                                           #
#     ICT Platform Installation Script      #
#                                           #
#############################################

# Colors for pretty output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Print a nice header
echo ""
echo -e "${CYAN}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║                                                           ║${NC}"
echo -e "${CYAN}║         ${PURPLE}ICT PLATFORM INSTALLATION SCRIPT${CYAN}                ║${NC}"
echo -e "${CYAN}║                                                           ║${NC}"
echo -e "${CYAN}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""

# Function to print step messages
print_step() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}▶ STEP $1: $2${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

# Function to print success messages
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

# Function to print error messages
print_error() {
    echo -e "${RED}✗ ERROR: $1${NC}"
}

# Function to print warning messages
print_warning() {
    echo -e "${YELLOW}⚠ WARNING: $1${NC}"
}

# Function to print info messages
print_info() {
    echo -e "${CYAN}ℹ $1${NC}"
}

# Track if there were any errors
HAS_ERRORS=0

# Get the directory where this script is located
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

#############################################
# STEP 1: Check Prerequisites
#############################################
print_step "1" "Checking if required programs are installed..."

# Check for Node.js
if command -v node &> /dev/null; then
    NODE_VERSION=$(node --version)
    print_success "Node.js is installed (version: $NODE_VERSION)"
else
    print_error "Node.js is NOT installed!"
    print_info "Please install Node.js from: https://nodejs.org/"
    print_info "Download the LTS version (the one that says 'Recommended')"
    HAS_ERRORS=1
fi

# Check for npm
if command -v npm &> /dev/null; then
    NPM_VERSION=$(npm --version)
    print_success "npm is installed (version: $NPM_VERSION)"
else
    print_error "npm is NOT installed!"
    print_info "npm comes with Node.js. Please install Node.js first."
    HAS_ERRORS=1
fi

# Check for PHP
if command -v php &> /dev/null; then
    PHP_VERSION=$(php --version | head -n 1)
    print_success "PHP is installed ($PHP_VERSION)"
else
    print_error "PHP is NOT installed!"
    print_info "Please install PHP 8.1 or higher"
    HAS_ERRORS=1
fi

# Check for Composer
if command -v composer &> /dev/null; then
    COMPOSER_VERSION=$(composer --version | head -n 1)
    print_success "Composer is installed ($COMPOSER_VERSION)"
else
    print_warning "Composer is NOT installed (optional - needed for PHP development)"
    print_info "Install from: https://getcomposer.org/download/"
fi

echo ""

# If critical errors, ask if user wants to continue
if [ $HAS_ERRORS -eq 1 ]; then
    echo ""
    print_error "Some required programs are missing!"
    echo ""
    read -p "Do you want to continue anyway? (y/n): " CONTINUE
    if [ "$CONTINUE" != "y" ] && [ "$CONTINUE" != "Y" ]; then
        echo ""
        print_info "Installation cancelled. Please install the missing programs first."
        exit 1
    fi
fi

#############################################
# STEP 2: Install WordPress Plugin Dependencies
#############################################
print_step "2" "Installing WordPress Plugin (JavaScript packages)..."

WP_PLUGIN_DIR="$SCRIPT_DIR/wp-ict-platform"

if [ -d "$WP_PLUGIN_DIR" ]; then
    cd "$WP_PLUGIN_DIR"

    print_info "Installing npm packages... (this may take a few minutes)"
    echo ""

    if npm install; then
        print_success "JavaScript packages installed successfully!"
    else
        print_error "Failed to install JavaScript packages!"
        HAS_ERRORS=1
    fi

    cd "$SCRIPT_DIR"
else
    print_error "WordPress plugin directory not found: $WP_PLUGIN_DIR"
    HAS_ERRORS=1
fi

echo ""

#############################################
# STEP 3: Install WordPress Plugin PHP Dependencies
#############################################
print_step "3" "Installing WordPress Plugin (PHP packages)..."

if [ -d "$WP_PLUGIN_DIR" ]; then
    cd "$WP_PLUGIN_DIR"

    if command -v composer &> /dev/null; then
        print_info "Installing Composer packages..."
        echo ""

        if composer install --no-interaction; then
            print_success "PHP packages installed successfully!"
        else
            print_warning "Failed to install PHP packages (may not be critical)"
        fi
    else
        print_warning "Skipping PHP packages (Composer not installed)"
    fi

    cd "$SCRIPT_DIR"
fi

echo ""

#############################################
# STEP 4: Build WordPress Plugin Assets
#############################################
print_step "4" "Building WordPress Plugin assets..."

if [ -d "$WP_PLUGIN_DIR" ]; then
    cd "$WP_PLUGIN_DIR"

    print_info "Building production assets... (this may take a minute)"
    echo ""

    if npm run build; then
        print_success "WordPress plugin built successfully!"
    else
        print_error "Failed to build WordPress plugin!"
        HAS_ERRORS=1
    fi

    cd "$SCRIPT_DIR"
fi

echo ""

#############################################
# STEP 5: Install Mobile App Dependencies
#############################################
print_step "5" "Installing Mobile App packages..."

MOBILE_APP_DIR="$SCRIPT_DIR/ict-mobile-app"

if [ -d "$MOBILE_APP_DIR" ]; then
    cd "$MOBILE_APP_DIR"

    print_info "Installing npm packages for mobile app..."
    echo ""

    if npm install; then
        print_success "Mobile app packages installed successfully!"
    else
        print_error "Failed to install mobile app packages!"
        HAS_ERRORS=1
    fi

    cd "$SCRIPT_DIR"
else
    print_warning "Mobile app directory not found (skipping): $MOBILE_APP_DIR"
fi

echo ""

#############################################
# STEP 6: Final Summary
#############################################
print_step "6" "Installation Complete!"

echo ""
if [ $HAS_ERRORS -eq 0 ]; then
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                                                           ║${NC}"
    echo -e "${GREEN}║     ✓ ALL PACKAGES INSTALLED SUCCESSFULLY! ✓             ║${NC}"
    echo -e "${GREEN}║                                                           ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════╝${NC}"
else
    echo -e "${YELLOW}╔═══════════════════════════════════════════════════════════╗${NC}"
    echo -e "${YELLOW}║                                                           ║${NC}"
    echo -e "${YELLOW}║     ⚠ INSTALLATION COMPLETED WITH SOME WARNINGS ⚠        ║${NC}"
    echo -e "${YELLOW}║                                                           ║${NC}"
    echo -e "${YELLOW}╚═══════════════════════════════════════════════════════════╝${NC}"
fi

echo ""
echo -e "${CYAN}What you can do next:${NC}"
echo ""
echo -e "  ${PURPLE}WordPress Plugin:${NC}"
echo -e "    • Copy the 'wp-ict-platform' folder to your WordPress plugins directory"
echo -e "    • Go to WordPress Admin > Plugins > Activate 'ICT Platform'"
echo ""
echo -e "  ${PURPLE}Mobile App:${NC}"
echo -e "    • cd ict-mobile-app"
echo -e "    • npm start (to run the development server)"
echo ""
echo -e "  ${PURPLE}Development Commands:${NC}"
echo -e "    • npm run dev    - Watch mode for development"
echo -e "    • npm run build  - Build for production"
echo -e "    • npm run test   - Run tests"
echo -e "    • npm run lint   - Check code style"
echo ""

exit 0
