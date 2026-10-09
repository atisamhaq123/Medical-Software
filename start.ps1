$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
$medicaPhpCommand = Get-Command php -ErrorAction SilentlyContinue
if ($medicaPhpCommand) {
    $medicaPhpPath = $medicaPhpCommand.Source
} elseif (Test-Path 'C:\wamp64\bin\php\php8.4.15\php.exe') {
    $medicaPhpPath = 'C:\wamp64\bin\php\php8.4.15\php.exe'
} else {
    throw 'PHP 8.3 or newer is required. Add PHP to PATH, then run this script again.'
}
& $medicaPhpPath -d xdebug.mode=off artisan serve --host=127.0.0.1 --port=8000
