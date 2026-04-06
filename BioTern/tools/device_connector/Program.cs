using System.Runtime.InteropServices;
using System.Text;
using System.Text.Json;

if (args.Length < 1)
{
    Console.Error.WriteLine("Usage: BioTernMachineConnector <config-json-path> [command] [args...]");
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

string command = args.Length >= 2 ? args[1].Trim().ToLowerInvariant() : "sync";
string[] cmdArgs = args.Skip(2).ToArray();

int handle = 0;
try
{
    handle = DevCtrlApi.D_Connect(
        config.IpAddress.Trim(),
        config.Port <= 0 ? 5001 : config.Port,
        (ushort)(config.DeviceNumber <= 0 ? 1 : config.DeviceNumber),
        config.CommunicationPassword ?? string.Empty
    );

    if (handle <= 0)
    {
        Console.Error.WriteLine("Device connection failed.");
        return 1;
    }

    return command switch
    {
        "sync" => RunSync(handle, config),
        "get-log-range" => RunGetLogRange(handle, cmdArgs),
        "get-log" => RunGetLogRange(handle, cmdArgs),
        "get-user-list" => RunStringCommand(handle, DevCtrlApi.D_GetUserList),
        "get-device-info" => RunStringCommand(handle, DevCtrlApi.D_GetDevInfo),
        "get-config" => RunStringCommand(handle, DevCtrlApi.D_GetConfig),
        "get-ring-set" => RunStringCommand(handle, DevCtrlApi.D_GetRingSet),
        "get-time" => RunStringCommand(handle, DevCtrlApi.D_GetTime, 256),
        "get-network" => RunGetNetwork(handle),
        "get-user" => RunGetUser(handle, cmdArgs),
        "set-user" => RunSetUser(handle, cmdArgs),
        "delete-user" => RunDeleteUser(handle, cmdArgs),
        "clear-users" => RunBoolCommand(DevCtrlApi.D_ClearUser(handle), "Users cleared.", "Failed to clear users."),
        "clear-records" => RunBoolCommand(DevCtrlApi.D_ClearRecords(handle), "Records cleared.", "Failed to clear records."),
        "set-config" => RunSetJson(handle, cmdArgs, DevCtrlApi.D_SetConfig, "Config updated."),
        "set-ring-set" => RunSetJson(handle, cmdArgs, DevCtrlApi.D_SetRingSet, "Bell settings updated."),
        "set-time" => RunSetTime(handle, cmdArgs),
        "set-network" => RunSetNetwork(handle, cmdArgs),
        "set-device-no" => RunSetDeviceNo(handle, cmdArgs),
        "set-password" => RunSetPassword(handle, cmdArgs),
        "clear-admin" => RunBoolCommand(DevCtrlApi.D_ClearAdmin(handle), "Admins cleared.", "Failed to clear admins."),
        "restart" => RunBoolCommand(DevCtrlApi.D_Restart(handle), "Device restarted.", "Failed to restart device."),
        "reset" => RunBoolCommand(DevCtrlApi.D_Reset(handle), "Factory reset completed.", "Failed to reset device."),
        "empty-data" => RunBoolCommand(DevCtrlApi.D_EmptyData(handle), "Device data cleared.", "Failed to clear device data."),
        _ => UnknownCommand(command),
    };
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
        }
    }
}

static int RunSync(int handle, MachineConfig config)
{
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

static int RunGetLogRange(int handle, string[] cmdArgs)
{
    string beginTime = (cmdArgs.Length >= 1 && !string.IsNullOrWhiteSpace(cmdArgs[0]))
        ? cmdArgs[0].Trim()
        : "2000-01-01 00:00:00";
    string endTime = (cmdArgs.Length >= 2 && !string.IsNullOrWhiteSpace(cmdArgs[1]))
        ? cmdArgs[1].Trim()
        : DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");

    // Historical ranges can return much larger payloads than get-new-log.
    var buffer = new StringBuilder(1024 * 1024 * 4);
    bool ok = DevCtrlApi.D_GetLog(handle, beginTime, endTime, buffer);
    if (!ok)
    {
        Console.Error.WriteLine("Failed to fetch log range from the biometric machine.");
        return 1;
    }

    string payload = buffer.ToString().Trim();
    Console.Write(string.IsNullOrWhiteSpace(payload) ? "[]" : payload);
    return 0;
}

static int RunStringCommand(int handle, Func<int, StringBuilder, bool> command, int capacity = 1024 * 256)
{
    var buffer = new StringBuilder(capacity);
    bool ok = command(handle, buffer);
    if (!ok)
    {
        Console.Error.WriteLine("Device command failed.");
        return 1;
    }

    Console.Write(buffer.ToString().Trim());
    return 0;
}

static int RunGetNetwork(int handle)
{
    var ip = new StringBuilder(64);
    var gateway = new StringBuilder(64);
    var mask = new StringBuilder(64);
    int port = 0;
    bool ok = DevCtrlApi.D_GetIP(handle, ip, gateway, mask, ref port);
    if (!ok)
    {
        Console.Error.WriteLine("Failed to read network settings.");
        return 1;
    }

    Console.Write(JsonSerializer.Serialize(new
    {
        ip = ip.ToString(),
        gateway = gateway.ToString(),
        mask = mask.ToString(),
        port
    }));
    return 0;
}

static int RunGetUser(int handle, string[] cmdArgs)
{
    if (cmdArgs.Length < 1 || !int.TryParse(cmdArgs[0], out int userId))
    {
        Console.Error.WriteLine("Usage: get-user <user-id>");
        return 2;
    }

    var buffer = new StringBuilder(1024 * 256);
    bool ok = DevCtrlApi.D_GetUserData(handle, userId, buffer);
    if (!ok)
    {
        Console.Error.WriteLine("Failed to get user.");
        return 1;
    }

    Console.Write(buffer.ToString().Trim());
    return 0;
}

static int RunSetUser(int handle, string[] cmdArgs)
{
    if (cmdArgs.Length < 1)
    {
        Console.Error.WriteLine("Usage: set-user <json-file-or-inline-json>");
        return 2;
    }

    string payload = ReadTextArg(cmdArgs[0]);
    if (string.IsNullOrWhiteSpace(payload))
    {
        Console.Error.WriteLine("User payload is empty.");
        return 2;
    }

    bool ok = DevCtrlApi.D_SetUser(handle, payload);
    return RunBoolCommand(ok, "User updated.", "Failed to update user.");
}

static int RunDeleteUser(int handle, string[] cmdArgs)
{
    if (cmdArgs.Length < 1 || !int.TryParse(cmdArgs[0], out int userId))
    {
        Console.Error.WriteLine("Usage: delete-user <user-id>");
        return 2;
    }

    return RunBoolCommand(DevCtrlApi.D_DelUser(handle, userId), "User deleted.", "Failed to delete user.");
}

static int RunSetJson(int handle, string[] cmdArgs, Func<int, string, bool> command, string successMessage)
{
    if (cmdArgs.Length < 1)
    {
        Console.Error.WriteLine("Missing JSON payload.");
        return 2;
    }

    string payload = ReadTextArg(cmdArgs[0]);
    if (string.IsNullOrWhiteSpace(payload))
    {
        Console.Error.WriteLine("JSON payload is empty.");
        return 2;
    }

    return RunBoolCommand(command(handle, payload), successMessage, "Device update failed.");
}

static int RunSetTime(int handle, string[] cmdArgs)
{
    if (cmdArgs.Length < 1)
    {
        Console.Error.WriteLine("Usage: set-time <yyyy-MM-dd HH:mm:ss>");
        return 2;
    }

    return RunBoolCommand(DevCtrlApi.D_SetTime(handle, cmdArgs[0]), "Time updated.", "Failed to update time.");
}

static int RunSetNetwork(int handle, string[] cmdArgs)
{
    if (cmdArgs.Length < 4 || !int.TryParse(cmdArgs[3], out int port))
    {
        Console.Error.WriteLine("Usage: set-network <ip> <gateway> <mask> <port>");
        return 2;
    }

    return RunBoolCommand(
        DevCtrlApi.D_SetIP(handle, cmdArgs[0], cmdArgs[1], cmdArgs[2], port),
        "Network settings updated.",
        "Failed to update network settings."
    );
}

static int RunSetDeviceNo(int handle, string[] cmdArgs)
{
    if (cmdArgs.Length < 1 || !ushort.TryParse(cmdArgs[0], out ushort deviceNo))
    {
        Console.Error.WriteLine("Usage: set-device-no <device-no>");
        return 2;
    }

    return RunBoolCommand(DevCtrlApi.D_SetDeviceNo(handle, deviceNo), "Device number updated.", "Failed to update device number.");
}

static int RunSetPassword(int handle, string[] cmdArgs)
{
    if (cmdArgs.Length < 1)
    {
        Console.Error.WriteLine("Usage: set-password <communication-password>");
        return 2;
    }

    return RunBoolCommand(DevCtrlApi.D_SetPassword(handle, cmdArgs[0]), "Communication password updated.", "Failed to update communication password.");
}

static int RunBoolCommand(bool ok, string successMessage, string failureMessage)
{
    if (!ok)
    {
        Console.Error.WriteLine(failureMessage);
        return 1;
    }

    Console.Write(successMessage);
    return 0;
}

static int UnknownCommand(string command)
{
    Console.Error.WriteLine($"Unknown command: {command}");
    return 2;
}

static string ReadTextArg(string value)
{
    if (File.Exists(value))
    {
        return File.ReadAllText(value);
    }

    return value;
}

internal sealed class MachineConfig
{
    public string IpAddress { get; set; } = "";
    public int Port { get; set; } = 5001;
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
    public static extern bool D_GetIP(int handle, StringBuilder ip, StringBuilder gateWay, StringBuilder mask, ref int port);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_SetIP(int handle, string ip, string gateWay, string mask, int port);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_SetDeviceNo(int handle, ushort devNo);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_SetPassword(int handle, string comPwd);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_GetTime(int handle, StringBuilder timeValue);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_SetTime(int handle, string timeValue);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_GetUserData(int handle, int id, StringBuilder jsonData);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_DelUser(int handle, int id);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_SetUser(int handle, string jsonData);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_ClearUser(int handle);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_GetUserList(int handle, StringBuilder jsonData);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_GetNewLog(int handle, StringBuilder jsonData);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_GetLog(int handle, string bTime, string eTime, StringBuilder jsonData);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_ClearRecords(int handle);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_GetDevInfo(int handle, StringBuilder jsonData);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_GetConfig(int handle, StringBuilder jsonData);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_SetConfig(int handle, string jsonData);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_GetRingSet(int handle, StringBuilder jsonData);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_SetRingSet(int handle, string jsonData);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_ClearAdmin(int handle);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_Reset(int handle);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_Restart(int handle);

    [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
    public static extern bool D_EmptyData(int handle);
}
