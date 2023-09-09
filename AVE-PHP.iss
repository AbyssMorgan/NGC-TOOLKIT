#define MyAppName "AVE-PHP"
#define MyAppVersion "1.9.2"
#define MyAppPublisher "Abyss Morgan"
#define MyAppURL "https://github.com/AbyssMorgan"
#define MyAppExeName "AVE-PHP.cmd"

[Setup]
ArchitecturesInstallIn64BitMode=x64
AppId={{2758257E-4810-4342-8230-A2176ED1A833}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppVerName={#MyAppName}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
VersionInfoVersion={#MyAppVersion}.0
VersionInfoDescription=AVE-PHP v{#MyAppVersion}
DefaultDirName={autopf}\AVE-PHP
DisableDirPage=no
DefaultGroupName={#MyAppName}
OutputDir={#SourcePath}\Setup
OutputBaseFilename=AVE-PHP_v{#MyAppVersion}
Compression=lzma
SolidCompression=yes
WizardStyle=modern
Uninstallable=yes
SetupIconFile={#SourcePath}\ave-php.ico
DisableProgramGroupPage=yes

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"; Flags: unchecked

[Files]
Source: "{#SourcePath}\includes\*"; DestDir: "{app}\includes"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#SourcePath}\vendor\*"; DestDir: "{app}\vendor"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#SourcePath}\commands\*"; DestDir: "{app}\commands"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#SourcePath}\AVE-PHP.cmd"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourcePath}\LICENSE"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourcePath}\ave-php.ico"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourcePath}\ave-php.png"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourcePath}\Changelog.txt"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourcePath}\example.ave-php"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourcePath}\composer.json"; DestDir: "{app}"; Flags: ignoreversion

[Icons]
Name: "{userdesktop}\{#MyAppName}"; Filename: "{cmd}"; Parameters: "/c ""{app}\{#MyAppExeName}"""; WorkingDir: "{app}"; IconFilename: "{app}\ave-php.ico"; Tasks: desktopicon

[Run]
Filename: "{app}\{#MyAppExeName}"; Description: "{cm:LaunchProgram,{#StringChange(MyAppName, '&', '&&')}}"; Flags: nowait postinstall skipifsilent
