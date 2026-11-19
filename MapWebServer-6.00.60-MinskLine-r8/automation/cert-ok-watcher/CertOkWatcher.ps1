# CertOkWatcher.ps1
# Watches for the Avest "Windows Security / certificate selection" dialog and
# automatically clicks "OK" on every occurrence AFTER the first dialog has been
# confirmed manually by the user. Disarms after a period of inactivity.
#
# State machine:
#   IDLE  -> first dialog appears -> WAIT_MANUAL (do nothing)
#   WAIT_MANUAL -> first dialog disappears -> ARMED (remember time)
#   ARMED -> dialog appears -> click OK immediately
#   ARMED -> no dialogs for $DisarmAfterSeconds -> IDLE
#
# Run hidden at logon (see Install-CertOkWatcher.cmd).

Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName UIAutomationClient
Add-Type -AssemblyName UIAutomationTypes

Add-Type -ReferencedAssemblies UIAutomationClient, UIAutomationTypes -TypeDefinition @'
namespace W {
using System;
using System.Text;
using System.Runtime.InteropServices;
using System.Collections.Generic;

public class Win32 {
    [DllImport("user32.dll", SetLastError=true, CharSet=CharSet.Unicode)]
    public static extern bool EnumWindows(EnumWindowsProc cb, IntPtr lParam);
    public delegate bool EnumWindowsProc(IntPtr h, IntPtr l);
    [DllImport("user32.dll", CharSet=CharSet.Unicode)]
    public static extern int GetWindowText(IntPtr h, StringBuilder s, int n);
    [DllImport("user32.dll", CharSet=CharSet.Unicode)]
    public static extern int GetClassName(IntPtr h, StringBuilder s, int n);
    [DllImport("user32.dll")] public static extern bool IsWindowVisible(IntPtr h);
    [DllImport("user32.dll")] public static extern uint GetWindowThreadProcessId(IntPtr h, out uint pid);
    [DllImport("user32.dll", CharSet=CharSet.Unicode)]
    public static extern IntPtr FindWindowEx(IntPtr parent, IntPtr after, string cls, string title);
    [DllImport("user32.dll")] public static extern IntPtr SendMessage(IntPtr h, uint msg, IntPtr w, IntPtr l);
    [DllImport("user32.dll")] public static extern bool SetForegroundWindow(IntPtr h);

    public static uint BM_CLICK = 0x00F5;

    public static List<IntPtr> FindCertDialogs(string[] procNames) {
        var result = new List<IntPtr>();
        EnumWindows((h, l) => {
            if (!IsWindowVisible(h)) return true;
            var cls = new StringBuilder(256);
            GetClassName(h, cls, 256);
            string cs = cls.ToString();
            if (cs != "#32770" && cs != "Credential Dialog Xaml Host") return true;
            var title = new StringBuilder(512);
            GetWindowText(h, title, 512);
            string t = title.ToString();
            bool isCert = t.IndexOf("Безопасность Windows", StringComparison.OrdinalIgnoreCase) >= 0
                       || t.IndexOf("Windows Security", StringComparison.OrdinalIgnoreCase) >= 0
                       || t.IndexOf("Выбор сертификата", StringComparison.OrdinalIgnoreCase) >= 0
                       || t.IndexOf("Certificate", StringComparison.OrdinalIgnoreCase) >= 0;
            if (!isCert) return true;
            uint pid; GetWindowThreadProcessId(h, out pid);
            var p = System.Diagnostics.Process.GetProcessById((int)pid);
            foreach (var n in procNames) {
                if (p.ProcessName.IndexOf(n, StringComparison.OrdinalIgnoreCase) >= 0) { result.Add(h); break; }
            }
            return true;
        }, IntPtr.Zero);
        return result;
    }

    public static bool ClickOk(IntPtr dlg) {
        // button with caption OK / ОК
        string[] caps = new string[] { "OK", "ОК" };
        foreach (var c in caps) {
            IntPtr b = FindWindowEx(dlg, IntPtr.Zero, "Button", c);
            if (b != IntPtr.Zero) {
                SetForegroundWindow(dlg);
                SendMessage(b, BM_CLICK, IntPtr.Zero, IntPtr.Zero);
                return true;
            }
        }
        // fallback: default push button (id 1)
        IntPtr d = FindWindowEx(dlg, IntPtr.Zero, "Button", null);
        if (d != IntPtr.Zero) { SetForegroundWindow(dlg); SendMessage(d, BM_CLICK, IntPtr.Zero, IntPtr.Zero); return true; }
        // fallback: XAML dialog (Credential Dialog Xaml Host) - use UI Automation
        try {
            var el = System.Windows.Automation.AutomationElement.FromHandle(dlg);
            if (el != null) {
                string[] names = new string[] { "ОК", "OK", "Подтвердить" };
                foreach (var nm in names) {
                    var cond = new System.Windows.Automation.OrCondition(
                        new System.Windows.Automation.PropertyCondition(
                            System.Windows.Automation.AutomationElement.NameProperty, nm),
                        new System.Windows.Automation.PropertyCondition(
                            System.Windows.Automation.AutomationElement.NameProperty, nm.ToUpperInvariant()));
                    var btn = el.FindFirst(System.Windows.Automation.TreeScope.Descendants, cond);
                    if (btn != null) {
                        var ip = btn.GetCurrentPattern(System.Windows.Automation.InvokePattern.Pattern)
                                 as System.Windows.Automation.InvokePattern;
                        if (ip != null) { ip.Invoke(); return true; }
                    }
                }
            }
        } catch { }
        return false;
    }
}
}
'@

$procNames   = @('NTClientSoftware','AvCSP','AvCrdHlr','avesetup')
$pollMs      = 500
$disarmAfter = New-TimeSpan -Minutes 3
$logFile     = Join-Path $PSScriptRoot 'CertOkWatcher.log'

function Log($m) {
    $line = '{0} {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $m
    Add-Content -Path $logFile -Value $line -Encoding Default
    # keep log small
    if ((Get-Item $logFile).Length -gt 512KB) {
        Get-Content $logFile -Tail 500 | Set-Content $logFile -Encoding Default
    }
}

$state = 'IDLE'
$lastActivity = Get-Date
Log 'Watcher started.'

while ($true) {
    try {
        $dialogs = [W.Win32]::FindCertDialogs($procNames)
        $present = ($dialogs.Count -gt 0)

        switch ($state) {
            'IDLE' {
                if ($present) {
                    Log ('First dialog appeared (pid(s) ' + (($dialogs | ForEach-Object { $p=0; [W.Win32]::GetWindowThreadProcessId($_,[ref]$p); $p }) -join ',') + '). Waiting for manual OK.')
                    $state = 'WAIT_MANUAL'
                    $lastActivity = Get-Date
                }
            }
            'WAIT_MANUAL' {
                if (-not $present) {
                    Log 'Manual OK detected. ARMED - auto-clicking until idle.'
                    $state = 'ARMED'
                    $lastActivity = Get-Date
                }
            }
            'ARMED' {
                if ($present) {
                    foreach ($d in $dialogs) {
                        if ([W.Win32]::ClickOk($d)) { Log 'Auto-clicked OK.' }
                    }
                    $lastActivity = Get-Date
                } elseif ((New-TimeSpan $lastActivity (Get-Date)) -gt $disarmAfter) {
                    Log 'No dialogs for 3 min. Disarmed to IDLE.'
                    $state = 'IDLE'
                }
            }
        }
    } catch {
        Log ('ERROR: ' + $_.Exception.Message)
    }
    Start-Sleep -Milliseconds $pollMs
}
