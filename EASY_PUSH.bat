@echo off
cls
echo ============================================
echo   PUSH TO GITHUB - Menstrual App
echo ============================================
echo.
echo Kopyahin ang repository URL mo mula sa GitHub
echo (Halimbawa: https://github.com/username/Menstrual-Cycle-Tracking-App.git)
echo.
set /p REPO_URL="Ilagay ang repository URL: "

if "%REPO_URL%"=="" (
    echo.
    echo ERROR: Walang URL na na-provide!
    pause
    exit /b 1
)

echo.
echo I-connect ang local repository sa GitHub...
git remote add origin %REPO_URL% 2>nul
if errorlevel 1 (
    echo Updating existing remote...
    git remote set-url origin %REPO_URL%
)

echo.
echo I-upload ang files sa GitHub...
echo.
echo IMPORTANTE: 
echo - Username: Ilagay ang GitHub username mo
echo - Password: Gamitin ang Personal Access Token (hindi regular password)
echo.
git push -u origin main

if errorlevel 1 (
    echo.
    echo ============================================
    echo ERROR: Hindi na-upload
    echo ============================================
    echo.
    echo Posibleng dahilan:
    echo 1. Mali ang repository URL
    echo 2. Kailangan ng Personal Access Token
    echo.
    echo Para sa Personal Access Token:
    echo GitHub ^> Settings ^> Developer settings ^> Personal access tokens
    echo Generate new token ^> Check 'repo' permission
    echo.
) else (
    echo.
    echo ============================================
    echo SUCCESS! Na-upload na ang lahat ng files!
    echo ============================================
    echo.
    echo I-refresh ang GitHub page para makita ang files.
    echo.
)

pause

