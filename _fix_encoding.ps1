param()
$fixed = 0
$clean = 0
$dirs  = @('app','resources','scheme')

foreach ($dir in $dirs) {
    if (-not (Test-Path $dir)) { continue }
    $files = Get-ChildItem -Recurse -Filter '*.php' -Path $dir -ErrorAction SilentlyContinue
    foreach ($f in $files) {
        $bytes  = [System.IO.File]::ReadAllBytes($f.FullName)
        $asUtf8 = [System.Text.Encoding]::UTF8.GetString($bytes)

        # Detect double-encoding: look for CP1252-decoded chars that are
        # impossible in normal PHP source (multi-byte UTF-8 headers misread).
        # 0xE2 0x80 -> U+00E2 + U+20AC in CP1252 (em-dash, ellipsis prefix)
        # 0xF0 0x9F -> U+00F0 + U+0178 in CP1252 (emoji prefix)
        # 0xC2 0xA9 -> U+00C2 + U+00A9 in CP1252 (copyright sign double-encoded)
        $hasMojibake = $false
        $b = $bytes
        for ($i = 0; $i -lt $b.Length - 1; $i++) {
            $b0 = $b[$i]; $b1 = $b[$i+1]
            # Pattern 1: 0xC3 or 0xC2 followed by 0x80-0xBF (CP1252 double-encode of 2-byte UTF-8)
            if (($b0 -eq 0xC3 -or $b0 -eq 0xC2) -and $b1 -ge 0x80 -and $b1 -le 0xBF) {
                $hasMojibake = $true; break
            }
            # Pattern 2: 0xE2 followed by 0x82 or 0x80 (CP1252 double-encode of em-dash/euro/etc)
            if ($b0 -eq 0xE2 -and ($b1 -eq 0x82 -or $b1 -eq 0x80 -or $b1 -eq 0x80)) {
                $hasMojibake = $true; break
            }
            # Pattern 3: 0xC3 0xB0 followed by 0xC5 0xB8 (double-encoded emoji F0 9F)
            if ($b0 -eq 0xC3 -and $b1 -eq 0xB0 -and $i+3 -lt $b.Length -and $b[$i+2] -eq 0xC5 -and $b[$i+3] -eq 0xB8) {
                $hasMojibake = $true; break
            }
        }

        if (-not $hasMojibake) { $clean++; continue }

        try {
            $enc1252   = [System.Text.Encoding]::GetEncoding(1252,
                [System.Text.EncoderFallback]::ExceptionFallback,
                [System.Text.DecoderFallback]::ReplacementFallback)
            $origBytes = $enc1252.GetBytes($asUtf8)
            $restored  = [System.Text.Encoding]::UTF8.GetString($origBytes)
            if ($restored -ne $asUtf8) {
                [System.IO.File]::WriteAllText($f.FullName, $restored, [System.Text.UTF8Encoding]::new($false))
                Write-Host "FIXED:  $($f.Name)"
                $fixed++
            } else {
                $clean++
            }
        } catch {
            $msg = $_.Exception.Message -replace "[\r\n].*",""
            Write-Host "SKIP:   $($f.Name) -- $msg"
        }
    }
}

Write-Host ""
Write-Host "Done -- fixed: $fixed | already clean: $clean"
