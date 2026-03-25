using System;
using System.Runtime.InteropServices;
using System.Text;

using HANDLE = System.IntPtr;

namespace DevCtrl
{
    unsafe public class DevCtrlAPI
    {
       
        //联机
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern Int32 D_Connect(string IP, Int32 Port, UInt16 DevNo, string ComPwd);
        //断开连接
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern void D_Disconnect(Int32 h);
        //获取网络参数
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_GetIP(Int32 h, StringBuilder IP, StringBuilder GateWay, StringBuilder Mask, ref Int32 Port);
        //修改网络参数
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_SetIP(Int32 h, string IP, string GateWay, string Mask, Int32 Port);
        //修改机号
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_SetDeviceNo(Int32 h, UInt16 DevNo);
        //修改通讯密码
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_SetPassword(Int32 h, string ComPwd);
        //获取时间
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_GetTime(Int32 h, StringBuilder strTime);
        //修改时间
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_SetTime(Int32 h, string strTime);
        //获取指定用户信息
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_GetUserData(Int32 h, Int32 ID, StringBuilder jData);
        //删除指定用户
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_DelUser(Int32 h, Int32 ID);
        //上传用户
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_SetUser(Int32 h, string jData);
        //删除全部用户
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_ClearUser(Int32 h);
        //获取用户列表
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_GetUserList(Int32 h, StringBuilder jData);
        //获取指定时段记录
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_GetLog(Int32 h, string bTime, string eTime, StringBuilder jData);
        //获取新记录
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_GetNewLog(Int32 h, StringBuilder jData);
        //清除记录
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_ClearRecords(Int32 h);
        //获取设备信息
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_GetDevInfo(Int32 h, StringBuilder jData);
        //获取参数
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_GetConfig(Int32 h, StringBuilder jData);
        //上传参数
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_SetConfig(Int32 h, string jData);
        //获取响铃参数
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_GetRingSet(Int32 h, StringBuilder jData);
        //上传响铃参数
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_SetRingSet(Int32 h, string jData);
        //清除管理员
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_ClearAdmin(Int32 h);
        //恢复出厂
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_Reset(Int32 h);
        //重启设备
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_Restart(Int32 h);
        //清除所有数据
        [DllImport("DevCtrl.dll", CharSet = CharSet.Ansi, CallingConvention = CallingConvention.StdCall)]
        public static extern bool D_EmptyData(Int32 h);
                                   
    }
}
