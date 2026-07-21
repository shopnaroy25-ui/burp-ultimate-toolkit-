#!/bin/bash
# Deploy script for Render

echo "🚀 Starting Burp Ultimate Toolkit deployment..."

# Set up directories
mkdir -p backend/storage/{requests,certs,logs,cache,training}
chmod -R 755 backend/storage

# Generate SSL certificates if needed
if [ ! -f backend/storage/certs/ca.crt ]; then
    echo "🔐 Generating SSL certificates..."
    openssl req -x509 -newkey rsa:2048 -nodes -keyout backend/storage/certs/ca.key -out backend/storage/certs/ca.crt -days 3650 -subj "/CN=Burp Toolkit CA"
fi

# Install PHP dependencies
if [ -f composer.json ]; then
    echo "📦 Installing PHP dependencies..."
    composer install --no-dev --optimize-autoloader
fi

# Install frontend dependencies
if [ -f package.json ]; then
    echo "📦 Installing frontend dependencies..."
    npm install
    npm run build
fi

# Set environment
if [ -f .env ]; then
    echo "⚙️  Setting up environment..."
    export $(cat .env | grep -v '^#' | xargs)
fi

echo "✅ Deployment complete!"
echo "🌐 Application running at: http://localhost:8080"
