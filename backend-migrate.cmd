@echo off
setlocal
set PHP_BIN=%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe
if not exist "%PHP_BIN%" set PHP_BIN=php
"%PHP_BIN%" -c ".local\php.ini" "backend\database\migrate.php"
