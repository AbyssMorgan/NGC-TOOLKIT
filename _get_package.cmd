@ECHO OFF
CD /D "%~dp0"
SET PATH=%PATH%;%CD%\bin\main;%CD%\bin\php;%CD%\bin\imagick
"%CD%\bin\php\php.exe" "%CD%\includes\main.php" --sort-settings
"%CD%\bin\php\php.exe" "%CD%\includes\main.php" --put-version
FOR /F "tokens=*" %%s IN ('TYPE "%CD%\version"') DO SET _VERSION=%%s
"%CD%\bin\main\7z.exe" a -mx9 -t7z "%CD%\AVE-PHP_v%_VERSION%.7z" "bin" "includes" "commands" "AVE-PHP.cmd" "README.md" "LICENSE"
PAUSE
