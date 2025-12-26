#!/bin/bash
# Generate API Documentation using phpDocumentor
# Run this from the project root: ./docs/generate-api-docs.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_ROOT"

echo "=== Control Tower API Documentation Generator ==="
echo ""

# Check if phpDocumentor PHAR exists
if [ ! -f "bin/phpDocumentor.phar" ]; then
    echo "phpDocumentor PHAR not found. Downloading..."
    mkdir -p bin
    curl -L https://phpdoc.org/phpDocumentor.phar -o bin/phpDocumentor.phar
    chmod +x bin/phpDocumentor.phar
fi

# Create output directory
mkdir -p docs/api

# Run phpDocumentor
echo "Generating API documentation..."
php bin/phpDocumentor.phar run -v

echo ""
echo "=== Documentation Generated ==="
echo "Open docs/api/index.html in your browser to view."
echo ""
