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
function Req($url, $method, $body, $ctype) {
  $req = [System.Net.WebRequest]::Create($url)
  $req.Timeout = 15000
  $req.CookieContainer = $script:cc
  if ($method -ne "GET") {
    $req.Method = $method
    $req.ContentType = $ctype
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($body)
    $req.ContentLength = $bytes.Length
    $st = $req.GetRequestStream(); $st.Write($bytes, 0, $bytes.Length); $st.Close()
  }
  try {
    $resp = $req.GetResponse()
    $sr = New-Object System.IO.StreamReader($resp.GetResponseStream(), [System.Text.Encoding]::UTF8)
    $c = $sr.ReadToEnd()
    $sr.Close(); $resp.Close()
    return @{ Status = "OK"; Content = $c }
  } catch [System.Net.WebException] {
    $resp = $_.Exception.Response
    $sr = New-Object System.IO.StreamReader($resp.GetResponseStream(), [System.Text.Encoding]::UTF8)
    $c = $sr.ReadToEnd()
    $sr.Close(); $resp.Close()
    return @{ Status = "ERR " + $resp.StatusCode; Content = $c }
  }
}
Req "http://localhost:8000/admin" "GET" $null $null | Out-Null
Req "http://localhost:8000/admin" "POST" ("user=admin&pass=" + [uri]::EscapeDataString($env:CMS_ADMIN_PASSWORD)) "application/x-www-form-urlencoded" | Out-Null

$h1 = Req "http://localhost:8000/" "GET" $null $null
foreach ($pat in "Czyścimy m.in.","Nasza firma świadczy","Jak działa","Gdzie sprawdza się") {
  if ($h1.Content -match [Regex]::Escape($pat)) { Write-Output ("  OK glowna: " + $pat) } else { Write-Output ("  BRAK: " + $pat) }
}

# edycja ustawienia
$s = Req "http://localhost:8000/admin/settings" "GET" $null $null
$tok = [regex]::Match($s.Content, 'name="_token" value="([^"]+)"').Groups[1].Value
Write-Output ("token length: " + $tok.Length)
$newBody = "_token=" + [uri]::EscapeDataString($tok) + "&settings%5Bcontact.phone%5D=%2B48+999+888+777&settings%5Bcontact.phoneHref%5D=tel%3A%2B48999888777"
$r = Req "http://localhost:8000/admin/settings" "POST" $newBody "application/x-www-form-urlencoded"
Write-Output ("zapis ustawien: " + $r.Status)
if ($r.Status -ne "OK") { Write-Output $r.Content.Substring(0, [Math]::Min(800, $r.Content.Length)) }
$h2 = Req "http://localhost:8000/" "GET" $null $null
if ($h2.Content -match "999 888 777") { Write-Output "  OK: nowy telefon widoczny" } else { Write-Output "  BRAK: nowy telefon NIE widoczny" }

# dodanie bloku
$s2 = Req "http://localhost:8000/admin/blocks/strip" "GET" $null $null
$tok2 = [regex]::Match($s2.Content, 'name="_token" value="([^"]+)"').Groups[1].Value
$addBody = "_action=add&_token=" + [uri]::EscapeDataString($tok2) + "&fields%5Bvalue%5D=TESTOWY+MATERIAL"
$r2 = Req "http://localhost:8000/admin/blocks/strip" "POST" $addBody "application/x-www-form-urlencoded"
Write-Output ("dodanie bloku: " + $r2.Status)
if ($r2.Status -ne "OK") { Write-Output $r2.Content.Substring(0, [Math]::Min(800, $r2.Content.Length)) }
$h3 = Req "http://localhost:8000/" "GET" $null $null
if ($h3.Content -match "TESTOWY MATERIAL") { Write-Output "  OK: nowy blok widoczny" } else { Write-Output "  BRAK: nowy blok NIE widoczny" }

# usuniecie dodanego bloku
$s3 = Req "http://localhost:8000/admin/blocks/strip" "GET" $null $null
$ids = [regex]::Matches($s3.Content, 'name="_id" value="(\d+)"') | ForEach-Object { $_.Groups[1].Value }
$lastId = $ids[$ids.Count - 1]
$delBody = "_action=delete&_id=" + $lastId + "&_token=" + [uri]::EscapeDataString($tok2)
$r3 = Req "http://localhost:8000/admin/blocks/strip" "POST" $delBody "application/x-www-form-urlencoded"
Write-Output ("usuniecie bloku: " + $r3.Status)
$h4 = Req "http://localhost:8000/" "GET" $null $null
if ($h4.Content -match "TESTOWY MATERIAL") { Write-Output "  BRAK: blok wciąż widoczny" } else { Write-Output "  OK: blok usuniety" }

# przywrocenie telefonu
$tokNow = [regex]::Match((Req "http://localhost:8000/admin/settings" "GET" $null $null).Content, 'name="_token" value="([^"]+)"').Groups[1].Value
$restoreBody = "_token=" + [uri]::EscapeDataString($tokNow) + "&settings%5Bcontact.phone%5D=%2B48+501+965+485&settings%5Bcontact.phoneHref%5D=tel%3A%2B48501965485"
Req "http://localhost:8000/admin/settings" "POST" $restoreBody "application/x-www-form-urlencoded" | Out-Null
$h5 = Req "http://localhost:8000/" "GET" $null $null
if ($h5.Content -match "501 965 485") { Write-Output "  OK: telefon przywrocony" } else { Write-Output "  BRAK: telefon NIE przywrocony" }

Stop-Job $job
Remove-Job $job
Write-Output "TEST FUNKCJONALNY ZAKONCZONY"
