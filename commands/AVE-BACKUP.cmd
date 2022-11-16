@ECHO OFF
chcp 65001
CD /D "%~dp0\.."
CLS
SET PATH=%PATH%;%CD%\bin\main;%CD%\bin\php;%CD%\bin\imagick
COLOR 9F
"%CD%\bin\php\php.exe" "%CD%\includes\main.php" --make-backup "%~1"
pause
GOTO :eof
