if ([string]::IsNullOrWhiteSpace($env:CMS_ADMIN_PASSWORD)) {
  throw "Set CMS_ADMIN_PASSWORD before running this script."
}
$proj = "C:\Users\Damian\laser-symfony"
$job = Start-Job -ScriptBlock {
  param($proj)
  Set-Location $proj
  php -S localhost:8000 -t "$proj\public" "$proj\public\devrouter.php" 2>&1
} -ArgumentList $proj
$cc = New-Object System.Net.CookieContainer
$req = [System.Net.WebRequest]::Create("http://localhost:8000/admin")
$req.Timeout = 5000
$req.CookieContainer = $cc
try { $req.GetResponse().Close() } catch {}
$req = [System.Net.WebRequest]::Create("http://localhost:8000/admin")
$req.Method = "POST"
$req.Timeout = 5000
$req.ContentType = "application/x-www-form-urlencoded"
$req.CookieContainer = $cc
$body = [System.Text.Encoding]::ASCII.GetBytes("user=admin&pass=" + [uri]::EscapeDataString($env:CMS_ADMIN_PASSWORD))
$req.ContentLength = $body.Length
$st = $req.GetRequestStream(); $st.Write($body, 0, $body.Length); $st.Close()
try { $req.GetResponse().Close() } catch {}
$req = [System.Net.WebRequest]::Create("http://localhost:8000/admin/settings")
$req.Timeout = 8000
$req.CookieContainer = $cc
try {
  $resp = $req.GetResponse()
  $sr = New-Object System.IO.StreamReader($resp.GetResponseStream())
  $c = $sr.ReadToEnd()
  $sr.Close(); $resp.Close()
  Write-Output "=== SETTINGS 200 ==="
  Write-Output $c.Substring(0, [Math]::Min(3000, $c.Length))
} catch [System.Net.WebException] {
  $resp = $_.Exception.Response
  $sr = New-Object System.IO.StreamReader($resp.GetResponseStream())
  $c = $sr.ReadToEnd()
  $sr.Close(); $resp.Close()
  Write-Output ("=== SETTINGS " + $resp.StatusCode + " ===")
  Write-Output $c.Substring(0, [Math]::Min(3000, $c.Length))
}
Stop-Job $job
Remove-Job $job
