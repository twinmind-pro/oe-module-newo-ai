#!/bin/bash
set -e

echo "Installing development dependencies..."
composer install --optimize-autoloader

echo "Development environment ready."
