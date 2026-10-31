@echo off
setlocal
cd /d "%~dp0"
set "CASTING_CMS_DATA_DIR=%LOCALAPPDATA%\7rokh-content-admin\runtime"
set "CASTING_CMS_PHP=php"
set "CASTING_CMS_PHP_ARGS="
where php >nul 2>nul
if errorlevel 1 (
  set "CASTING_CMS_PHP=D:\FaraFarda\.tools\php\php.exe"
  set "CASTING_CMS_PHP_ARGS=-d extension=D:\FaraFarda\.tools\php\ext\php_gd.dll"
)
if not exist "%CASTING_CMS_PHP%" if not "%CASTING_CMS_PHP%"=="php" (
  echo Set CASTING_CMS_PHP to a supported PHP 8.4+ executable.
  pause
  exit /b 1
)
echo Content admin: http://127.0.0.1:8791/casting-portal/cast-admin/
echo Site preview also requires a local WordPress at ..\wp-load.php.
start "" "http://127.0.0.1:8791/casting-portal/cast-admin/"
"%CASTING_CMS_PHP%" %CASTING_CMS_PHP_ARGS% -S 127.0.0.1:8791 -t "%~dp0" "%~dp0tools\cms-router.php"
pause
