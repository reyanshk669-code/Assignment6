@echo off
title OmniMart Multi-Vendor Platform Launcher
color 0b
echo ==========================================================
echo          OmniMart Multi-Vendor Platform Launcher
echo ==========================================================
echo.

:: 1. Attempt to start MySQL automatically if installed in XAMPP
if exist "C:\xampp\mysql_start.bat" (
    echo [*] Checking MySQL service...
    netstat -ano | findstr ":3306" >nul 2>&1
    if %ERRORLEVEL% NEQ 0 (
        echo [*] Starting MySQL in the background...
        start "" /min "C:\xampp\mysql_start.bat"
        timeout /t 3 >nul
    ) else (
        echo [*] MySQL is already running!
    )
)

:: 2. Find PHP Binary
set PHP_BIN=php
where php >nul 2>&1
if %ERRORLEVEL% EQU 0 goto RUN_SERVER

if exist "C:\xampp\php\php.exe" (
    set PHP_BIN=C:\xampp\php\php.exe
    goto RUN_SERVER
)

if exist "C:\laragon\bin\php" (
    for /f "delims=" %%I in ('dir /b /ad /o-n "C:\laragon\bin\php\php-*" 2^>nul') do (
        if exist "C:\laragon\bin\php\%%I\php.exe" (
            set PHP_BIN=C:\laragon\bin\php\%%I\php.exe
            goto RUN_SERVER
        )
    )
)

echo [!] PHP is not found on your system.
echo Please ensure XAMPP is installed at C:\xampp
pause
exit

:RUN_SERVER
echo [*] Found PHP: %PHP_BIN%
echo [*] Launching OmniMart at http://localhost:8000 ...
echo.
timeout /t 1 >nul
start "" "http://localhost:8000/setup.php"
"%PHP_BIN%" -S localhost:8000
pause
