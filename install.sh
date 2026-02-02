#!/bin/bash

# Cleanup Report Generator - Server Installation Script
# Run this script on your server to install DOMpdf dependencies

echo "Installing Cleanup Report Generator dependencies..."

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "Composer not found. Installing Composer..."
    
    # Download and install Composer
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    
    # Verify installation
    if command -v composer &> /dev/null; then
        echo "Composer installed successfully"
    else
        echo "Failed to install Composer. Please install manually."
        exit 1
    fi
else
    echo "Composer is already installed"
fi

# Install PHP dependencies
echo "Installing DOMpdf and dependencies..."
composer install --no-dev --optimize-autoloader

# Set proper permissions
echo "Setting proper permissions..."
chmod -R 755 vendor/ 2>/dev/null || true
chown -R www-data:www-data vendor/ 2>/dev/null || chown -R apache:apache vendor/ 2>/dev/null || true

# Test DOMpdf installation
echo "Testing DOMpdf installation..."
php -r "
require_once 'vendor/autoload.php';
if (class_exists('Dompdf\\\\Dompdf')) {
    echo 'DOMpdf is installed and working!' . PHP_EOL;
} else {
    echo 'DOMpdf installation failed' . PHP_EOL;
    exit(1);
}
"

echo ""
echo "Installation completed successfully!"
echo ""
echo "Next steps:"
echo "1. Upload the plugin to your WordPress site"
echo "2. Activate the plugin in WordPress admin"
echo "3. Create cleanup reports with professional PDF export"
echo ""
echo "Features included:"
echo "✅ WPMU DEV branding and colors"
echo "✅ Professional PDF generation with DOMpdf"
echo "✅ Multiple screenshot support"
echo "✅ WYSIWYG editors for rich content"
echo "✅ Responsive design and print-friendly layouts"
echo ""