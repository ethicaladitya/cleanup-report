# Cleanup Report Generator v2.0.0

Professional WordPress plugin for generating branded cleanup reports with PDF export capabilities.

## 🚀 Features

- **WPMU DEV Branding**: Professional navy blue and gold color scheme
- **PDF Export**: High-quality PDF generation using DOMpdf
- **Multiple Screenshots**: Support for multiple before/after images
- **WYSIWYG Editors**: Rich text editing for all content fields
- **Responsive Design**: Mobile-friendly admin interface
- **Print-Friendly**: Optimized layouts for PDF and print output

## 📦 Installation

### Method 1: Automatic Installation (Recommended)

1. Upload the plugin folder to your server
2. Run the installation script:
   ```bash
   cd /path/to/wp-content/plugins/cleanup-report-generator
   ./install.sh
   ```

### Method 2: Manual Installation

1. **Install Composer** (if not already installed):
   ```bash
   curl -sS https://getcomposer.org/installer | php
   sudo mv composer.phar /usr/local/bin/composer
   ```

2. **Install Dependencies**:
   ```bash
   cd /path/to/wp-content/plugins/cleanup-report-generator
   composer install --no-dev --optimize-autoloader
   ```

3. **Set Permissions**:
   ```bash
   chmod -R 755 vendor/
   chown -R www-data:www-data vendor/
   ```

4. **Activate Plugin** in WordPress admin

## 🎯 Usage

1. **Create New Report**: Go to Cleanup Reports → Add New
2. **Fill Report Data**: Use the enhanced admin interface with WYSIWYG editors
3. **Add Screenshots**: Upload multiple before/after images
4. **Export PDF**: Click "Export PDF" for professional formatted output

## 🔧 System Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Memory**: 256MB recommended for PDF generation
- **Composer**: For dependency management

## 📝 What's New in v2.0.0

- ✅ **DOMpdf Integration**: Professional PDF generation
- ✅ **WPMU DEV Styling**: Complete brand makeover
- ✅ **Multiple Screenshots**: Enhanced media management
- ✅ **WYSIWYG Editors**: Rich text editing capabilities
- ✅ **Improved Performance**: Optimized code structure
- ✅ **Better Error Handling**: Graceful fallbacks
- ✅ **Responsive Design**: Mobile-friendly interface

## 🐛 Troubleshooting

### PDF Generation Issues

1. **Memory Errors**: Increase PHP memory limit to 256MB
   ```php
   ini_set('memory_limit', '256M');
   ```

2. **Permission Errors**: Ensure proper file permissions
   ```bash
   chown -R www-data:www-data /path/to/plugin/
   ```

3. **Missing Fonts**: Install system fonts
   ```bash
   # Ubuntu/Debian
   sudo apt-get install fonts-liberation fonts-dejavu-core
   
   # CentOS/RHEL
   sudo yum install liberation-fonts dejavu-sans-fonts
   ```

### No PDF Generated

If DOMpdf is not available, the plugin will output HTML with instructions for manual PDF conversion using the browser's "Print to PDF" function.

## 🔄 Updates

- **Current Version**: 2.0.0
- **Last Updated**: October 2, 2025
- **Compatibility**: WordPress 5.0 - 6.3+

## 📞 Support

For support and customization requests, contact WPMU DEV.

---

**Developed by WPMU DEV** | Professional WordPress Solutions