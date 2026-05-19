# BioTern Machine Connector

The connector supports two Windows builds:

- x86: default build, uses `DevCtrl.dll`.
- x64: optional build, uses `DevCtrl.x64.dll` from the biometric device SDK.

The current bundled `DevCtrl.dll` is x86. A real x64 connector cannot talk to the device until the vendor x64 SDK DLL is added as:

```text
BioTern/tools/device_connector/DevCtrl.x64.dll
```

Build commands:

```powershell
dotnet build .\BioTernMachineConnector.csproj -c Release
.\build-x64.ps1
```

The x64 output is created in:

```text
bin/Release/net9.0-windows/win-x64/
```
