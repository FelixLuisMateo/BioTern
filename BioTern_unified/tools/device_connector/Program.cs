using System.Runtime.InteropServices;
using System.Text;
using System.Text.Json;

if (args.Length < 1)
{
    Console.Error.WriteLine("Usage: BioTernMachineConnector <config-json-path>");
    return 2;
}

string configPath = Path.GetFullPath(args[0]);
if (!File.Exists(configPath))
{
    Console.Error.WriteLine($"Config file not found: {configPath}");
    return 2;
}

MachineConfig? config;
try
{
    config = JsonSerializer.Deserialize<MachineConfig>(
        File.ReadAllText(configPath),
        new JsonSerializerOptions { PropertyNameCaseInsensitive = true }
    );
}
catch (Exception ex)
{
    Console.Error.WriteLine($"Failed to read config: {ex.Message}");
    return 2;
}

if (config is null || string.IsNullOrWhiteSpace(config.IpAddress))
{
    Console.Error.WriteLine("Invalid connector configuration.");
    return 2;
}

int handle = 0;
try
{
    handle = DevCtrlApi.D_Connect(
        config.IpAddress.Trim(),
        config.Port <= 0 ? 5005 : config.Port,
        (ushort)(config.DeviceNumber <= 0 ? 1 : config.DeviceNumber),
        config.CommunicationPassword ?? string.Empty
    );

    if (handle <= 0)
    {
        Console.Error.WriteLine("Device connection failed.");
        return 1;
    }

    var buffer = new StringBuilder(1024 * 256);
    bool ok = DevCtrlApi.D_GetNewLog(handle, buffer);
    if (!ok)
    {
        Console.Error.WriteLine("Failed to fetch new logs from the biometric machine.");
        return 1;
    }

    string payload = buffer.ToString().Trim();
    string outputPath = Path.GetFullPath(config.OutputPath ?? "attendance.txt");
    Directory.CreateDirectory(Path.GetDirectoryName(outputPath)!);
    File.WriteAllText(outputPath, string.IsNullOrWhiteSpace(payload) ? "[]" : payload, new UTF8Encoding(false));

    Console.WriteLine($"Connected to {config.IpAddress}:{config.Port}.");
    Console.WriteLine($"Logs saved to: {outputPath}");
    Console.WriteLine($"Payload length: {payload.Length}");
    return 0;
}
catch (Exception ex)
{
    Console.Error.WriteLine($"Connector error: {ex.Message}");
    return 1;
}
finally
{
    if (handle > 0)
    {
        try
        {
            DevCtrlApi.D_Disconnect(handle);
            Console.WriteLine("Device disconnected.");
        }
        catch
        {
            // Best-effort disconnect so the device can be used for enrollment immediately after sync.
        }
    }
}

internal sealed class MachineConfig
{
    public string IpAddress { get; set; } = "";
    public int Port { get; set; } = 5005;
    public int DeviceNumber { get; set; } = 1;
    public string CommunicationPassword { get; set; } = "";
    public string OutputPath { get; set; } = "";
}

internal static class DevCtrlApi
{
    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern int D_Connect(string ip, int port, ushort devNo, string comPwd);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern void D_Disconnect(int handle);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_GetNewLog(int handle, StringBuilder jsonData);
}
