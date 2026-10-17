$proj = "C:\Users\Damian\laser-symfony"
$job = Start-Job -ScriptBlock {
  param($proj)
  Set-Location $proj
  php -S localhost:8000 -t "$proj\public" "$proj\public\router.php" 2>&1
} -ArgumentList $proj
$html = ""
for ($i = 0; $i -lt 25; $i++) {
  Start-Sleep -Seconds 1
  try {
    $req = [System.Net.WebRequest]::Create("http://localhost:8000/")
    $req.Timeout = 3000
    $resp = $req.GetResponse()
    $sr = New-Object System.IO.StreamReader($resp.GetResponseStream())
    $html = $sr.ReadToEnd()
    $sr.Close(); $resp.Close()
    break
  } catch {}
}
Write-Output "=== Tresc odpowiedzi ==="
Write-Output $html
Write-Output "=== koniec ==="
Stop-Job $job
Remove-Job $job
