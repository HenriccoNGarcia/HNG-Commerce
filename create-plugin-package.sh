#!/bin/bash

# HNG Commerce - Universal Installation Package Generator
# Creates a ZIP file that works on any WordPress installation
# 
# Usage: bash create-plugin-package.sh [output-directory]
# 
# This script ensures the ZIP doesn't capture problematic OS-level permissions
# that might cause issues when installing on different servers

set -e

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}═══════════════════════════════════════════════════════════=${NC}"
echo -e "${BLUE}   HNG Commerce - Universal Plugin Package Generator${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

# Get script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PLUGIN_DIR="$SCRIPT_DIR"
OUTPUT_DIR="${1:-.}"

# Get version from plugin file
VERSION=$(grep "Version:" "$PLUGIN_DIR/hng-commerce.php" | head -1 | sed 's/.*Version: \([^ ]*\).*/\1/')

PACKAGE_NAME="hng-commerce-v${VERSION}"
PACKAGE_FILE="$OUTPUT_DIR/$PACKAGE_NAME.zip"

echo -e "${YELLOW}Plugin Directory:${NC} $PLUGIN_DIR"
echo -e "${YELLOW}Output Directory:${NC} $OUTPUT_DIR"
echo -e "${YELLOW}Package Name:${NC} $PACKAGE_NAME.zip"
echo -e "${YELLOW}Version:${NC} $VERSION"
echo ""

# Verify plugin structure
echo -e "${BLUE}[1/5]${NC} Verifying plugin structure..."
if [ ! -f "$PLUGIN_DIR/hng-commerce.php" ]; then
    echo -e "${RED}✗ ERROR: hng-commerce.php not found!${NC}"
    exit 1
fi
if [ ! -d "$PLUGIN_DIR/includes" ]; then
    echo -e "${RED}✗ ERROR: includes/ directory not found!${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Plugin structure verified${NC}"
echo ""

# Create temporary staging directory
echo -e "${BLUE}[2/5]${NC} Preparing staging directory..."
STAGING_DIR=$(mktemp -d)
STAGING_PLUGIN="$STAGING_DIR/hng-commerce"

trap "rm -rf $STAGING_DIR" EXIT

cp -r "$PLUGIN_DIR" "$STAGING_PLUGIN"

# Remove problematic files and directories
echo -e "${BLUE}[3/5]${NC} Cleaning up artifacts..."

# Remove version control
rm -rf "$STAGING_PLUGIN/.git"
rm -rf "$STAGING_PLUGIN/.gitignore"
rm -rf "$STAGING_PLUGIN/.gitattributes"
rm -rf "$STAGING_PLUGIN/.svn"

# Remove Node/build artifacts
rm -rf "$STAGING_PLUGIN/node_modules"
rm -rf "$STAGING_PLUGIN/build"
rm -rf "$STAGING_PLUGIN/.webpack"

# Remove logs (these should never be in distribution)
rm -rf "$STAGING_PLUGIN/logs"
mkdir -p "$STAGING_PLUGIN/logs"
touch "$STAGING_PLUGIN/logs/.gitkeep"

# Remove backup files
find "$STAGING_PLUGIN" -name "*.bak" -delete
find "$STAGING_PLUGIN" -name "*.tmp" -delete
find "$STAGING_PLUGIN" -name "*~" -delete
find "$STAGING_PLUGIN" -name ".DS_Store" -delete
find "$STAGING_PLUGIN" -name "Thumbs.db" -delete

# Remove editor-specific files
rm -rf "$STAGING_PLUGIN/.vscode"
rm -rf "$STAGING_PLUGIN/.idea"
rm -rf "$STAGING_PLUGIN/.sublime-project"
rm -rf "$STAGING_PLUGIN/.sublime-workspace"

# Remove environment files
rm -f "$STAGING_PLUGIN/.env"
rm -f "$STAGING_PLUGIN/.env.local"
rm -f "$STAGING_PLUGIN/.env.*.local"

# Remove composer vendor (if present, users should run composer install)
rm -rf "$STAGING_PLUGIN/vendor"

echo -e "${GREEN}✓ Artifacts cleaned${NC}"
echo ""

# Normalize all permissions before zipping
echo -e "${BLUE}[4/5]${NC} Normalizing file permissions for universal compatibility..."

# Make all files world-readable (644)
find "$STAGING_PLUGIN" -type f -exec chmod 644 {} \; 2>/dev/null || true

# Make all directories accessible (755)
find "$STAGING_PLUGIN" -type d -exec chmod 755 {} \; 2>/dev/null || true

# Make PHP scripts executable
find "$STAGING_PLUGIN" -name "*.php" -type f -exec chmod 644 {} \; 2>/dev/null || true

echo -e "${GREEN}✓ Permissions normalized${NC}"
echo ""

# Create ZIP without storing OS-level file attributes
echo -e "${BLUE}[5/5]${NC} Creating universal ZIP archive..."

# Remove existing ZIP if it exists
rm -f "$PACKAGE_FILE"

# Create ZIP with specific options:
# -r: Recursive
# -q: Quiet mode
# -X: Don't store extra file attributes (this is key for universal compatibility)
# -9: Maximum compression
# Key: Using -X prevents storing Unix file permissions that would be problematic
if zip -r -q -X -9 "$PACKAGE_FILE" "$STAGING_PLUGIN" 2>/dev/null; then
    
    # If -X is not supported, fall back to standard zip
    if [ $? -ne 0 ]; then
        echo -e "${YELLOW}Note: Using standard ZIP (without extended attributes filtering)${NC}"
        rm -f "$PACKAGE_FILE"
        zip -r -q -9 "$PACKAGE_FILE" "$STAGING_PLUGIN"
    fi
else
    # Fallback to basic zip
    rm -f "$PACKAGE_FILE"
    zip -r -q "$PACKAGE_FILE" "$STAGING_PLUGIN"
fi

# Verify package
if [ ! -f "$PACKAGE_FILE" ]; then
    echo -e "${RED}✗ ERROR: Failed to create ZIP file!${NC}"
    exit 1
fi

PACKAGE_SIZE=$(ls -lh "$PACKAGE_FILE" | awk '{print $5}')
FILE_COUNT=$(unzip -l "$PACKAGE_FILE" | tail -1 | awk '{print $2}')

echo -e "${GREEN}✓ ZIP archive created successfully${NC}"
echo ""

# Verify archive integrity
echo -e "${BLUE}[VERIFY]${NC} Testing archive integrity..."
if unzip -t "$PACKAGE_FILE" > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Archive integrity verified (all tests passed)${NC}"
else
    echo -e "${RED}✗ WARNING: Archive integrity check failed${NC}"
fi
echo ""

# Display results
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✓ PACKAGE SUCCESSFULLY CREATED${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""
echo "Package Information:"
echo "  Name: $PACKAGE_NAME.zip"
echo "  Location: $PACKAGE_FILE"
echo "  Size: $PACKAGE_SIZE"
echo "  Files: $FILE_COUNT"
echo ""
echo "Installation Instructions:"
echo "  1. Log in to WordPress Admin"
echo "  2. Go to: Plugins > Add New > Upload Plugin"
echo "  3. Select: $PACKAGE_NAME.zip"
echo "  4. Click: Install Now"
echo "  5. Activate the plugin"
echo ""
echo "The plugin includes automatic permission fixing on activation,"
echo "so it will work correctly even if permissions are not optimal."
echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
