@ECHO OFF
chcp 65001
CLS
SET PATH=%PATH%;%CD%\bin;%CD%\bin\php;%CD%\bin\imagick;%CD%\bin\ffmpeg;%CD%\bin\mtn
FOR /F "tokens=*" %%s IN ('php "%CD%\includes\main.php" GET_COLOR') DO COLOR %%s
php "%CD%\includes\main.php"
GOTO :eof