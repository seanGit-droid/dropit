param()
# Targeted repair: replace U+FFFD replacement chars with the correct characters
# based on the context (surrounding chars tell us what was lost).
# Also replace literal '?' that replaced the em dash in the title.

$repairs = @(
    @{ File='app\views\partials\head.php';     Old="?' '";   New="— " },
    @{ File='app\views\partials\head.php';     Old=" ? ";    New=" — " },
    @{ File='app\views\admin\assign_driver.php'; Old="New fare (?)"; New="New fare (P)" },
    @{ File='app\views\admin\assign_driver.php'; Old='(?)';  New="(P)" }
)

# General: scan all PHP files for U+FFFD and report
$repl = [char]0xFFFD
$dirs = @('app','resources','scheme')
$found = 0
foreach ($dir in $dirs) {
    if (-not (Test-Path $dir)) { continue }
    $files = Get-ChildItem -Recurse -Filter '*.php' -Path $dir -ErrorAction SilentlyContinue
    foreach ($f in $files) {
        $content = [System.IO.File]::ReadAllText($f.FullName, [System.Text.Encoding]::UTF8)
        if ($content.Contains($repl)) {
            Write-Host "HAS REPLACEMENT CHAR: $($f.Name)"
            # Fix known patterns: peso sign was U+20B1, em dash was U+2014
            # In dispatch: '(?)' = '(P)' for peso, modal label
            $fixed = $content
            # Replace replacement char in label context "(&#xFFFD;)" -> "(P)"
            $fixed = $fixed -replace "\(${repl}\)", "(P)"
            # Replace replacement char used as dash separator in title
            $fixed = $fixed -replace " ${repl} ", " - "
            if ($fixed -ne $content) {
                [System.IO.File]::WriteAllText($f.FullName, $fixed, [System.Text.UTF8Encoding]::new($false))
                Write-Host "  Fixed replacement chars in $($f.Name)"
                $found++
            }
        }
    }
}

if ($found -eq 0) { Write-Host "No replacement chars found - files are clean" }

# Now fix head.php title specifically - the em dash
$hf = 'app\views\partials\head.php'
$hc = [System.IO.File]::ReadAllText((Resolve-Path $hf), [System.Text.Encoding]::UTF8)
# The title had: esc($page_title).' — '.APP_NAME
# After corruption it shows as: esc($page_title).' ? '.APP_NAME  or with replacement char
# Normalize: replace any single ? or replacement char between quotes with em dash
$hc2 = $hc -replace "'\.\s*['`"]\s*[?$repl]+\s*['`"]\s*\.", "'.' . chr(0x2014) . '."
if ($hc2 -ne $hc) {
    Write-Host "Fixed title separator"
} else {
    Write-Host "Title separator pattern not matched - checking raw..."
    # Show chars around 'page_title'
    $idx = $hc.IndexOf('page_title')
    if ($idx -gt 0) {
        $snippet = $hc.Substring([Math]::Max(0,$idx-5), [Math]::Min(80,$hc.Length-$idx+5))
        Write-Host "Context: $snippet"
        # Show byte values
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($snippet)
        Write-Host "Bytes: $($bytes -join ' ')"
    }
}
Write-Host "Done"
