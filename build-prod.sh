#!/bin/bash
set -e

echo "Cleaning previous build..."
rm -rf build
mkdir build

echo "Installing production dependencies (no dev)..."
composer install --no-dev --classmap-authoritative --optimize-autoloader

echo "Creating ZIP archive..."
composer archive --format=zip --dir=build

echo "Production build completed. Archive is in ./build"
