@echo off
echo ========================================
echo   PUSH TO GITHUB - Menstrual App
echo ========================================
echo.
echo IMPORTANTE: Kailangan mo munang gumawa ng repository sa GitHub!
echo.
echo Steps:
echo 1. Pumunta sa github.com at gumawa ng bagong repository
echo 2. Kopyahin ang repository URL (halimbawa: https://github.com/username/Menstrual.git)
echo 3. I-paste dito ang URL mo
echo.
set /p REPO_URL="Ilagay ang GitHub repository URL: "

if "%REPO_URL%"=="" (
    echo Error: Walang URL na na-provide!
    pause
    exit /b 1
)

echo.
echo I-connect ang local repository sa GitHub...
git remote add origin %REPO_URL% 2>nul
if errorlevel 1 (
    echo Warning: Remote origin may already exist. I-update...
    git remote set-url origin %REPO_URL%
)

echo.
echo I-upload ang files sa GitHub...
echo (Hihingin ang username at password/token mo)
git push -u origin main

if errorlevel 1 (
    echo.
    echo ========================================
    echo ERROR: Hindi na-upload ang files
    echo ========================================
    echo.
    echo Posibleng dahilan:
    echo 1. Mali ang repository URL
    echo 2. Walang access sa repository
    echo 3. Kailangan ng Personal Access Token (hindi regular password)
    echo.
    echo Para sa Personal Access Token:
    echo GitHub ^> Settings ^> Developer settings ^> Personal access tokens
    echo.
) else (
    echo.
    echo ========================================
    echo SUCCESS! Na-upload na ang files!
    echo ========================================
    echo.
)

pause

