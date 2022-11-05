@ECHO OFF
chcp 65001
CLS
SET PATH=%PATH%;%CD%\bin\main;%CD%\bin\php;%CD%\bin\imagick
FOR /F "tokens=*" %%s IN ('php "%CD%\includes\main.php" --get-color') DO COLOR %%s
php "%CD%\includes\main.php" --interactive
GOTO :eof