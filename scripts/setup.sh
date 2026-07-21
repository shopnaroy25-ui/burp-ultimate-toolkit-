#!/bin/bash
# Setup script for local development

echo "🛠️ Setting up Burp Ultimate Toolkit..."

# Install Docker if not present
if ! command -v docker &> /dev/null; then
    echo "Installing Docker..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sudo sh get-docker.sh
fi

# Install Docker Compose if not present
if ! command -v docker-compose &> /dev/null; then
    echo "Installing Docker Compose..."
    sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    sudo chmod +x /usr/local/bin/docker-compose
fi

# Start services
echo "Starting Docker services..."
docker-compose up -d

echo "✅ Setup complete!"
echo "🌐 Application: http://localhost:8080"
echo "📊 WebSocket: ws://localhost:8443"
