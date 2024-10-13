#define MyAppName "NGC-TOOLKIT"
#define MyAppVersion "2.3.2"
#define MyAppPublisher "Abyss Morgan"
#define MyAppURL "https://github.com/AbyssMorgan"
#define MyAppExeName "Toolkit.cmd"

[Setup]
ArchitecturesInstallIn64BitMode=x64
AppId={{5B983545-1D38-47CD-9890-0926E0E312CD}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppVerName={#MyAppName}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
VersionInfoVersion={#MyAppVersion}.0
VersionInfoDescription=NGC-TOOLKIT v{#MyAppVersion}
DefaultDirName={autopf}\NGC-TOOLKIT
DisableDirPage=no
DefaultGroupName={#MyAppName}
OutputDir={#SourcePath}\Setup
OutputBaseFilename=NGC-TOOLKIT_v{#MyAppVersion}
Compression=lzma
SolidCompression=yes
WizardStyle=modern
Uninstallable=yes
SetupIconFile={#SourcePath}\NGC-TOOLKIT.ico
DisableProgramGroupPage=yes

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"; Flags: unchecked

[Files]
Source: "{#SourcePath}\includes\*"; DestDir: "{app}\includes"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#SourcePath}\vendor\*"; DestDir: "{app}\vendor"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#SourcePath}\bin\*.cmd"; DestDir: "{app}\bin"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#SourcePath}\LICENSE"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourcePath}\NGC-TOOLKIT.ico"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourcePath}\Changelog.txt"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourcePath}\composer.json"; DestDir: "{app}"; Flags: ignoreversion

[Icons]
Name: "{userdesktop}\{#MyAppName}"; Filename: "{cmd}"; Parameters: "/c ""{app}\bin\{#MyAppExeName}"""; WorkingDir: "{app}"; IconFilename: "{app}\NGC-TOOLKIT.ico"; Tasks: desktopicon

[Run]
Filename: "{app}\bin\{#MyAppExeName}"; Description: "{cm:LaunchProgram,{#StringChange(MyAppName, '&', '&&')}}"; Flags: nowait postinstall skipifsilent
