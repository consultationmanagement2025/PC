$path = Join-Path $PSScriptRoot '..\public-portal.php' | Resolve-Path -ErrorAction Stop
$lines = Get-Content $path
$balance = 0
for ($i = 0; $i -lt $lines.Count; $i++) {
    $line = $lines[$i]
    $opens = ([regex]::Matches($line,'\{')).Count
    $closes = ([regex]::Matches($line,'\}')).Count
    $balance += $opens - $closes
    if ($opens -gt 0 -or $closes -gt 0) {
        "{0,4}: {1,3}  +{2} -{3}  {4}" -f ($i+1), $balance, $opens, $closes, $line.Trim()
    }
}
"FINAL BALANCE: $balance" | Write-Output
if ($balance -ne 0) { exit 2 } else { exit 0 }