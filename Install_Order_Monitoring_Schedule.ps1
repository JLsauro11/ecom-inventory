param([string]$ApplicationPath=$PSScriptRoot,[string]$PhpPath='C:\xampp\php\php.exe')
$ErrorActionPreference='Stop'
$app=(Resolve-Path -LiteralPath $ApplicationPath).Path
$job=Join-Path $app 'order_monitor_job.php'
if(!(Test-Path -LiteralPath $job -PathType Leaf) -or !(Test-Path -LiteralPath $PhpPath -PathType Leaf)){throw 'PHP or the Order Monitoring job is missing.'}
$taskName='RS8 Order Monitoring - '+(Split-Path $app -Leaf)
$script="& '"+$PhpPath.Replace("'","''")+"' '"+$job.Replace("'","''")+"'"
$encoded=[Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($script))
$action=New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -NonInteractive -WindowStyle Hidden -EncodedCommand '+$encoded)
$trigger=New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 5)
$settings=New-ScheduledTaskSettingsSet -Hidden -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Description 'Checks inventory order alerts locally. No marketplace API; no inventory posting.' -Force | Out-Null
Write-Output "Installed: $taskName. Checks every 5 minutes while this Windows user session and the host are running. Application sign-in is not required."
