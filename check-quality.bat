
@echo off
SETLOCAL ENABLEEXTENSIONS

ECHO Running PHP CodeSniffer (PSR-12 standard)...
call composer exec phpcs -- --standard=PSR12 src
IF %ERRORLEVEL% NEQ 0 (
    ECHO CodeSniffer found issues. Fix them before proceeding.
    EXIT /B 1
)

ECHO Running PHPStan static analysis (level max)...
call composer exec phpstan -- analyse src --level=max
IF %ERRORLEVEL% NEQ 0 (
    ECHO PHPStan found issues. Fix them before proceeding.
    EXIT /B 1
)

ECHO Quality checks passed successfully.
ENDLOCAL
