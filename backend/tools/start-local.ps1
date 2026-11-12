$ErrorActionPreference = 'Stop'
$backendRoot = Split-Path -Parent $PSScriptRoot
$uploadDirectory = Join-Path $backendRoot 'storage/app/private/php-upload'
New-Item -ItemType Directory -Path $uploadDirectory -Force | Out-Null
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
$phpBinary = if ($phpCommand) { $phpCommand.Source } else { 'C:/xampp/php/php.exe' }
if (-not (Test-Path -LiteralPath $phpBinary)) { throw 'PHP was not found. Install PHP or add it to PATH.' }
$router = Join-Path $backendRoot 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
$errorLog = Join-Path $backendRoot 'storage/logs/php-local.log'
Push-Location (Join-Path $backendRoot 'public')
try {
    & $phpBinary -d "upload_tmp_dir=$uploadDirectory" -d 'display_errors=0' -d 'display_startup_errors=0' -d 'log_errors=1' -d "error_log=$errorLog" -d 'upload_max_filesize=10M' -d 'post_max_size=170M' -d 'max_file_uploads=16' -S 'localhost:8000' $router
    exit $LASTEXITCODE
} finally {
    Pop-Location
}
