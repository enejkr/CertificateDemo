# Nastavitev in uporaba skripte `create-client.ps1`

Skripta `create-client.ps1` se uporablja za avtomatsko ustvarjanje novih mTLS uporabnikov. Namesto da bi za vsakega uporabnika ročno izvajali ukaze OpenSSL, skripta celoten postopek izvede samodejno.

## 1. Predpogoji

Pred uporabo skripte morajo biti nameščeni in pravilno nastavljeni:

- XAMPP,
- Apache z OpenSSL,
- MySQL,
- ustvarjen Root CA certifikat,
- ustvarjena podatkovna baza `mtls_demo`,
- tabela `users`.

Pri privzeti XAMPP namestitvi se uporabljata:

```text
C:\xampp\apache\bin\openssl.exe
C:\xampp\mysql\bin\mysql.exe
```

Root CA mora biti ustvarjen pred uporabo skripte in mora obstajati:

```text
C:\xampp\htdocs\demo\certs\ca.crt
C:\xampp\htdocs\demo\certs\ca.key
```

Priporočena struktura map je:

```text
C:\xampp\htdocs\demo\
└── certs\
    ├── ca.key
    ├── ca.crt
    ├── ca.srl
    ├── server\
    │   ├── server.key
    │   ├── server.csr
    │   └── server.crt
    └── client\
        ├── client.key
        ├── client.csr
        └── client.crt
```

## 2. Ustvarjanje skripte

V projektu ustvarimo datoteko:

```text
create-client.ps1
```

Na primer:

```text
C:\xampp\htdocs\demo\create-client.ps1
```

V datoteko se vstavi naslednja vsebina:

```powershell
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
```

## 3. Dovoljenje za izvajanje PowerShell skript

Če PowerShell preprečuje izvajanje lokalnih skript, je potrebno nastaviti ustrezno Execution Policy.

PowerShell zaženemo in preverimo trenutno nastavitev:

```powershell
Get-ExecutionPolicy
```

Če izvajanje skript ni dovoljeno, lahko za trenutnega uporabnika nastavimo:

```powershell
Set-ExecutionPolicy -Scope CurrentUser RemoteSigned
```

Nastavitev potrdimo z `Y`.

Nato ponovno preverimo:

```powershell
Get-ExecutionPolicy
```

Pri uspešni nastavitvi mora biti za trenutnega uporabnika omogočeno izvajanje lokalnih skript.

## 4. Priprava podatkovne baze

Skripta pričakuje podatkovno bazo:

```text
mtls_demo
```

in tabelo:

```text
users
```

Primer SQL ukaza za izdelavo baze in tabele:

```sql
CREATE DATABASE mtls_demo;

USE mtls_demo;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    certificate_fingerprint VARCHAR(64) NOT NULL
);
```

Bazo lahko ustvarimo tudi neposredno iz PowerShella:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -e "CREATE DATABASE IF NOT EXISTS mtls_demo;"
```

Nato ustvarimo tabelo:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root mtls_demo -e "CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(100) NOT NULL, certificate_fingerprint VARCHAR(64) NOT NULL);"
```

Če je MySQL zaščiten z geslom, je potrebno ukaz ustrezno prilagoditi in uporabiti prijavo z uporabniškim imenom ter geslom.

## 5. Zagon skripte

Odpre se **PowerShell** in premakne v mapo projekta:

```powershell
Set-Location 'C:\xampp\htdocs\demo'
```

Skripto nato zaženemo s parametrom `-n`, ki predstavlja uporabniško ime:

```powershell
.\create-client.ps1 -n janez
```

Parameter:

```text
-n janez
```

pomeni, da bo ustvarjen uporabnik z imenom `janez`.

## 6. Kaj se zgodi ob zagonu

Ko zaženemo:

```powershell
.\create-client.ps1 -n janez
```

skripta izvede naslednje korake.

### 1. Shrani trenutno mapo

```powershell
$startDir = Get-Location
```

S tem si skripta zapomni trenutno delovno mapo, da se lahko po koncu izvajanja vrne nanjo.

### 2. Nastavi okolje za OpenSSL

```powershell
Set-Location 'C:\xampp\apache\bin'
$env:OPENSSL_CONF = 'C:\xampp\apache\conf\openssl.cnf'
```

S tem skripta zagotovi, da uporablja OpenSSL iz XAMPP Apache okolja in ustrezno konfiguracijsko datoteko.

### 3. Določi mapo uporabnika

Če je uporabniško ime `janez`, se ustvari:

```text
C:\xampp\htdocs\demo\certs\janez
```

### 4. Ustvari zasebni ključ in CSR

OpenSSL ustvari:

```text
client.key
client.csr
```

`client.key` je zasebni ključ uporabnika, `client.csr` pa zahteva za izdajo certifikata.

### 5. Podpiše client certifikat

CSR podpiše Root CA:

```text
ca.crt
ca.key
```

Rezultat je:

```text
client.crt
```

Tako je client certifikat izdan s strani istega Root CA, ki mu strežnik zaupa.

### 6. Izračuna fingerprint

Skripta iz certifikata pridobi SHA-256 fingerprint:

```powershell
.\openssl.exe x509 -in "$dir\client.crt" -noout -fingerprint -sha256
```

Dvopičja se nato odstranijo, tako da se fingerprint shrani kot 64-mestna hexadecimalna vrednost.

Primer:

```text
A1B2C3D4E5F6...
```

### 7. Vpiše uporabnika v MySQL

Skripta sestavi SQL ukaz:

```sql
INSERT INTO users
(username, certificate_fingerprint)
VALUES
('janez', '...fingerprint...');
```

in ga izvede prek:

```text
C:\xampp\mysql\bin\mysql.exe
```

### 8. Izpiše rezultat

Na koncu se izpišejo podatki:

```text
Uporabnik: janez
Fingerprint: ...
Certifikat: C:\xampp\htdocs\demo\certs\janez\client.crt
Koncano.
```

## 7. Rezultat

Po uspešnem izvajanju skripte dobimo:

```text
certs/
└── janez/
    ├── client.key
    ├── client.csr
    └── client.crt
```

V podatkovni bazi `mtls_demo` pa se ustvari zapis:

```text
username | certificate_fingerprint
---------|-------------------------
janez    | A1B2C3D4...
```

Fingerprint v bazi predstavlja povezavo med uporabnikom in njegovim client certifikatom.

## 8. Preverjanje uporabnika v bazi

Po ustvarjanju uporabnika lahko preverimo vse registrirane uporabnike:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root mtls_demo -e "SELECT * FROM users;"
```

Primer rezultata:

```text
+----+----------+----------------------------------+
| id | username | certificate_fingerprint         |
+----+----------+----------------------------------+
|  1 | janez    | A1B2C3D4...                      |
+----+----------+----------------------------------+
```

## 9. Ustvarjanje več uporabnikov

Za vsakega uporabnika skripto zaženemo posebej.

Primer:

```powershell
.\create-client.ps1 -n janez
.\create-client.ps1 -n ana
.\create-client.ps1 -n miha
```

Nastane:

```text
certs/
├── janez/
│   ├── client.key
│   ├── client.csr
│   └── client.crt
│
├── ana/
│   ├── client.key
│   ├── client.csr
│   └── client.crt
│
└── miha/
    ├── client.key
    ├── client.csr
    └── client.crt
```

V tabeli `users` pa so za vsakega uporabnika shranjeni njegovo uporabniško ime in fingerprint certifikata.

## 10. Uporaba client certifikata

Ustvarjeni certifikat lahko uporabnik uporabi za povezavo z mTLS strežnikom.

Za mTLS sta potrebna:

```text
client.crt
client.key
```

Client certifikat vsebuje javni del certifikata, medtem ko je `client.key` zasebni ključ uporabnika.

**Zasebnega ključa `client.key` uporabnik ne sme deliti z drugimi osebami.**

Ko se uporabnik poveže s strežnikom, strežnik zahteva client certifikat. Strežnik preveri, ali je certifikat podpisan z zaupanja vrednim Root CA.

Aplikacija lahko nato iz prejetega certifikata izračuna SHA-256 fingerprint in ga primerja z vrednostjo v tabeli `users`.

Primer logike:

```text
Client
  │
  │ client.crt + client.key
  ▼
Apache / HTTPS
  │
  │ preverjanje Root CA
  ▼
Aplikacija
  │
  │ izračun SHA-256 fingerprinta
  ▼
Tabela users
  │
  │ primerjava fingerprinta
  ▼
Uporabnik potrjen
```

Če se fingerprint ujema z zapisom v tabeli `users`, je mogoče certifikat povezati z določenim uporabnikom.

## 11. Pomembna varnostna opomba

Datoteka:

```text
ca.key
```

je zasebni ključ Root CA in mora biti posebej zaščitena.

Prav tako je potrebno zaščititi:

```text
client.key
server.key
```

Zasebnih ključev ni priporočljivo pošiljati drugim osebam ali objavljati v repozitorijih.

Pri produkcijski uporabi je priporočljivo dodatno urediti:

- zaščito Root CA zasebnega ključa,
- ustrezne pravice dostopa do zasebnih ključev,
- preverjanje veljavnosti certifikatov,
- revokacijo preklicanih certifikatov,
- varno upravljanje uporabnikov,
- preverjanje veljavnosti certifikata in njegove namenske uporabe,
- varno izvajanje SQL poizvedb brez neposrednega vstavljanja uporabniškega vnosa v SQL niz.

## 12. Celoten primer uporabe

Postopek za novega uporabnika je zato:

```powershell
Set-Location 'C:\xampp\htdocs\demo'
```

Nato:

```powershell
.\create-client.ps1 -n janez
```

Skripta ustvari:

```text
certs/janez/client.key
certs/janez/client.csr
certs/janez/client.crt
```

in v bazo vstavi:

```text
janez + SHA-256 fingerprint
```

Nato lahko uporabniku posredujemo njegov client certifikat in ustrezno konfiguriramo odjemalca za uporabo mTLS.

Celoten proces lahko povzamemo kot:

```text
uporabniško ime
       │
       ▼
create-client.ps1
       │
       ├── ustvari client.key
       │
       ├── ustvari client.csr
       │
       ├── podpiše CSR z Root CA
       │
       ├── ustvari client.crt
       │
       ├── izračuna SHA-256 fingerprint
       │
       └── shrani uporabnika + fingerprint v MySQL
```