# Xdebug 3: coverage mode must be set before the PHP process starts (phpunit.xml is too late).
$ProjectRoot = Split-Path $PSScriptRoot -Parent
$env:XDEBUG_MODE = "coverage"
& php (Join-Path $ProjectRoot "bin\phpunit") @args
