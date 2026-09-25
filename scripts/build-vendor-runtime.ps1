param([Parameter(Mandatory = $true)][string]$OutputDirectory)

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$vendor = Join-Path $root 'vendor'
$target = [IO.Path]::GetFullPath($OutputDirectory)
if (Test-Path -LiteralPath $target) { throw 'Choose a new, empty output path.' }

# Explicit runtime roots keep package tests, examples and tooling out of PROD.
$packages = [ordered]@{
    'catfan/medoo' = @('src')
    'graham-campbell/result-type' = @('src')
    'phpmailer/phpmailer' = @('src', 'language')
    'phpoption/phpoption' = @('src')
    'symfony/polyfill-ctype' = @('.')
    'symfony/polyfill-mbstring' = @('.')
    'symfony/polyfill-php80' = @('.')
    'vlucas/phpdotenv' = @('src')
}
$locked = (Get-Content -LiteralPath (Join-Path $root 'composer.lock') -Raw | ConvertFrom-Json).packages
$installed = (Get-Content -LiteralPath (Join-Path $vendor 'composer/installed.json') -Raw | ConvertFrom-Json).packages
if (@($locked).Count -ne $packages.Count -or @($installed).Count -ne $packages.Count) {
    throw 'Package inventory changed: review the runtime allowlist.'
}
foreach ($package in $locked) {
    if (!$packages.Contains($package.name)) { throw "Unreviewed package: $($package.name)" }
    $match = @($installed | Where-Object { $_.name -eq $package.name -and $_.version -eq $package.version -and $_.source.reference -eq $package.source.reference })
    if ($match.Count -ne 1) { throw "Run composer install first: $($package.name) differs from lock." }
}

function Copy-RuntimeFile([string]$Source) {
    $relative = $Source.Substring($vendor.Length).TrimStart('\', '/')
    $destination = Join-Path $target $relative
    New-Item -ItemType Directory -Path (Split-Path $destination -Parent) -Force | Out-Null
    Copy-Item -LiteralPath $Source -Destination $destination
}

Copy-RuntimeFile (Join-Path $vendor 'autoload.php')
Get-ChildItem -LiteralPath (Join-Path $vendor 'composer') -File |
    Where-Object { $_.Extension -eq '.php' -or $_.Name -in @('LICENSE', 'installed.json') } |
    ForEach-Object { Copy-RuntimeFile $_.FullName }

foreach ($name in $packages.Keys) {
    $packageRoot = Join-Path $vendor $name
    foreach ($directory in $packages[$name]) {
        Get-ChildItem -LiteralPath (Join-Path $packageRoot $directory) -File -Recurse |
            Where-Object { $_.Extension -in @('.php', '.stub') } |
            ForEach-Object { Copy-RuntimeFile $_.FullName }
    }
    Get-ChildItem -LiteralPath $packageRoot -File |
        Where-Object { $_.Name -match '^LICENSE(?:\..+)?$' } |
        ForEach-Object { Copy-RuntimeFile $_.FullName }
}
Write-Output "Runtime vendor ready: $target ($((Get-ChildItem -LiteralPath $target -File -Recurse).Count) files)."
