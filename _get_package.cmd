@ECHO OFF
SET PATH=%PATH%;%CD%\bin\main;%CD%\bin\php;%CD%\bin\imagick
php "%CD%\includes\main.php" --put-version
php "%CD%\includes\main.php" --guard-generate
IF EXIST "%CD%\AVE-PHP.7z" DEL /Q /A "%CD%\AVE-PHP.7z"
7z a -mx9 -t7z "%CD%\AVE-PHP.7z" "bin" "includes" "meta" "config" "AVE-PHP.cmd" "AVE.ave-guard" "README.md" "LICENSE" -x!"config\user.ini"
PAUSE