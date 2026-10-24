param([string]$PhpPath='C:\xampp\php\php.exe',[string]$Time='02:00')
$ErrorActionPreference='Stop'
$appPath=(Resolve-Path -LiteralPath $PSScriptRoot).Path
$jobPath=Join-Path $appPath 'backup_job.php'
if(!(Test-Path -LiteralPath $PhpPath) -or !(Test-Path -LiteralPath $jobPath)){throw 'PHP or backup_job.php was not found.'}
$scheduleTime=[datetime]::ParseExact($Time,'HH:mm',[Globalization.CultureInfo]::InvariantCulture)
$taskSuffix=([BitConverter]::ToString([Security.Cryptography.SHA256]::Create().ComputeHash([Text.Encoding]::UTF8.GetBytes($appPath))).Replace('-','')).Substring(0,12)
$taskName='RS8 Inventory Backup '+$taskSuffix
$action=New-ScheduledTaskAction -Execute $PhpPath -Argument ('"'+$jobPath+'"') -WorkingDirectory $appPath
$trigger=New-ScheduledTaskTrigger -Daily -At $scheduleTime
$settings=New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew
$principal=New-ScheduledTaskPrincipal -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Description 'Daily private database, application and attachment backup. Runs while this Windows user is signed in; catches up when available.' -Force | Out-Null
Write-Output ('Installed '+$taskName+' daily at '+$Time+'. View Backups in the system to verify successful runs. Keep MySQL running.')
