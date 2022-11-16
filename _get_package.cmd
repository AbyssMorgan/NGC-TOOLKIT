@ECHO OFF
CD /D "%~dp0"
SET PATH=%PATH%;%CD%\bin\main;%CD%\bin\php;%CD%\bin\imagick
"%CD%\bin\php\php.exe" "%CD%\includes\main.php" --put-version
"%CD%\bin\php\php.exe" "%CD%\includes\main.php" --guard-generate
IF EXIST "%CD%\AVE-PHP.7z" DEL /Q /A "%CD%\AVE-PHP.7z"
"%CD%\bin\main\7z.exe" a -mx9 -t7z "%CD%\AVE-PHP.7z" "bin" "includes" "config" "commands" "AVE-PHP.cmd" "AVE.ave-guard" "README.md" "LICENSE" -x!"config\user.ini" -x!"config\mysql"
PAUSE
