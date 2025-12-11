#!/bin/bash
# Setup script for cPanel after pulling from GitHub
# Run this script in cPanel Terminal after git pull

echo "Setting up Laravel application on cPanel..."

# Create storage directories
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p storage/app/public

# Create public upload directories
mkdir -p public/uploads/business_logos
mkdir -p public/uploads/documents
mkdir -p public/uploads/img
mkdir -p public/uploads/media
mkdir -p public/uploads/invoice_logos
mkdir -p public/uploads/UltimatePOS
mkdir -p public/uploads/temp
mkdir -p public/uploads/cms

# Set proper permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chmod -R 775 public/uploads

# Create .env file from example if it doesn't exist
if [ ! -f .env ]; then
    cp .env.example .env
    echo ".env file created. Please update with production settings."
fi

# Install composer dependencies (if composer is available)
if command -v composer &> /dev/null; then
    echo "Installing composer dependencies..."
    composer install --no-dev --optimize-autoloader
fi

# Generate application key if needed
php artisan key:generate

# Clear and cache config
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Create storage link
php artisan storage:link

echo "Setup complete!"
echo "Don't forget to:"
echo "1. Update .env with production database credentials"
echo "2. Upload any existing user files to public/uploads/"
echo "3. Run: php artisan migrate (if needed)"
