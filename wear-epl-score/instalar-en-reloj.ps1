param(
    [Parameter(Mandatory = $true)]
    [string]$Ip,
    [Parameter(Mandatory = $true)]
    [ValidateRange(1, 65535)]
    [int]$PuertoVinculacion,
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^\d{6}$')]
    [string]$Codigo,
    [Parameter(Mandatory = $true)]
    [ValidateRange(1, 65535)]
    [int]$PuertoConexion
)

$parsedIp = $null
if (-not [System.Net.IPAddress]::TryParse($Ip, [ref]$parsedIp)) {
    throw "La dirección IP del reloj no es válida."
}

$adbCandidates = @(
    (Join-Path $env:LOCALAPPDATA 'Android\Sdk\platform-tools\adb.exe'),
    (Join-Path $env:USERPROFILE '.cache\epl-wear-android\sdk\platform-tools\adb.exe')
)
$adb = $adbCandidates | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
if (-not $adb) {
    throw "No se encontró ADB. Ejecuta primero la preparación de Android del proyecto."
}

$apk = Join-Path $PSScriptRoot 'apk\EPL-Score-Watch-FE.apk'
if (-not (Test-Path -LiteralPath $apk)) {
    throw "No se encontró el APK en $apk"
}

$env:ANDROID_USER_HOME = Join-Path $env:USERPROFILE '.cache\epl-wear-android\adb-home'
New-Item -ItemType Directory -Force -Path $env:ANDROID_USER_HOME | Out-Null

$pairEndpoint = $Ip + ':' + $PuertoVinculacion
$connectEndpoint = $Ip + ':' + $PuertoConexion

Write-Host "Vinculando el Galaxy Watch..." -ForegroundColor Cyan
& $adb pair $pairEndpoint $Codigo
if ($LASTEXITCODE -ne 0) {
    throw "No se pudo vincular el reloj. Verifica el puerto y el código."
}

Write-Host "Conectando..." -ForegroundColor Cyan
& $adb connect $connectEndpoint
if ($LASTEXITCODE -ne 0) {
    throw "No se pudo conectar al reloj. El puerto de conexión es distinto al de vinculación."
}

Write-Host "Instalando EPL Score..." -ForegroundColor Cyan
& $adb -s $connectEndpoint install -r $apk
if ($LASTEXITCODE -ne 0) {
    throw "La instalación no terminó correctamente."
}

Write-Host "EPL Score quedó instalado en tu reloj." -ForegroundColor Green

