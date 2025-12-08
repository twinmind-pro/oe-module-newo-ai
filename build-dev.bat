@echo off
SETLOCAL ENABLEEXTENSIONS

ECHO Installing development dependencies...
composer install --optimize-autoloader

ECHO Development environment ready.
ENDLOCAL
