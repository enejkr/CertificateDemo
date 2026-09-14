# CertificateDemo

Demo PHP projekta za prikaz avtentikacije z digitalnimi certifikati, mTLS in JWT.

## Vsebina

- `client/` – odjemalski del aplikacije
- `server/` – strežniški del in API
- `certs/` – certifikati za mTLS
- `server/keys/` – ključi za JWT
- `logs/` – lokalni logi

---

# Demo mTLS

## Uporaba OpenSSL kreiranja certifikatov

Vsi ukazi so izvedeni v **PowerShell**.

Spremenljivka, uporabljena v samih ukazih:

```powershell
$c = 'C:\xampp\htdocs\demo\certs'
```

Najprej nastavimo delovno mapo OpenSSL:

```powershell
Set-Location 'C:\xampp\apache\bin'
```

Nastavimo tudi OpenSSL konfiguracijsko datoteko:

```powershell
$env:OPENSSL_CONF = 'C:\xampp\apache\conf\openssl.cnf'
```

## 1. Ustvarjanje Root CA

```powershell
.\openssl.exe req -x509 -newkey rsa:2048 -sha256 -nodes -days 3650 -keyout "$c\ca.key" -out "$c\ca.crt" -subj "/CN=MTLS Demo Root CA"
```

Ta ukaz ustvari root CA certifikat in njegov ključ, ki bosta uporabljeni pri podpisovanju drugih certifikatov.

Ustvarjeni datoteki:

```text
ca.key
ca.crt
```

## 2. Ustvarjanje server ključa in CSR

```powershell
.\openssl.exe req -newkey rsa:2048 -sha256 -nodes -keyout "$c\server\server.key" -out "$c\server\server.csr" -subj "/CN=localhost"
```

Ukaz ustvari ključ in nepodpisan certifikat, ki ju še more podpisati CA.

Ustvarjeni datoteki:

```text
server.key
server.csr
```

## 3. Ustvarjanje client ključa in CSR

```powershell
.\openssl.exe req -newkey rsa:2048 -sha256 -nodes -keyout "$c\client\client.key" -out "$c\client\client.csr" -subj "/CN=mtls-klient"
```

Ukaz ustvari ključ in nepodpisan certifikat, ki ju še more podpisati CA.

Ustvarjeni datoteki:

```text
client.key
client.csr
```

## 4. Podpis server certifikata

```powershell
.\openssl.exe x509 -req -in "$c\server\server.csr" -CA "$c\ca.crt" -CAkey "$c\ca.key" -CAcreateserial -out "$c\server\server.crt" -days 825 -sha256
```

## 5. Podpis client certifikata

```powershell
.\openssl.exe x509 -req -in "$c\client\client.csr" -CA "$c\ca.crt" -CAkey "$c\ca.key" -CAcreateserial -out "$c\client\client.crt" -days 825 -sha256
```

Ukaz podpiše nepodpisan certifikat serverja in clienta z ključem in certifikatom root CA.

---

# Proces pridobivanja certifikata

Proces pridobivanja certifikatov poteka v več korakih.

Najprej se ustvari **Root CA**, ki predstavlja zaupanja vredno certifikacijsko avtoriteto.

Nato server in client ustvarita vsak svoj zasebni ključ in CSR. CSR predstavlja zahtevo za izdajo certifikata in še ni podpisan.

Root CA nato s svojim zasebnim ključem podpiše CSR serverja in clienta. Tako dobimo veljavna certifikata, ki ju je izdal isti Root CA.

Proces:

```text
                    Root CA
                 ca.key / ca.crt
                      |
             +--------+--------+
             |                 |
             v                 v
        Server CSR         Client CSR
        server.csr         client.csr
             |                 |
             v                 v
       server.crt          client.crt
```

Pri mTLS se server in client med vzpostavitvijo povezave medsebojno preverjata s pomočjo certifikatov.

Server preveri clientov certifikat, client pa lahko preveri serverjev certifikat. Oba morata zaupati istemu Root CA oziroma ustrezni certifikacijski verigi.

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
| `.key` | vsebuje zasebni ključ |
| `.csr` | zahteva za certifikat oziroma "nepodpisan certifikat" |
| `.crt` | izdan/podpisan certifikat |
| `ca.key` | zasebni ključ Root CA |
| `ca.crt` | javni certifikat Root CA |
| `.srl` | serijska datoteka, ki hrani naslednjo serijsko številko certifikata |

---

# Uporabljeni OpenSSL parametri

| Parameter | Pomen |
|---|---|
| `req` | delo z zahtevami za certifikat |
| `-x509` | ustvari X.509 certifikat |
| `-newkey rsa:2048` | ustvari nov RSA ključ dolžine 2048 bitov |
| `-sha256` | uporablja SHA-256 |
| `-nodes` | zasebni ključ ni zaščiten z geslom |
| `-days 3650` | veljavnost certifikata 3650 dni |
| `-days 825` | veljavnost certifikata 825 dni |
| `-keyout` | določi izhodno datoteko zasebnega ključa |
| `-out` | določi izhodno datoteko |
| `-subj` | določi podatke certifikata |
| `-CA` | določi CA certifikat |
| `-CAkey` | določi zasebni ključ CA |
| `-CAcreateserial` | ustvari serijsko datoteko CA |
| `-in` | določi vhodno datoteko |
| `-CN` | Common Name certifikata |

---

# JWT ključi

Projekt uporablja tudi RSA ključa za podpisovanje in preverjanje JWT žetonov.

JWT uporablja:

```text
private.key
public.key
```

Zasebni ključ se uporablja za **podpis JWT**, javni ključ pa za **preverjanje podpisa**.

## Ustvarjanje JWT ključev

V PowerShellu se najprej premaknemo v mapo OpenSSL:

```powershell
Set-Location 'C:\xampp\apache\bin'
```

Določimo mapo, kamor bomo shranili JWT ključe:

```powershell
$jwt = 'C:\xampp\htdocs\demo\server\keys'
```

## 1. Ustvarjanje zasebnega ključa

```powershell
.\openssl.exe genrsa -out "$jwt\private.key" 2048
```

Ta ukaz ustvari RSA zasebni ključ dolžine 2048 bitov.

Rezultat:

```text
server/keys/private.key
```

## 2. Ustvarjanje javnega ključa

```powershell
.\openssl.exe rsa -in "$jwt\private.key" -pubout -out "$jwt\public.key"
```

Javni ključ se izpelje iz zasebnega ključa.

Rezultat:

```text
server/keys/public.key
```

---

# Proces JWT avtentikacije

Pri izdaji JWT se podatki podpišejo z zasebnim ključem.

```text
private.key
     |
     v
podpis podatkov
     |
     v
JWT token
```

Ko strežnik prejme JWT, lahko njegov podpis preveri z javnim ključem.

```text
JWT
 |
 v
public.key
 |
 v
preverjanje podpisa
 |
 +----> veljaven
 |
 +----> neveljaven
```

Zasebni ključ mora zato ostati zaupen, medtem ko se javni ključ lahko uporablja za preverjanje podpisov.

---

# Struktura JWT ključev

```text
server/
└── keys/
    ├── private.key
    └── public.key
```

---

# Zagon projekta

Projekt kopiraj v:

```text
C:\xampp\htdocs\demo
```

Nato zaženi **Apache** in **MySQL** preko XAMPP.

Projekt je nato dostopen preko:

```text
http://localhost/demo/client/
```

## Zahteve

- XAMPP
- Apache
- PHP
- MySQL
- OpenSSL

---

# Varnost

Datoteke z zasebnimi ključi (`.key`) vsebujejo občutljive podatke in jih v produkcijskem okolju ne smemo javno objavljati.

Prav tako je priporočljivo, da se razvojni certifikati in zasebni ključi ne uporabljajo v produkciji.

JWT `private.key` mora ostati zaupen, saj se uporablja za podpisovanje JWT žetonov.

Root CA `ca.key` mora prav tako ostati zaupen, saj omogoča podpisovanje novih certifikatov.

Javni certifikati (`.crt`) in javni JWT ključ (`public.key`) se lahko uporabljajo za preverjanje podpisov in certifikatov.
