#!/usr/bin/env bash
#
# ICT Platform Deployment Script
# ==============================
# Deploys the plugin to a production server
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

# Load environment variables
if [ -f "$PROJECT_DIR/.env" ]; then
    export $(grep -v '^#' "$PROJECT_DIR/.env" | xargs)
fi

# Configuration (from environment or defaults)
DEPLOY_HOST="${DEPLOY_HOST:-}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
DEPLOY_PATH="${DEPLOY_PATH:-/var/www/html/wp-content/plugins}"
DEPLOY_KEY="${DEPLOY_KEY:-}"
PLUGIN_NAME="ict-platform"

echo -e "${CYAN}ICT Platform - Deployment${NC}"
echo "=========================="

# Validate configuration
if [ -z "$DEPLOY_HOST" ]; then
    echo -e "${RED}Error: DEPLOY_HOST not configured${NC}"
    echo "Set DEPLOY_HOST in .env or as environment variable"
    exit 1
fi

echo "Target: ${DEPLOY_USER}@${DEPLOY_HOST}:${DEPLOY_PATH}"
echo ""

# Build SSH command
SSH_CMD="ssh"
if [ -n "$DEPLOY_KEY" ]; then
    SSH_CMD="ssh -i $DEPLOY_KEY"
fi

# Check connectivity
echo -e "${YELLOW}Checking server connectivity...${NC}"
if ! $SSH_CMD -o ConnectTimeout=10 "${DEPLOY_USER}@${DEPLOY_HOST}" "echo 'Connected'" > /dev/null 2>&1; then
    echo -e "${RED}Error: Cannot connect to server${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Server accessible${NC}"

# Get latest release
RELEASE_DIR="$PROJECT_DIR/releases"
LATEST_RELEASE=$(ls -t "$RELEASE_DIR"/*.zip 2>/dev/null | head -n1)

if [ -z "$LATEST_RELEASE" ]; then
    echo -e "${YELLOW}No release package found. Creating one...${NC}"
    "$SCRIPT_DIR/release.sh"
    LATEST_RELEASE=$(ls -t "$RELEASE_DIR"/*.zip | head -n1)
fi

RELEASE_FILE=$(basename "$LATEST_RELEASE")
echo "Deploying: ${RELEASE_FILE}"
echo ""

# Confirm deployment
read -p "Deploy to production? (y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Deployment cancelled."
    exit 0
fi

echo ""
echo -e "${YELLOW}Starting deployment...${NC}"

# Create backup on server
echo "Creating backup..."
$SSH_CMD "${DEPLOY_USER}@${DEPLOY_HOST}" "
    cd ${DEPLOY_PATH} && \
    if [ -d ${PLUGIN_NAME} ]; then
        BACKUP_NAME=${PLUGIN_NAME}-backup-\$(date +%Y%m%d-%H%M%S).zip
        zip -r /tmp/\$BACKUP_NAME ${PLUGIN_NAME}
        echo \"Backup created: /tmp/\$BACKUP_NAME\"
    fi
"

# Upload release
echo "Uploading release..."
scp ${DEPLOY_KEY:+-i "$DEPLOY_KEY"} "$LATEST_RELEASE" "${DEPLOY_USER}@${DEPLOY_HOST}:/tmp/${RELEASE_FILE}"

# Extract and deploy
echo "Extracting and deploying..."
$SSH_CMD "${DEPLOY_USER}@${DEPLOY_HOST}" "
    cd ${DEPLOY_PATH} && \
    rm -rf ${PLUGIN_NAME} && \
    unzip -o /tmp/${RELEASE_FILE} && \
    rm /tmp/${RELEASE_FILE} && \
    chown -R www-data:www-data ${PLUGIN_NAME} && \
    chmod -R 755 ${PLUGIN_NAME}
"

# Verify deployment
echo "Verifying deployment..."
DEPLOYED_VERSION=$($SSH_CMD "${DEPLOY_USER}@${DEPLOY_HOST}" \
    "grep 'Version:' ${DEPLOY_PATH}/${PLUGIN_NAME}/ict-platform.php | sed 's/.*Version:[[:space:]]*//'")

echo ""
echo -e "${GREEN}✓ Deployment complete!${NC}"
echo -e "Deployed version: ${CYAN}${DEPLOYED_VERSION}${NC}"
echo ""
echo "Post-deployment checklist:"
echo "  [ ] Verify plugin is active in WordPress admin"
echo "  [ ] Test critical functionality"
echo "  [ ] Monitor error logs"
echo "  [ ] Check sync queue status"
