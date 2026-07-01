$root = Split-Path -Parent $PSScriptRoot
$patterns = @(
    '(?ms)^<\?php\r?\n\s*session_start\(\);\s*\r?\n',
    '(?ms)\r?\n\s*session_start\(\);\s*\r?\n\s*require\s+',
    '(?ms)if\s*\(\s*session_status\(\)\s*===\s*PHP_SESSION_NONE\s*\)\s*\{\s*\r?\n\s*session_start\(\);\s*\r?\n\s*\}\s*'
)

Get-ChildItem -Path $root -Recurse -Filter *.php | ForEach-Object {
    $content = Get-Content -Raw -LiteralPath $_.FullName
    $orig = $content
    foreach ($p in $patterns) {
        if ($p -match 'session_start.*require') {
            $content = $content -replace $p, "`r`nrequire "
        } elseif ($p -match 'session_status') {
            $content = $content -replace $p, ''
        } else {
            $content = $content -replace $p, "<?php`r`n"
        }
    }
    if ($content -ne $orig) {
        Set-Content -LiteralPath $_.FullName -Value $content -NoNewline
        Write-Host "Fixed: $($_.Name)"
    }
}
