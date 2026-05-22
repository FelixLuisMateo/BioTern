unit uMain;

interface

uses
  Windows, Messages, SysUtils, Variants, Classes, Graphics, Controls, Forms,
  Dialogs, StdCtrls, ComCtrls;

type
  TfrmMain = class(TForm)
    PageControl1: TPageControl;
    TabSheet1: TTabSheet;
    TabSheet2: TTabSheet;
    TabSheet3: TTabSheet;
    Label1: TLabel;
    edtIP: TEdit;
    edtPort: TEdit;
    btnConnect: TButton;
    Label2: TLabel;
    btnDisconnect: TButton;
    Label6: TLabel;
    edtDevNo: TEdit;
    Label7: TLabel;
    edtComPwd: TEdit;
    btnSetDeviceNo: TButton;
    btnSetPassword: TButton;
    Label8: TLabel;
    Label9: TLabel;
    btnSetIP: TButton;
    btnGetIP: TButton;
    edtGateway: TEdit;
    edtMask: TEdit;
    btnGetTime: TButton;
    edtTime: TEdit;
    Label3: TLabel;
    btnSetTime: TButton;
    Label5: TLabel;
    mmoUser: TMemo;
    btnGetUserList: TButton;
    btnSetUser: TButton;
    TabSheet4: TTabSheet;
    btnGetDevInfo: TButton;
    btnGetConfig: TButton;
    btnSetConfig: TButton;
    btnGetRingSet: TButton;
    btnSetRingSet: TButton;
    btnGetNewLog: TButton;
    btnGetLog: TButton;
    btnClearRecords: TButton;
    edtNewNo: TEdit;
    Label10: TLabel;
    edtNewPwd: TEdit;
    Label11: TLabel;
    btnClearAdmin: TButton;
    btnReset: TButton;
    btnRestart: TButton;
    btnEmptyData: TButton;
    btnClearUser: TButton;
    btnGetUserData: TButton;
    btnDelUser: TButton;
    Label4: TLabel;
    edtID: TEdit;
    Label12: TLabel;
    edtBeginTime: TEdit;
    Label13: TLabel;
    mmoRec: TMemo;
    Label14: TLabel;
    edtEndTime: TEdit;
    mmoData: TMemo;
    Label15: TLabel;
    procedure btnConnectClick(Sender: TObject);
    procedure FormCreate(Sender: TObject);
    procedure btnDisconnectClick(Sender: TObject);
    procedure btnGetTimeClick(Sender: TObject);
    procedure btnSetTimeClick(Sender: TObject);
    procedure btnResetClick(Sender: TObject);
    procedure btnRestartClick(Sender: TObject);
    procedure btnEmptyDataClick(Sender: TObject);
    procedure btnClearAdminClick(Sender: TObject);
    procedure btnClearRecordsClick(Sender: TObject);
    procedure btnSetUserClick(Sender: TObject);
    procedure btnDelUserClick(Sender: TObject);
    procedure btnGetUserDataClick(Sender: TObject);
    procedure btnGetUserListClick(Sender: TObject);
    procedure btnClearUserClick(Sender: TObject);
    procedure btnGetNewLogClick(Sender: TObject);
    procedure btnGetLogClick(Sender: TObject);
    procedure btnGetDevInfoClick(Sender: TObject);
    procedure btnSetConfigClick(Sender: TObject);
    procedure btnGetConfigClick(Sender: TObject);
    procedure btnSetDeviceNoClick(Sender: TObject);
    procedure btnSetPasswordClick(Sender: TObject);
    procedure btnSetIPClick(Sender: TObject);
    procedure btnGetIPClick(Sender: TObject);
    procedure btnGetRingSetClick(Sender: TObject);
    procedure btnSetRingSetClick(Sender: TObject);
  private
    h:Integer;
  public
    { Public declarations }
  end;

var
  frmMain: TfrmMain;

  //联机
  function D_Connect(IP: PAnsiChar; Port: Integer; DevNo: Word; ComPwd: PAnsiChar): Integer; stdcall; external 'DevCtrl.dll';
  //断开连接
  procedure D_Disconnect(h: Integer); stdcall; external 'DevCtrl.dll';
  //获取网络参数
  function D_GetIP(h: Integer; IP,GateWay,Mask: PAnsiChar; var Port: Integer): Boolean; stdcall; external 'DevCtrl.dll';
  //修改网络参数
  function D_SetIP(h: Integer; IP,GateWay,Mask: PAnsiChar; Port: Integer): Boolean; stdcall; external 'DevCtrl.dll';
  //修改机号
  function D_SetDeviceNo(h: Integer; DevNo: Word): Boolean; stdcall; external 'DevCtrl.dll';
  //修改通讯密码
  function D_SetPassword(h: Integer; Compwd: PAnsiChar): Boolean; stdcall; external 'DevCtrl.dll';
  //获取时间
  function D_GetTime(h: Integer; strTime: PAnsiChar): Boolean; stdcall; external 'DevCtrl.dll';
  //修改时间
  function D_SetTime(h: Integer; strTime: PAnsiChar): Boolean; stdcall; external 'DevCtrl.dll';
  //获取指定用户信息
  function D_GetUserData(h: Integer; ID: Integer; jData: PAnsiChar): Boolean; stdcall; external 'DevCtrl.dll';
  //删除指定用户
  function D_DelUser(h: Integer; ID: Integer): Boolean; stdcall; external 'DevCtrl.dll';
  //上传用户
  function D_SetUser(h: Integer; jData: PAnsiChar): Boolean; stdcall; external 'DevCtrl.dll';
  //删除全部用户
  function D_ClearUser(h: Integer): Boolean; stdcall; external 'DevCtrl.dll';
  //获取用户列表
  function D_GetUserList(h: Integer; jData: PAnsiChar): Boolean; stdcall; external 'DevCtrl.dll';
  //获取指定时段记录
  function D_GetLog(h: Integer; bTime,eTime: PAnsiChar; jData:PAnsiChar): Boolean; stdcall; external 'DevCtrl.dll';
  //获取新记录
  function D_GetNewLog(h: Integer; jData: PAnsiChar): Boolean; stdcall; external 'DevCtrl.dll';
  //清除记录
  function D_ClearRecords(h: Integer): Boolean; stdcall; external 'DevCtrl.dll';
  //获取设备信息
  function D_GetDevInfo(h: Integer; jData: PAnsiChar): Boolean; stdcall; external 'DevCtrl.dll';
  //获取参数
  function D_GetConfig(h: Integer; jData: PAnsiChar): Boolean; stdcall; external 'DevCtrl.dll';
  //上传参数
  function D_SetConfig(h: Integer; jData: PAnsiChar): Boolean; stdcall; external 'DevCtrl.dll';
  //获取响铃参数
  function D_GetRingSet(h: Integer; jData: PAnsiChar): Boolean; stdcall; external 'DevCtrl.dll';
  //上传响铃参数
  function D_SetRingSet(h: Integer; jData: PAnsiChar): Boolean; stdcall; external 'DevCtrl.dll';
  //清除管理员
  function D_ClearAdmin(h: Integer): Boolean; stdcall; external 'DevCtrl.dll';
  //恢复出厂
  function D_Reset(h: Integer): Boolean; stdcall; external 'DevCtrl.dll';
  //重启设备
  function D_Restart(h: Integer): Boolean; stdcall; external 'DevCtrl.dll';
  //清除所有数据
  function D_EmptyData(h: Integer): Boolean; stdcall; external 'DevCtrl.dll';

implementation

{$R *.dfm}

procedure TfrmMain.FormCreate(Sender: TObject);
begin
  PageControl1.ActivePageIndex := 0;
  h := 0;
  edtBeginTime.Text := FormatDateTime('yyyy-MM-dd 00:00:00',Now);
  edtEndTime.Text := FormatDateTime('yyyy-MM-dd 23:59:59',Now);
  btnConnect.Enabled:=True;
  btnDisconnect.Enabled:=False;     
end;

procedure TfrmMain.btnConnectClick(Sender: TObject);
begin
  h := D_Connect(PAnsiChar(AnsiString(edtIP.Text)),StrToIntDef(edtPort.Text,0),StrToIntDef(edtDevNo.Text,0),PAnsiChar(AnsiString(edtComPwd.Text)));
  if h > 0 then
  begin
    btnConnect.Enabled:=False;
    btnDisconnect.Enabled:=True;
  end
  else
  begin
    ShowMessage('联机失败');
  end;
end;

procedure TfrmMain.btnDisconnectClick(Sender: TObject);
begin
  if h > 0 then
    D_Disconnect(h);
  btnConnect.Enabled:=True;
  btnDisconnect.Enabled:=False;
end;

procedure TfrmMain.btnGetIPClick(Sender: TObject);
var
  IP,Gateway,Mask:array[0..20] of Ansichar;
  Port:Integer;
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_GetIP(h,IP,Gateway,Mask,Port) then
  begin
    edtIP.Text := string(IP);
    edtGateway.Text := string(Gateway);
    edtMask.Text := string(Mask);
    edtPort.Text := IntToStr(Port);
    ShowMessage('获取网络参数成功');
  end
  else
  begin
    ShowMessage('获取网络参数失败');
  end;
end;

procedure TfrmMain.btnSetIPClick(Sender: TObject);
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_SetIP(h,PAnsiChar(AnsiString(edtIP.Text)),PAnsiChar(AnsiString(edtGateway.Text)),PAnsiChar(AnsiString(edtMask.Text)),StrToIntDef(edtPort.Text,5001)) then
  begin
    ShowMessage('修改网络参数成功');
  end
  else
  begin
    ShowMessage('修改网络参数失败');
  end;
end;       

procedure TfrmMain.btnSetDeviceNoClick(Sender: TObject);
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_SetDeviceNo(h,StrToIntDef(edtNewNo.Text,1)) then
  begin
    ShowMessage('修改设备机号成功');
  end
  else
  begin
    ShowMessage('修改设备机号失败');
  end;
end;

procedure TfrmMain.btnSetPasswordClick(Sender: TObject);
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_SetPassword(h,PAnsiChar(AnsiString(edtNewPwd.Text))) then
  begin
    ShowMessage('修改通讯密码成功');
  end
  else
  begin
    ShowMessage('修改通讯密码失败');
  end;
end;

procedure TfrmMain.btnGetTimeClick(Sender: TObject);
var
  strTime:array[0..50] of AnsiChar;
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_GetTime(h, strTime) then
  begin
    edtTime.Text := string(strTime);
  end
  else
  begin
    ShowMessage('获取时间失败');
  end;
end;

procedure TfrmMain.btnSetTimeClick(Sender: TObject);
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_SetTime(h, PAnsiChar(AnsiString(edtTime.Text))) then
  begin
    ShowMessage('修改时间成功');
  end
  else
  begin
    ShowMessage('修改时间失败');
  end;
end;

procedure TfrmMain.btnGetUserDataClick(Sender: TObject);
var
  jData:array[0..1024 * 100] of AnsiChar;
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_GetUserData(h,StrToIntDef(edtID.Text,0),@jData) then
  begin
    mmoUser.Lines.Text := string(jData);
    ShowMessage('获取用户信息成功');
  end
  else
  begin
    ShowMessage('获取用户信息失败');
  end;
end;

procedure TfrmMain.btnDelUserClick(Sender: TObject);
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_DelUser(h,StrToIntDef(edtID.Text,0)) then
  begin
    ShowMessage('删除指定用户成功');
  end
  else
  begin
    ShowMessage('删除指定用户失败');
  end;
end;  

procedure TfrmMain.btnSetUserClick(Sender: TObject);
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if mmoUser.Lines.Text = '' then
  begin
    ShowMessage('用户数据不能为空');
    Exit;
  end;
  if D_SetUser(h,PAnsiChar(AnsiString(mmoUser.Lines.Text))) then
  begin
    ShowMessage('新增用户成功');
  end
  else
  begin
    ShowMessage('新增用户失败');
  end;
end;

procedure TfrmMain.btnClearUserClick(Sender: TObject);
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_ClearUser(h) then
  begin
    ShowMessage('删除全部用户成功');
  end
  else
  begin
    ShowMessage('删除全部用户失败');
  end;
end; 

procedure TfrmMain.btnGetUserListClick(Sender: TObject);
var
  jData:array[0..1024 * 100] of AnsiChar;
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_GetUserList(h,@jData) then
  begin
    mmoUser.Lines.Text := string(jData);
    ShowMessage('获取用户列表成功');
  end
  else
  begin
    ShowMessage('获取用户列表失败');
  end;
end;  

procedure TfrmMain.btnGetLogClick(Sender: TObject);
var
  jData:array[0..1024 * 100] of AnsiChar;
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_GetLog(h,'2022-01-01','2023-12-31 23:59:59',@jData) then
  begin
    mmoRec.Lines.Text := string(jData);
    ShowMessage('获取时间段记录成功');
  end
  else
  begin
    ShowMessage('获取时间段记录失败');
  end;
end; 

procedure TfrmMain.btnGetNewLogClick(Sender: TObject);
var
  jData:array[0..1024 * 100] of AnsiChar;
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_GetNewLog(h,@jData) then
  begin
    mmoRec.Lines.Text := string(jData);
    ShowMessage('获取新记录成功');
  end
  else
  begin
    ShowMessage('获取新记录失败');
  end;
end;

procedure TfrmMain.btnClearRecordsClick(Sender: TObject);
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_ClearRecords(h) then
  begin
    ShowMessage('清除记录成功');
  end
  else
  begin
    ShowMessage('清除记录失败');
  end;
end;

procedure TfrmMain.btnGetDevInfoClick(Sender: TObject);
var
  jData:array[0..1024 * 100] of AnsiChar;
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_GetDevInfo(h,@jData) then
  begin
    mmoData.Lines.Text := string(jData);
    ShowMessage('获取设备信息成功');
  end
  else
  begin
    ShowMessage('获取设备信息失败');
  end;
end;

procedure TfrmMain.btnGetConfigClick(Sender: TObject);
var
  jData:array[0..1024 * 100] of AnsiChar;
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_GetConfig(h,@jData) then
  begin
    mmoData.Lines.Text := string(jData);
    ShowMessage('获取设备参数成功');
  end
  else
  begin
    ShowMessage('获取设备参数失败');
  end;
end;

procedure TfrmMain.btnSetConfigClick(Sender: TObject);
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if mmoData.Lines.Text = '' then
  begin
    ShowMessage('参数不能为空');
    Exit;
  end;
  if D_SetConfig(h,PAnsiChar(AnsiString(mmoData.Lines.Text))) then
  begin
    ShowMessage('上传设备参数成功');
  end
  else
  begin
    ShowMessage('上传设备参数失败');
  end;
end;

procedure TfrmMain.btnGetRingSetClick(Sender: TObject);
var
  jData:array[0..1024 * 100] of AnsiChar;
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_GetRingSet(h,@jData) then
  begin
    mmoData.Lines.Text := string(jData);
    ShowMessage('获取响铃参数成功');
  end
  else
  begin
    ShowMessage('获取响铃参数失败');
  end;
end;

procedure TfrmMain.btnSetRingSetClick(Sender: TObject);
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if mmoData.Lines.Text = '' then
  begin
    ShowMessage('参数不能为空');
    Exit;
  end;
  if D_SetRingSet(h,PAnsiChar(AnsiString(mmoData.Lines.Text))) then
  begin
    ShowMessage('上传响铃参数成功');
  end
  else
  begin
    ShowMessage('上传响铃参数失败');
  end;
end;       

procedure TfrmMain.btnClearAdminClick(Sender: TObject);
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_ClearAdmin(h) then
  begin
    ShowMessage('清除管理员成功');
  end
  else
  begin
    ShowMessage('清除管理员失败');
  end;
end;

procedure TfrmMain.btnResetClick(Sender: TObject);
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_Reset(h) then
  begin
    ShowMessage('恢复出厂成功');
  end
  else
  begin
    ShowMessage('恢复出厂失败');
  end;
end;

procedure TfrmMain.btnEmptyDataClick(Sender: TObject);
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_EmptyData(h) then
  begin
    ShowMessage('清除所有数据成功');
  end
  else
  begin
    ShowMessage('清除所有数据失败');
  end;
end;      

procedure TfrmMain.btnRestartClick(Sender: TObject);
begin
  if h <= 0 then
  begin
    ShowMessage('请先联机');
    Exit;
  end;
  if D_Restart(h) then
  begin
    ShowMessage('重启设备成功');
  end
  else
  begin
    ShowMessage('重启设备失败');
  end;
end;

end.
