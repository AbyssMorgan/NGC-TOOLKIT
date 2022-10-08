@ECHO OFF
chcp 65001
SET PATH=%PATH%;%CD%\php;%CD%\bin
FOR /F "tokens=*" %%s IN ('php "%CD%\includes\main.php" GET_COLOR') DO COLOR %%s
php "%CD%\includes\main.php
ECHO Test mode no exit
pause
GOTO :eof