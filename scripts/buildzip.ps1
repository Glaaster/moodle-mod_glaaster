# scripts/buildzip.ps1
#
# PowerShell equivalent of scripts/buildzip.sh (Windows PowerShell 5.1 and PowerShell 7+).
#
# Usage:
#   .\scripts\buildzip.ps1 <plugin_type> <plugin_name> <plugin_version> <moodle_channel>
# Example:
#   .\scripts\buildzip.ps1 mod glaaster dev
#   .\scripts\buildzip.ps1 mod glaaster 1.2.3 4.1
#
# Compress-Archive is not used on purpose: on Windows PowerShell 5.1 it writes entries with
# backslash separators, which Moodle's plugin installer rejects. Entries are written here with
# forward slashes, like `zip -r`.

param(
    [string]$PluginType = 'mod',
    [string]$PluginName = 'glaaster',
    [string]$PluginVersion = 'dev',
    [string]$MoodleChannel = ''
)

$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$PluginType = $PluginType.ToLower()

# Suffix for Moodle channel (none on main).
if ([string]::IsNullOrEmpty($MoodleChannel) -or $MoodleChannel -eq 'main' -or $MoodleChannel -eq 'undefined') {
    $ChannelSuffix = ''
} else {
    $ChannelSuffix = "_moodle$MoodleChannel"
}

$ZipName = "${PluginType}_${PluginName}_v${PluginVersion}${ChannelSuffix}.zip"

# Project root = parent of the scripts folder.
$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$TypeDir = Join-Path $ProjectRoot $PluginType
$PluginDir = Join-Path $TypeDir $PluginName
$ZipPath = Join-Path $ProjectRoot $ZipName

if (-not (Test-Path $PluginDir -PathType Container)) {
    Write-Error "Error: '$PluginType/$PluginName' folder not found."
    exit 1
}

if (Test-Path $ZipPath) {
    Remove-Item $ZipPath -Force
}

$TypeDirFull = (Resolve-Path $TypeDir).Path.TrimEnd('\', '/')
$PluginDirFull = (Resolve-Path $PluginDir).Path

$zip = [System.IO.Compression.ZipFile]::Open($ZipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    # Root folder entry ("glaaster/"), then every sub folder and file, all relative to the type dir.
    $items = @(Get-Item -LiteralPath $PluginDirFull -Force) + @(Get-ChildItem -LiteralPath $PluginDirFull -Recurse -Force)
    foreach ($item in $items) {
        $relative = $item.FullName.Substring($TypeDirFull.Length + 1) -replace '\\', '/'
        if ($item.PSIsContainer) {
            [void]$zip.CreateEntry("$relative/")
        } else {
            [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $zip, $item.FullName, $relative, [System.IO.Compression.CompressionLevel]::Optimal
            )
        }
    }
} finally {
    $zip.Dispose()
}

Write-Host "Package created: $ZipName"
