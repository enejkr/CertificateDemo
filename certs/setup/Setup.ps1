param([string]$n)

$startDir = Get-Location

Set-Location 'C:\xampp\apache\bin'
$env:OPENSSL_CONF = 'C:\xampp\apache\conf\openssl.cnf'

$c = 'C:\xampp\htdocs\demo\certs'
$dir = "$c\$n"

# Ustvari mapo
New-Item -ItemType Directory -Force -Path $dir | Out-Null

# Ustvari key + CSR
.\openssl.exe req -newkey rsa:2048 -sha256 -nodes `
    -keyout "$dir\client.key" `
    -out "$dir\client.csr" `
    -subj "/CN=mtls-klient"

# Podpiši certifikat
.\openssl.exe x509 -req `
    -in "$dir\client.csr" `
    -CA "$c\ca.crt" `
    -CAkey "$c\ca.key" `
    -CAcreateserial `
    -out "$dir\client.crt" `
    -days 825 `
    -sha256

# Fingerprint
$fingerprint = (.\openssl.exe x509 `
    -in "$dir\client.crt" `
    -noout `
    -fingerprint `
    -sha256) -replace 'SHA256 Fingerprint=', '' -replace ':', ''

# Vstavi v bazo
$sql = "INSERT INTO users (username, certificate_fingerprint) VALUES ('$n', '$fingerprint');"

& 'C:\xampp\mysql\bin\mysql.exe' -u root mtls_demo -e $sql

Write-Host ""
Write-Host "Uporabnik: $n"
Write-Host "Fingerprint: $fingerprint"
Write-Host "Certifikat: $dir\client.crt"
Write-Host "Koncano."

# Vrni se v začetno mapo
Set-Location $startDir