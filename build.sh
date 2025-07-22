#!/bin/bash

# Build script for ConvertCart Analytics WordPress Plugin
# This script creates a production-ready zip file for WordPress installation
# Includes SMS and Email consent blocks for WooCommerce Blocks checkout

set -e

echo "🚀 Starting ConvertCart Analytics Plugin Build Process..."

# Get plugin version from main file
VERSION=$(grep "Version:" cc-analytics.php | sed 's/.*Version: *\([0-9.]*\).*/\1/')
echo "📦 Building version: $VERSION"

# Create build directory
BUILD_DIR="build"
PLUGIN_DIR="convert-cart-analytics"
ZIP_NAME="convert-cart-analytics-v$VERSION.zip"

# Clean previous build
echo "🧹 Cleaning previous build..."
rm -rf $BUILD_DIR
rm -f *.zip

# Create build directory structure
mkdir -p $BUILD_DIR/$PLUGIN_DIR

echo "📋 Copying essential files..."

# Copy main plugin files
cp cc-analytics.php $BUILD_DIR/$PLUGIN_DIR/
cp README.md $BUILD_DIR/$PLUGIN_DIR/
cp CHANGELOG.md $BUILD_DIR/$PLUGIN_DIR/

# Copy includes directory
cp -r includes/ $BUILD_DIR/$PLUGIN_DIR/

# Copy images directory if it exists
if [ -d "assets/images" ]; then
    mkdir -p $BUILD_DIR/$PLUGIN_DIR/assets/images
    cp -r assets/images/* $BUILD_DIR/$PLUGIN_DIR/assets/images/
else
    echo "⚠️  assets/images directory not found, skipping..."
fi

# Copy only necessary asset files
mkdir -p $BUILD_DIR/$PLUGIN_DIR/assets/dist/js/sms_consent
cp -r assets/dist/js/sms_consent/* $BUILD_DIR/$PLUGIN_DIR/assets/dist/js/sms_consent/

mkdir -p $BUILD_DIR/$PLUGIN_DIR/assets/dist/js/email_consent
cp -r assets/dist/js/email_consent/* $BUILD_DIR/$PLUGIN_DIR/assets/dist/js/email_consent/

# Copy source CSS files for the consent blocks
mkdir -p $BUILD_DIR/$PLUGIN_DIR/assets/js/sms-consent
cp assets/js/sms-consent/index.css $BUILD_DIR/$PLUGIN_DIR/assets/js/sms-consent/

mkdir -p $BUILD_DIR/$PLUGIN_DIR/assets/js/email-consent
cp assets/js/email-consent/index.css $BUILD_DIR/$PLUGIN_DIR/assets/js/email-consent/

# Copy composer.json if exists (for dependencies)
if [ -f composer.json ]; then
    cp composer.json $BUILD_DIR/$PLUGIN_DIR/
fi

echo "🔧 Building assets..."

# Run webpack build to ensure assets are up to date
if command -v npm &> /dev/null; then
    echo "📦 Running webpack build..."
    npm run build:consent
    
    # Copy the freshly built assets
    cp -r assets/dist/js/sms_consent/* $BUILD_DIR/$PLUGIN_DIR/assets/dist/js/sms_consent/
    cp -r assets/dist/js/email_consent/* $BUILD_DIR/$PLUGIN_DIR/assets/dist/js/email_consent/
else
    echo "⚠️  npm not found, using existing built assets..."
fi

# Remove development files from build
echo "🧹 Cleaning development files..."
find $BUILD_DIR/$PLUGIN_DIR -name "*.map" -delete
find $BUILD_DIR/$PLUGIN_DIR -name "*.log" -delete
find $BUILD_DIR/$PLUGIN_DIR -name ".DS_Store" -delete

# Set proper permissions
echo "🔐 Setting file permissions..."
find $BUILD_DIR/$PLUGIN_DIR -type f -exec chmod 644 {} \;
find $BUILD_DIR/$PLUGIN_DIR -type d -exec chmod 755 {} \;

# Create zip file
echo "📦 Creating zip file..."
cd $BUILD_DIR
zip -r ../$ZIP_NAME $PLUGIN_DIR
cd ..

# Clean up build directory
rm -rf $BUILD_DIR

# Show file structure
echo "📁 Plugin structure:"
echo "convert-cart-analytics/"
echo "├── cc-analytics.php"
echo "├── README.md"
echo "├── CHANGELOG.md"
echo "├── composer.json"
echo "├── includes/"
echo "│   ├── class-wc-cc-analytics.php"
echo "│   ├── class-convertcart-sms-consent-block-integration.php"
echo "│   └── class-convertcart-email-consent-block-integration.php"
echo "├── assets/"
echo "│   ├── dist/"
echo "│   │   └── js/"
echo "│   │       ├── sms_consent/"
echo "│   │       │   ├── block.json"
echo "│   │       │   ├── sms-consent-block.js"
echo "│   │       │   ├── sms-consent-block.asset.php"
echo "│   │       │   ├── sms-consent-block-frontend.js"
echo "│   │       │   └── sms-consent-block-frontend.asset.php"
echo "│   │       └── email_consent/"
echo "│   │           ├── block.json"
echo "│   │           ├── email-consent-block.js"
echo "│   │           ├── email-consent-block.asset.php"
echo "│   │           ├── email-consent-block-frontend.js"
echo "│   │           └── email-consent-block-frontend.asset.php"
echo "│   └── js/"
echo "│       ├── sms-consent/"
echo "│       │   └── index.css"
echo "│       └── email-consent/"
echo "│           └── index.css"

echo "✅ Build complete!"
echo "📦 Created: $ZIP_NAME"
echo "📏 Size: $(du -h $ZIP_NAME | cut -f1)"

# Show installation instructions
echo ""
echo "📥 Installation Instructions:"
echo "1. Upload $ZIP_NAME to your WordPress admin"
echo "2. Go to Plugins → Add New → Upload Plugin"
echo "3. Choose the zip file and click 'Install Now'"
echo "4. Activate the plugin"
echo "5. Configure in WooCommerce → Settings → Integrations → ConvertCart Analytics"
echo "6. Enable SMS and/or Email consent blocks in the integration settings"
echo ""
echo "🎉 Plugin includes SMS and Email consent blocks for WooCommerce Blocks checkout!"
