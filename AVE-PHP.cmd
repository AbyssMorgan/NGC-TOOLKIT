@ECHO OFF
SET PATH=%PATH%;%CD%\php
FOR /F "tokens=*" %%s IN ('php "%CD%\includes\main.php" GET_COLOR') DO COLOR %%s

:_CMD
php "%CD%\includes\main.php
pause
GOTO :eof