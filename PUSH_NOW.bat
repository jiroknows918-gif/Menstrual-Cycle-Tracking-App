@echo off
cls
echo ============================================
echo   PUSH TO GITHUB - Menstrual App
echo ============================================
echo.
echo Repository URL: https://github.com/jiroknows918-gif/Menstrual-Cycle-Tracking-App.git
echo.
echo I-upload ang files sa GitHub...
echo.
echo IMPORTANTE: 
echo - Username: jiroknows918-gif
echo - Password: Gamitin ang Personal Access Token (hindi regular password)
echo.
echo ============================================
echo.
git push -u origin main

if errorlevel 1 (
    echo.
    echo ============================================
    echo ERROR: Hindi na-upload
    echo ============================================
    echo.
    echo Posibleng dahilan:
    echo 1. Mali ang username o password
    echo 2. Kailangan ng Personal Access Token
    echo.
    echo Para sa Personal Access Token:
    echo 1. GitHub ^> Settings ^> Developer settings
    echo 2. Personal access tokens ^> Tokens (classic)
    echo 3. Generate new token (classic)
    echo 4. Check 'repo' permission
    echo 5. Generate at kopyahin ang token
    echo.
    echo I-run ulit ang script na ito pagkatapos.
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

