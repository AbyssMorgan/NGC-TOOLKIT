@ECHO OFF
chcp 65001
CLS
SET PATH=%PATH%;%CD%\bin\main;%CD%\bin\php;%CD%\bin\imagick
COLOR 9F
"%CD%\bin\php\php.exe" "%CD%\includes\main.php" --interactive
GOTO :eof
