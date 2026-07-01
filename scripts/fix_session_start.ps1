$root = Split-Path -Parent $PSScriptRoot
$files = Get-ChildItem -Path $root -Recurse -Filter *.php |
    Where-Object { $_.FullName -notmatch '\\vendor\\' }

foreach ($f in $files) {
    $content = Get-Content -Raw -LiteralPath $f.FullName
    $orig = $content

    # session_start(); antes de config -> quitar (config.php ya inicia sesión)
    $content = $content -replace '(?ms)^<\?php\r?\n\s*session_start\(\);\s*\r?\n\s*require\s+', "<?php`r`nrequire "

    # Bloque redundante tras config
    $content = $content -replace '(?ms)(require_once\s+[^;]+config\.php[^;]*;)\s*\r?\n\s*if\s*\(\s*session_status\(\)\s*===\s*PHP_SESSION_NONE\s*\)\s*\{\s*\r?\n\s*session_start\(\);\s*\r?\n\s*\}\s*', "`$1`r`n"

    if ($content -ne $orig) {
        Set-Content -LiteralPath $f.FullName -Value $content -NoNewline
        Write-Host "Fixed: $($f.FullName)"
    }
}
