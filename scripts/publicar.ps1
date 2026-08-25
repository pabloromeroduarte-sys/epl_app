# Publicar SOLO cuando ya probaste en local y aprobaste el cambio.
#
# Flujo habitual (GitHub + VPS de una vez):
#   .\scripts\publicar.ps1 -Aprobado -Mensaje "descripción del cambio"
#
# Solo GitHub (sin tocar el VPS):
#   .\scripts\publicar.ps1 -Mensaje "descripción"
#
#   .\scripts\publicar.ps1 -SoloStatus

param(
    [string]$Mensaje = "",
    [switch]$Desplegar,
    [switch]$Aprobado,
    [switch]$SoloStatus
)

if ($Aprobado) {
    $Desplegar = $true
}

$ErrorActionPreference = "Stop"
$root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
Set-Location $root

function Get-DeployToken {
    if ($env:DEPLOY_TOKEN) { return $env:DEPLOY_TOKEN.Trim() }
    $envFile = Join-Path $root ".env"
    if (-not (Test-Path $envFile)) { return $null }
    foreach ($line in Get-Content $envFile -Encoding UTF8) {
        if ($line -match '^\s*DEPLOY_TOKEN\s*=\s*(.+)\s*$') {
            return $matches[1].Trim().Trim('"').Trim("'")
        }
    }
    return $null
}

Write-Host "=== EPL publicar ===" -ForegroundColor Cyan
Write-Host "Rama: $(git branch --show-current)" -ForegroundColor Gray
git status -sb

if ($SoloStatus) { exit 0 }

$status = git status --porcelain
if (-not $status) {
    Write-Host "No hay cambios que commitear." -ForegroundColor Yellow
    if (-not $Desplegar) { exit 0 }
}
else {
    if (-not $Mensaje.Trim()) {
        Write-Error "Indica -Mensaje 'descripción del cambio'"
    }
    git add -A
  # No subir secretos por accidente
    git reset HEAD .env 2>$null
    git reset HEAD .env.* 2>$null
    git reset HEAD .claude 2>$null
    $staged = git diff --cached --name-only
    if (-not $staged) {
        Write-Host "Tras excluir .env no queda nada que commitear." -ForegroundColor Yellow
        if (-not $Desplegar) { exit 0 }
    }
    else {
        git commit -m $Mensaje.Trim()
        Write-Host "Commit creado." -ForegroundColor Green
    }
}

$branch = git branch --show-current
if (-not $branch) { $branch = "main" }

Write-Host "Push a origin/$branch ..." -ForegroundColor Cyan
git push origin $branch
Write-Host "GitHub actualizado." -ForegroundColor Green

# Sincronizar local (XAMPP) automáticamente
Write-Host "Sincronizando local..." -ForegroundColor Cyan
git -C $root pull origin main --ff-only 2>$null
Write-Host "Local sincronizado." -ForegroundColor Green

if ($Desplegar) {
    $token = Get-DeployToken
    if (-not $token) {
        Write-Error "DEPLOY_TOKEN no encontrado. Añádelo en .env o variable de entorno DEPLOY_TOKEN."
    }
    # Windows PowerShell 5.1 no negocia TLS 1.2 por defecto; forzarlo y confiar en el cert del propio VPS.
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    if (-not ("TrustAllEPL" -as [type])) {
        Add-Type @"
using System.Net; using System.Security.Cryptography.X509Certificates;
public class TrustAllEPL : ICertificatePolicy {
  public bool CheckValidationResult(ServicePoint sp, X509Certificate cert, WebRequest req, int problem) { return true; }
}
"@
    }
    [System.Net.ServicePointManager]::CertificatePolicy = New-Object TrustAllEPL
    $urls = @(
        "https://epleague.cl/deploy_webhook.php?token=$token",
        "https://padel.165.227.109.215.nip.io/deploy_webhook.php?token=$token"
    )
    $ok = $false
    foreach ($url in $urls) {
        try {
            Write-Host "Deploy: $url" -ForegroundColor Cyan
            $r = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 120
            Write-Host $r.Content
            $ok = $true
            break
        }
        catch {
            Write-Host "Fallo: $($_.Exception.Message)" -ForegroundColor Yellow
        }
    }
    if (-not $ok) { exit 1 }
    Write-Host "VPS actualizado." -ForegroundColor Green
}
