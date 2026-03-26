using System;
using System.Collections.Generic;
using System.ComponentModel;
using System.Data;
using System.Drawing;
using System.Linq;
using System.Text;
using System.Runtime.InteropServices;
using System.Windows.Forms;
using DevCtrl;
using System.IO;

namespace Demo
{
    public partial class ADSDemo : Form
    {
        public int h;

        public ADSDemo()
        {
            InitializeComponent();
            h = 0;
            btnConnect.Enabled = true;
            btnDisconnect.Enabled = false;
            edtBeginTime.Text = DateTime.Now.ToString("yyyy-MM-dd 00:00:00");
            edtEndTime.Text = DateTime.Now.ToString("yyyy-MM-dd 23:59:59");
            LoadSavedConnectionSettings();
        }

        private void LoadSavedConnectionSettings()
        {
            if (!string.IsNullOrWhiteSpace(Properties.Settings.Default.DeviceIp))
            {
                edtIP.Text = Properties.Settings.Default.DeviceIp;
            }

            if (!string.IsNullOrWhiteSpace(Properties.Settings.Default.DeviceGateway))
            {
                edtGateway.Text = Properties.Settings.Default.DeviceGateway;
            }

            if (!string.IsNullOrWhiteSpace(Properties.Settings.Default.DeviceMask))
            {
                edtMask.Text = Properties.Settings.Default.DeviceMask;
            }

            if (!string.IsNullOrWhiteSpace(Properties.Settings.Default.DevicePort))
            {
                edtPort.Text = Properties.Settings.Default.DevicePort;
            }

            if (!string.IsNullOrWhiteSpace(Properties.Settings.Default.DeviceNo))
            {
                edtDevNo.Text = Properties.Settings.Default.DeviceNo;
            }

            if (!string.IsNullOrWhiteSpace(Properties.Settings.Default.CommPassword))
            {
                edtComPwd.Text = Properties.Settings.Default.CommPassword;
            }
        }

        private void SaveConnectionSettings()
        {
            Properties.Settings.Default.DeviceIp = edtIP.Text.Trim();
            Properties.Settings.Default.DeviceGateway = edtGateway.Text.Trim();
            Properties.Settings.Default.DeviceMask = edtMask.Text.Trim();
            Properties.Settings.Default.DevicePort = edtPort.Text.Trim();
            Properties.Settings.Default.DeviceNo = edtDevNo.Text.Trim();
            Properties.Settings.Default.CommPassword = edtComPwd.Text.Trim();
            Properties.Settings.Default.Save();
        }

        private void btnConnect_Click(object sender, EventArgs e)
        {
            string IP = edtIP.Text;
            Int32 Port = Int32.Parse(edtPort.Text);
            UInt16 DevNo = UInt16.Parse(edtDevNo.Text);
            string ComPwd = edtComPwd.Text;
            h = DevCtrlAPI.D_Connect(IP, Port, DevNo, ComPwd);
            if (h > 0)
            {
                btnDisconnect.Enabled = true;
                btnConnect.Enabled = false;
                SaveConnectionSettings();
                MessageBox.Show("Connection successful");
            }
            else
            {
                MessageBox.Show("Connection failed");
            }
        }

        private void btnDisconnect_Click(object sender, EventArgs e)
        {
            if (h > 0)
            {
                DevCtrlAPI.D_Disconnect(h);
            }
            btnDisconnect.Enabled = false;
            btnConnect.Enabled = true;
        }

        private void btnGetIP_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            StringBuilder IP = new StringBuilder(20);
            StringBuilder Mask = new StringBuilder(20);
            StringBuilder Gateway = new StringBuilder(20);
            Int32 Port = 0;
            if (DevCtrlAPI.D_GetIP(h, IP, Gateway, Mask, ref Port))
            {
                edtIP.Text = IP.ToString();
                edtGateway.Text = Gateway.ToString();
                edtMask.Text = Mask.ToString();
                edtPort.Text = Port.ToString();
                MessageBox.Show("Network parameters retrieved successfully");
            }
            else
            {
                MessageBox.Show("Failed to retrieve network parameters");
            }
        }

        private void btnSetIP_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            string IP = edtIP.Text;
            string Gateway = edtGateway.Text;
            string Mask = edtMask.Text;
            Int32 Port = Int32.Parse(edtPort.Text);

            if (DevCtrlAPI.D_SetIP(h, IP, Gateway, Mask, Port))
            {
                SaveConnectionSettings();
                MessageBox.Show("Network parameters updated successfully");
            }
            else
            {
                MessageBox.Show("Failed to update network parameters");
            }
        }

        private void btnSetDeviceNo_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            UInt16 NewNo = UInt16.Parse(edtNewNo.Text);

            if (DevCtrlAPI.D_SetDeviceNo(h, NewNo))
            {
                MessageBox.Show("Device number updated successfully");
            }
            else
            {
                MessageBox.Show("Failed to update device number");
            }
        }

        private void btnSetPassword_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            string NewPwd = edtNewPwd.Text;

            if (DevCtrlAPI.D_SetPassword(h, NewPwd))
            {
                MessageBox.Show("Communication password updated successfully");
            }
            else
            {
                MessageBox.Show("Failed to update communication password");
            }
        }

        private void btnGetTime_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            StringBuilder strTime = new StringBuilder(50);
            if (DevCtrlAPI.D_GetTime(h, strTime))
            {
                edtTime.Text = strTime.ToString();
                MessageBox.Show("Time retrieved successfully");
            }
            else
            {
                MessageBox.Show("Failed to retrieve time");
            }
        }

        private void btnSetTime_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            string strTime = edtTime.Text;
            if (DevCtrlAPI.D_SetTime(h, strTime))
            {
                MessageBox.Show("Time updated successfully");
            }
            else
            {
                MessageBox.Show("Failed to update time");
            }
        }

        private void btnGetUserData_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            StringBuilder jData = new StringBuilder(1024 * 100);
            Int32 ID = Int32.Parse(edtID.Text);
            if (DevCtrlAPI.D_GetUserData(h, ID, jData))
            {
                mmoUser.Text = jData.ToString();
                MessageBox.Show("User information retrieved successfully");
            }
            else
            {
                MessageBox.Show("Failed to retrieve user information");
            }
        }

        private void btnDelUser_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            Int32 ID = Int32.Parse(edtID.Text);
            if (DevCtrlAPI.D_DelUser(h, ID))
            {
                MessageBox.Show("User deleted successfully");
            }
            else
            {
                MessageBox.Show("Failed to delete user");
            }
        }

        private void btnSetUser_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            string jData = mmoUser.Text.Trim();
            if (jData == "")
            {
                MessageBox.Show("User data cannot be empty");
                return;
            }
            if (DevCtrlAPI.D_SetUser(h, jData))
            {
                MessageBox.Show("User added successfully");
            }
            else
            {
                MessageBox.Show("Failed to add user");
            }
        }

        private void btnClearUser_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            if (DevCtrlAPI.D_ClearUser(h))
            {
                MessageBox.Show("All users deleted successfully");
            }
            else
            {
                MessageBox.Show("Failed to delete all users!");
            }
        }

        private void btnGetUserList_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            StringBuilder jData = new StringBuilder(1024 * 100);
            if (DevCtrlAPI.D_GetUserList(h, jData))
            {
                mmoUser.Text = jData.ToString();
                MessageBox.Show("User list retrieved successfully");
            }
            else
            {
                MessageBox.Show("Failed to retrieve user list");
            }
        }

        private void btnGetLog_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            string bTime = edtBeginTime.Text;
            string eTime = edtEndTime.Text;
            StringBuilder jData = new StringBuilder(1024 * 100);
            if (DevCtrlAPI.D_GetLog(h, bTime, eTime, jData))
            {
                mmoRec.Text = jData.ToString();
                MessageBox.Show("Records for the time period retrieved successfully");
            }
            else
            {
                MessageBox.Show("Failed to retrieve records for the time period");
            }
        }

        private void btnGetNewLog_Click(object sender, EventArgs e)
        {
            GetNewLog(true); // Show popup and save logs
        }

        private void btnClearRecords_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            if (DevCtrlAPI.D_ClearRecords(h))
            {
                MessageBox.Show("Records cleared successfully");
            }
            else
            {
                MessageBox.Show("Failed to clear records");
            }
        }

        private void btnGetDevInfo_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            StringBuilder jData = new StringBuilder(1024 * 100);
            if (DevCtrlAPI.D_GetDevInfo(h, jData))
            {
                mmoData.Text = jData.ToString();
                MessageBox.Show("Device information retrieved successfully");
            }
            else
            {
                MessageBox.Show("Failed to retrieve device information");
            }
        }

        private void btnGetConfig_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            StringBuilder jData = new StringBuilder(1024 * 100);
            if (DevCtrlAPI.D_GetConfig(h, jData))
            {
                mmoData.Text = jData.ToString();
                MessageBox.Show("Device parameters retrieved successfully");
            }
            else
            {
                MessageBox.Show("Failed to retrieve device parameters");
            }
        }

        private void btnSetConfig_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            string jData = mmoData.Text.Trim();
            if (jData == "")
            {
                MessageBox.Show("Parameter data cannot be empty");
                return;
            }
            if (DevCtrlAPI.D_SetConfig(h, jData))
            {
                MessageBox.Show("Device parameters uploaded successfully");
            }
            else
            {
                MessageBox.Show("Failed to upload device parameters");
            }
        }

        private void btnGetRingSet_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            StringBuilder jData = new StringBuilder(1024 * 100);
            if (DevCtrlAPI.D_GetRingSet(h, jData))
            {
                mmoData.Text = jData.ToString();
                MessageBox.Show("Bell parameters retrieved successfully");
            }
            else
            {
                MessageBox.Show("Failed to retrieve bell parameters");
            }
        }

        private void btnSetRingSet_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            string jData = mmoData.Text.Trim();
            if (jData == "")
            {
                MessageBox.Show("Parameter data cannot be empty");
                return;
            }
            if (DevCtrlAPI.D_SetRingSet(h, jData))
            {
                MessageBox.Show("Bell parameters uploaded successfully");
            }
            else
            {
                MessageBox.Show("Failed to upload bell parameters");
            }
        }

        private void btnClearAdmin_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            if (DevCtrlAPI.D_ClearAdmin(h))
            {
                MessageBox.Show("Administrators cleared successfully");
            }
            else
            {
                MessageBox.Show("Failed to clear administrators");
            }
        }

        private void btnReset_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            if (DevCtrlAPI.D_Reset(h))
            {
                MessageBox.Show("Factory reset successful");
            }
            else
            {
                MessageBox.Show("Factory reset failed");
            }
        }

        private void btnEmptyData_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            if (DevCtrlAPI.D_EmptyData(h))
            {
                MessageBox.Show("All data cleared successfully");
            }
            else
            {
                MessageBox.Show("Failed to clear all data");
            }
        }

        private void btnRestart_Click(object sender, EventArgs e)
        {
            if (h <= 0)
            {
                MessageBox.Show("Please connect first");
                return;
            }
            if (DevCtrlAPI.D_Restart(h))
            {
                MessageBox.Show("Device restarted successfully");
            }
            else
            {
                MessageBox.Show("Failed to restart device");
            }
        }

        private void timer1_Tick(object sender, EventArgs e)
        {
            GetNewLog(false); // No popup when called by timer
        }

        private void ADSDemo_Load(object sender, EventArgs e)
        {
            timer1.Interval = 60000; // 1 minute
            timer1.Start();
        }
        private void GetNewLog(bool showPopup)
        {
            if (h <= 0)
            {
                if (showPopup)
                    MessageBox.Show("Please connect first");
                return;
            }
            StringBuilder jData = new StringBuilder(1024 * 100);
            if (DevCtrlAPI.D_GetNewLog(h, jData))
            {
                mmoRec.Text = jData.ToString();
                // Save logs to a file for PHP to read
                string logPath = @"C:\xampp\htdocs\BioTern\attendance.txt";
                File.WriteAllText(logPath, jData.ToString());
                if (showPopup)
                    MessageBox.Show("New records retrieved successfully");
            }
            else
            {
                if (showPopup)
                    MessageBox.Show("Failed to retrieve new records");
            }
        }
    }
}
