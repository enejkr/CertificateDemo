## Uporaba OpenSSL

Vsi ukazi so izvedeni v **PowerShellu**.

Delovna mapa:

```powershell
Set-Location 'C:\xampp\apache\bin'
```

OpenSSL konfiguracija:

```powershell
$env:OPENSSL_CONF = 'C:\xampp\apache\conf\openssl.cnf'
```

Mapa s certifikati:

```powershell
$c = 'C:\xampp\htdocs\demo\certs'
```

## 1. Ustvarjanje Root CA

```powershell
.\openssl.exe req -x509 -newkey rsa:2048 -sha256 -nodes -days 3650 -keyout "$c\ca.key" -out "$c\ca.crt" -subj "/CN=MTLS Demo Root CA"
```

Ustvari Root CA certifikat in njegov zasebni ključ:

```text
ca.key
ca.crt
```

## 2. Ustvarjanje server ključa in CSR

```powershell
.\openssl.exe req -newkey rsa:2048 -sha256 -nodes -keyout "$c\server\server.key" -out "$c\server\server.csr" -subj "/CN=localhost"
```

Ustvari zasebni ključ in CSR strežnika.

## 3. Ustvarjanje client ključa in CSR

```powershell
.\openssl.exe req -newkey rsa:2048 -sha256 -nodes -keyout "$c\client\client.key" -out "$c\client\client.csr" -subj "/CN=mtls-klient"
```

Ustvari zasebni ključ in CSR klienta.

## 4. Podpis server certifikata

```powershell
.\openssl.exe x509 -req -in "$c\server\server.csr" -CA "$c\ca.crt" -CAkey "$c\ca.key" -CAcreateserial -out "$c\server\server.crt" -days 825 -sha256
```

## 5. Podpis client certifikata

```powershell
.\openssl.exe x509 -req -in "$c\client\client.csr" -CA "$c\ca.crt" -CAkey "$c\ca.key" -CAcreateserial -out "$c\client\client.crt" -days 825 -sha256
```

Server in client certifikat sta tako podpisana z istim Root CA.

---

# Pridobljene datoteke

## Server

```text
server/
├── server.key
├── server.csr
└── server.crt
```

## Client

```text
client/
├── client.key
├── client.csr
└── client.crt
```

## CA

```text
ca.key
ca.srl
ca.crt
```

---

# Pomen datotek

| Datoteka | Opis |
|---|---|
| `.key` | zasebni ključ |
| `.csr` | zahteva za izdajo certifikata |
| `.crt` | podpisan certifikat |
| `ca.key` | zasebni ključ Root CA |
| `ca.crt` | javni certifikat Root CA |
| `.srl` | serijska datoteka CA |

---

# Ustvarjanje dodatnega mTLS uporabnika

Za dodajanje novega uporabnika se uporablja PowerShell skripta, ki kot parameter prejme uporabniško ime.

Primer:

```powershell
.\create-client.ps1 -n janez
```

Skripta za uporabnika:

1. ustvari posebno mapo v `certs`,
2. ustvari RSA 2048-bitni zasebni ključ,
3. ustvari CSR,
4. CSR podpiše z Root CA,
5. ustvari client certifikat,
6. izračuna SHA-256 fingerprint certifikata,
7. uporabnika in njegov fingerprint vnese v tabelo `users`,
8. izpiše podatke o ustvarjenem uporabniku.

Za uporabnika se ustvari naslednja struktura:

```text
certs/
└── janez/
    ├── client.key
    ├── client.csr
    └── client.crt
```

Fingerprint predstavlja SHA-256 identifikator client certifikata in se shrani v podatkovno bazo skupaj z uporabniškim imenom.

S tem je uporabnik povezan s svojim mTLS certifikatom. Pri preverjanju certifikata lahko aplikacija primerja fingerprint prejetega client certifikata z vrednostjo, shranjeno v tabeli `users`.

Skripta uporablja:

- OpenSSL iz XAMPP Apache okolja za ustvarjanje in podpis certifikata,
- Root CA za podpis client certifikata,
- MySQL odjemalca iz XAMPP za vpis uporabnika v bazo.