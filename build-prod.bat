
@echo off
SETLOCAL ENABLEEXTENSIONS

ECHO Cleaning previous build...
IF EXIST build RMDIR /S /Q build
MKDIR build

ECHO Installing production dependencies (no dev)...
call composer install --no-dev --classmap-authoritative --optimize-autoloader
IF %ERRORLEVEL% NEQ 0 (
    ECHO Composer install failed!
    EXIT /B 1
)

ECHO Creating ZIP archive...
call composer archive --format=zip --dir=build
IF %ERRORLEVEL% NEQ 0 (
    ECHO Composer archive failed!
    EXIT /B 1
)

ECHO Production build completed. Archive is in .\build
PAUSE
ENDLOCAL
