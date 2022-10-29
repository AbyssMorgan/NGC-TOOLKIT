@ECHO OFF
SET PATH=%PATH%;%CD%\bin
7z a -mx9 -t7z "AVE-PHP.7z" "%CD%\bin" "%CD%\includes" "%CD%\meta" "%CD%\config\default.ini" "%CD%\config\mkvmerge.ini"
PAUSE