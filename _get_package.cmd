@ECHO OFF
SET PATH=%PATH%;%CD%\bin
IF EXIST "%CD%\AVE-PHP.7z" DEL /Q /A "%CD%\AVE-PHP.7z"
7z a -mx9 -t7z "%CD%\AVE-PHP.7z" "bin" "includes" "meta" "config" "AVE-PHP.cmd" -x!"config\user.ini"
PAUSE