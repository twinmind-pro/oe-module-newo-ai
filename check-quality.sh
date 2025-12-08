#!/bin/bash
set -e

echo "Running PHP CodeSniffer (PSR-12 standard)..."
composer exec phpcs -- --standard=PSR12 src
if [ $? -ne 0 ]; then
  echo "CodeSniffer found issues. Fix them before proceeding."
  exit 1
fi

echo "Running PHPStan static analysis (level max)..."
composer exec phpstan -- analyse src --level=max
if [ $? -ne 0 ]; then
  echo "PHPStan found issues. Fix them before proceeding."
  exit 1
fi

echo "Quality checks passed successfully."
