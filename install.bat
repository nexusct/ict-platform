@echo off
setlocal enabledelayedexpansion

REM =============================================
REM     ICT Platform Installation Script
REM              (Windows Version)
REM =============================================

title ICT Platform Installer

echo.
echo ===============================================================
echo.
echo          ICT PLATFORM INSTALLATION SCRIPT
echo.
echo ===============================================================
echo.

set "HAS_ERRORS=0"
set "SCRIPT_DIR=%~dp0"

REM =============================================
REM STEP 1: Check Prerequisites
REM =============================================
echo ---------------------------------------------------------------
echo   STEP 1: Checking if required programs are installed...
echo ---------------------------------------------------------------
echo.

REM Check for Node.js
where node >nul 2>nul
if %errorlevel% equ 0 (
    for /f "tokens=*" %%i in ('node --version') do set NODE_VER=%%i
    echo [OK] Node.js is installed ^(version: !NODE_VER!^)
) else (
    echo [ERROR] Node.js is NOT installed!
    echo         Please install from: https://nodejs.org/
    echo         Download the LTS version ^(the one that says 'Recommended'^)
    set "HAS_ERRORS=1"
)

REM Check for npm
where npm >nul 2>nul
if %errorlevel% equ 0 (
    for /f "tokens=*" %%i in ('npm --version') do set NPM_VER=%%i
    echo [OK] npm is installed ^(version: !NPM_VER!^)
) else (
    echo [ERROR] npm is NOT installed!
    echo         npm comes with Node.js. Please install Node.js first.
    set "HAS_ERRORS=1"
)

REM Check for PHP
where php >nul 2>nul
if %errorlevel% equ 0 (
    for /f "tokens=1,2" %%i in ('php --version') do (
        if "%%i"=="PHP" set PHP_VER=%%j
    )
    echo [OK] PHP is installed ^(version: !PHP_VER!^)
) else (
    echo [WARNING] PHP is NOT installed ^(optional for development^)
)

REM Check for Composer
where composer >nul 2>nul
if %errorlevel% equ 0 (
    echo [OK] Composer is installed
) else (
    echo [WARNING] Composer is NOT installed ^(optional for PHP development^)
    echo          Install from: https://getcomposer.org/download/
)

echo.

REM If critical errors, ask if user wants to continue
if "!HAS_ERRORS!"=="1" (
    echo.
    echo [ERROR] Some required programs are missing!
    echo.
    set /p CONTINUE="Do you want to continue anyway? (y/n): "
    if /i not "!CONTINUE!"=="y" (
        echo.
        echo Installation cancelled. Please install the missing programs first.
        pause
        exit /b 1
    )
)

REM =============================================
REM STEP 2: Install WordPress Plugin JS Dependencies
REM =============================================
echo.
echo ---------------------------------------------------------------
echo   STEP 2: Installing WordPress Plugin ^(JavaScript packages^)...
echo ---------------------------------------------------------------
echo.

set "WP_PLUGIN_DIR=%SCRIPT_DIR%wp-ict-platform"

if exist "%WP_PLUGIN_DIR%" (
    cd /d "%WP_PLUGIN_DIR%"

    echo Installing npm packages... ^(this may take a few minutes^)
    echo.

    call npm install
    if %errorlevel% equ 0 (
        echo.
        echo [OK] JavaScript packages installed successfully!
    ) else (
        echo.
        echo [ERROR] Failed to install JavaScript packages!
        set "HAS_ERRORS=1"
    )

    cd /d "%SCRIPT_DIR%"
) else (
    echo [ERROR] WordPress plugin directory not found: %WP_PLUGIN_DIR%
    set "HAS_ERRORS=1"
)

echo.

REM =============================================
REM STEP 3: Install WordPress Plugin PHP Dependencies
REM =============================================
echo ---------------------------------------------------------------
echo   STEP 3: Installing WordPress Plugin ^(PHP packages^)...
echo ---------------------------------------------------------------
echo.

if exist "%WP_PLUGIN_DIR%" (
    cd /d "%WP_PLUGIN_DIR%"

    where composer >nul 2>nul
    if %errorlevel% equ 0 (
        echo Installing Composer packages...
        echo.

        call composer install --no-interaction
        if %errorlevel% equ 0 (
            echo.
            echo [OK] PHP packages installed successfully!
        ) else (
            echo.
            echo [WARNING] Failed to install PHP packages ^(may not be critical^)
        )
    ) else (
        echo [WARNING] Skipping PHP packages ^(Composer not installed^)
    )

    cd /d "%SCRIPT_DIR%"
)

echo.

REM =============================================
REM STEP 4: Build WordPress Plugin Assets
REM =============================================
echo ---------------------------------------------------------------
echo   STEP 4: Building WordPress Plugin assets...
echo ---------------------------------------------------------------
echo.

if exist "%WP_PLUGIN_DIR%" (
    cd /d "%WP_PLUGIN_DIR%"

    echo Building production assets... ^(this may take a minute^)
    echo.

    call npm run build
    if %errorlevel% equ 0 (
        echo.
        echo [OK] WordPress plugin built successfully!
    ) else (
        echo.
        echo [ERROR] Failed to build WordPress plugin!
        set "HAS_ERRORS=1"
    )

    cd /d "%SCRIPT_DIR%"
)

echo.

REM =============================================
REM STEP 5: Install Mobile App Dependencies
REM =============================================
echo ---------------------------------------------------------------
echo   STEP 5: Installing Mobile App packages...
echo ---------------------------------------------------------------
echo.

set "MOBILE_APP_DIR=%SCRIPT_DIR%ict-mobile-app"

if exist "%MOBILE_APP_DIR%" (
    cd /d "%MOBILE_APP_DIR%"

    echo Installing npm packages for mobile app...
    echo.

    call npm install
    if %errorlevel% equ 0 (
        echo.
        echo [OK] Mobile app packages installed successfully!
    ) else (
        echo.
        echo [ERROR] Failed to install mobile app packages!
        set "HAS_ERRORS=1"
    )

    cd /d "%SCRIPT_DIR%"
) else (
    echo [WARNING] Mobile app directory not found ^(skipping^): %MOBILE_APP_DIR%
)

echo.

REM =============================================
REM STEP 6: Final Summary
REM =============================================
echo ---------------------------------------------------------------
echo   STEP 6: Installation Complete!
echo ---------------------------------------------------------------
echo.

if "!HAS_ERRORS!"=="0" (
    echo ===============================================================
    echo.
    echo        ALL PACKAGES INSTALLED SUCCESSFULLY!
    echo.
    echo ===============================================================
) else (
    echo ===============================================================
    echo.
    echo     INSTALLATION COMPLETED WITH SOME WARNINGS
    echo.
    echo ===============================================================
)

echo.
echo What you can do next:
echo.
echo   WordPress Plugin:
echo     - Copy the 'wp-ict-platform' folder to your WordPress plugins directory
echo     - Go to WordPress Admin ^> Plugins ^> Activate 'ICT Platform'
echo.
echo   Mobile App:
echo     - cd ict-mobile-app
echo     - npm start ^(to run the development server^)
echo.
echo   Development Commands:
echo     - npm run dev    - Watch mode for development
echo     - npm run build  - Build for production
echo     - npm run test   - Run tests
echo     - npm run lint   - Check code style
echo.

pause
exit /b 0
