# Recompila Tailwind CSS estático desde las vistas PHP.
# Uso: .\scripts\compilar_tailwind.ps1
# Ejecutar cada vez que agregues nuevas clases Tailwind a los PHP.

Set-Location $PSScriptRoot\..

Write-Host "Compilando Tailwind..." -ForegroundColor Cyan

cmd /c "node_modules\.bin\tailwindcss -i assets/css/tailwind.src.css -o assets/css/tailwind.min.css --minify 2>&1"

if ($LASTEXITCODE -eq 0) {
    $size = (Get-Item "assets\css\tailwind.min.css").Length
    Write-Host "Listo: assets/css/tailwind.min.css ($([math]::Round($size/1024, 1))KB)" -ForegroundColor Green
} else {
    Write-Host "Error al compilar Tailwind." -ForegroundColor Red
}
