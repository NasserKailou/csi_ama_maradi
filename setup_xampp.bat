@echo off
REM ================================================================
REM  Setup XAMPP – Système CSI AMA Maradi
REM  Exécuter en tant qu'Administrateur depuis le dossier du projet
REM ================================================================

title CSI AMA Maradi – Installation XAMPP

set PROJECT_DIR=%~dp0
set XAMPP_PHP=C:\xampp\php\php.exe
set XAMPP_MYSQL=C:\xampp\mysql\bin\mysql.exe

echo.
echo ================================================================
echo   Systeme CSI AMA Maradi – Installation XAMPP (Windows)
echo ================================================================
echo.

REM -- Vérifier XAMPP --
if not exist "%XAMPP_PHP%" (
    echo [ERR] PHP non trouve dans %XAMPP_PHP%
    echo       Verifiez que XAMPP est installe dans C:\xampp
    echo       Ou modifiez le chemin XAMPP_PHP dans ce script.
    pause
    exit /b 1
)

echo [OK]  PHP trouve : %XAMPP_PHP%

REM -- Copier .env.example en .env si nécessaire --
if not exist "%PROJECT_DIR%.env" (
    echo [..]  Copie de .env.example vers .env...
    copy "%PROJECT_DIR%.env.example" "%PROJECT_DIR%.env" > nul
    echo [OK]  Fichier .env cree. EDITEZ ce fichier si necessaire.
)

REM -- Installer les dépendances Composer si disponible --
if exist "C:\xampp\php\composer.phar" (
    echo [..]  Installation des dependances Composer (TCPDF)...
    cd /d "%PROJECT_DIR%"
    "%XAMPP_PHP%" C:\xampp\php\composer.phar install --no-dev --optimize-autoloader
    echo [OK]  Composer termine.
) else (
    echo [!!]  Composer non trouve. TCPDF peut ne pas etre disponible.
    echo       Les recus seront generes en HTML (mode de secours).
)

REM -- Exécuter le script d'installation PHP --
echo.
echo [..]  Lancement du script d'installation...
echo.
"%XAMPP_PHP%" "%PROJECT_DIR%install.php"

if errorlevel 1 (
    echo.
    echo [ERR] L'installation a echoue. Verifiez les messages ci-dessus.
    echo       Assurez-vous que MySQL est en cours d'execution dans XAMPP.
    pause
    exit /b 1
)

echo.
echo ================================================================
echo   Installation terminee !
echo.
echo   Ouvrez votre navigateur :
echo   http://localhost/csi_ama_maradi/
echo ================================================================
echo.
pause
