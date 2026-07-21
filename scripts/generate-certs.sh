#!/bin/bash
# SSL Certificate generation script

CERT_DIR="backend/storage/certs"
mkdir -p "$CERT_DIR"

echo "🔐 Generating SSL certificates..."

# Generate CA
openssl genrsa -out "$CERT_DIR/ca.key" 2048
openssl req -new -x509 -days 3650 -key "$CERT_DIR/ca.key" -out "$CERT_DIR/ca.crt" -subj "/C=US/ST=California/L=San Francisco/O=Burp Toolkit/CN=Burp Toolkit CA"

# Generate domain certificate
openssl genrsa -out "$CERT_DIR/server.key" 2048
openssl req -new -key "$CERT_DIR/server.key" -out "$CERT_DIR/server.csr" -subj "/C=US/ST=California/L=San Francisco/O=Burp Toolkit/CN=localhost"
openssl x509 -req -days 365 -in "$CERT_DIR/server.csr" -CA "$CERT_DIR/ca.crt" -CAkey "$CERT_DIR/ca.key" -set_serial 01 -out "$CERT_DIR/server.crt"

# Clean up
rm "$CERT_DIR/server.csr"

echo "✅ Certificates generated in $CERT_DIR/"
ls -la "$CERT_DIR/"
