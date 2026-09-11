Set-Location 'C:\xampp\apache\bin'
$env:OPENSSL_CONF = 'C:\xampp\apache\conf\openssl.cnf'
$c = 'C:\xampp\htdocs\demo\certs'
$n = 'clientD'
New-Item -ItemType Directory -Force -Path "$c\$n"
.\openssl.exe req -newkey rsa:2048 -sha256 -nodes -keyout "$c\$n\client.key" -out "$c\$n\client.csr" -subj "/CN=mtls-klient"
.\openssl.exe x509 -req -in "$c\$n\client.csr" -CA "$c\ca.crt" -CAkey "$c\ca.key" -CAcreateserial -out "$c\$n\client.crt" -days 825 -sha256
