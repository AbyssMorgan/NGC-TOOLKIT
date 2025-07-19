#define MyAppName "NGC-TOOLKIT"
#define MyAppVersion "2.7.1"
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
Source: "{#SourcePath}\bin\*.cmd"; DestDir: "{app}\bin"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#SourcePath}\LICENSE"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourcePath}\NGC-TOOLKIT.ico"; DestDir: "{app}"; Flags: ignoreversion
Source: "{#SourcePath}\Changelog.txt"; DestDir: "{app}"; Flags: ignoreversion

[Icons]
Name: "{userdesktop}\{#MyAppName}"; Filename: "{cmd}"; Parameters: "/c ""{app}\bin\{#MyAppExeName}"""; WorkingDir: "{app}"; IconFilename: "{app}\NGC-TOOLKIT.ico"; Tasks: desktopicon

[Run]
Filename: "{app}\bin\{#MyAppExeName}"; Description: "{cm:LaunchProgram,{#StringChange(MyAppName, '&', '&&')}}"; Flags: nowait postinstall skipifsilent

[Registry]
Root: HKCR; Subkey: ".ngcs"; ValueType: string; ValueName: ""; ValueData: "NGC.SCRIPT"; Flags: uninsdeletevalue
Root: HKCR; Subkey: "NGC.SCRIPT"; ValueType: string; ValueName: ""; ValueData: "{#MyAppName} Script"; Flags: uninsdeletekey
Root: HKCR; Subkey: "NGC.SCRIPT\DefaultIcon"; ValueType: string; ValueName: ""; ValueData: """{app}\NGC-TOOLKIT.ico"""
Root: HKCR; Subkey: "NGC.SCRIPT\shell"; Flags: uninsdeletekeyifempty
Root: HKCR; Subkey: "NGC.SCRIPT\shell\open"; Flags: uninsdeletekeyifempty
Root: HKCR; Subkey: "NGC.SCRIPT\shell\open\command"; ValueType: string; ValueName: ""; ValueData: """{app}\bin\Script.cmd"" ""%1"" %*"

[Code]
procedure DeleteOldFiles;
begin
  DelTree(ExpandConstant('{app}\includes'), True, True, True);
  DelTree(ExpandConstant('{app}\vendor'), True, True, True);
  DelTree(ExpandConstant('{app}\bin'), True, True, True);
end;

procedure CurStepChanged(CurStep: TSetupStep);
begin
  if CurStep = ssInstall then
    DeleteOldFiles;
end;
