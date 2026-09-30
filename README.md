# CertificateDemo

Demo PHP projekta za prikaz avtentikacije z digitalnimi certifikati, mTLS in JWT.

## Vsebina

- `client/` – odjemalski del aplikacije
- `server/` – strežniški del in API
- `certs/` – certifikati za mTLS
- `server/keys/` – ključi za JWT
- `logs/` – lokalni logi

---

# API arhitektura

API je razdeljen na **mTLS del za prijavo** in **navaden HTTPS API za delo z access tokenom**.

## 1. mTLS prijava

Za začetno prijavo se uporablja client certifikat:

```text
POST /mtls-api/login.php
        |
        | Client Certificate
        v
   preverjanje
        |
        v
   access_token
   refresh_token
```

Client certifikat se uporabi za preverjanje identitete klienta. Po uspešni prijavi strežnik vrne access in refresh token.

## 2. Refresh token

Refresh endpoint 

```text
POST /api/refresh.php
        |
        | Authorization:
        | Bearer <refresh_token>
        v
    preverjanje
        |
        v
     novi Refresh Token
     novi Access Token
```

Če je `refresh.php` znotraj `/api`, client ne potrebuje pošiljati certificata 

## 3. Navaden HTTPS API

Dejanske API zahteve uporabljajo access token:

```text
POST /api/api.php
        |
        | Authorization:
        | Bearer <access_token>
        v
       API
```

Ta endpoint ne zahteva client certifikata.

Celotna arhitektura:

```text
                         CLIENT
                           |
              +------------+------------+
              |                         |
              v                         v
      /mtls-api/login.php         /api/api.php
              |                         |
       Client Certificate          Access Token
              |                         |
              v                         v
        Access Token                API
        Refresh Token
```

Client PHP koda zato client certifikat uporablja pri mTLS prijavi. Pri zahtevah z access tokenom se certifikat ne pošilja.

---

# Demo mTLS

## Proces certifikatne avtentikacije

Root CA podpiše certifikata serverja in klienta:

```text
                    Root CA
                       |
             +---------+---------+
             |                   |
             v                   v
        Server CSR          Client CSR
             |                   |
             v                   v
        server.crt           client.crt
```

Pri mTLS server preveri clientov certifikat, client pa lahko preveri serverjev certifikat.

---

# Apache HTTPS in mTLS

Apache uporablja HTTPS `VirtualHost` na portu `8443`.

Konfiguracija:

```apache
<VirtualHost *:8443>
    ServerName localhost
    DocumentRoot "C:/xampp/htdocs/demo/server"

    SSLEngine on

    SSLCertificateFile      "C:/xampp/htdocs/demo/certs/server/server.crt"
    SSLCertificateKeyFile   "C:/xampp/htdocs/demo/certs/server/server.key"
    SSLCACertificateFile    "C:/xampp/htdocs/demo/certs/ca.crt"

    SSLVerifyClient none

    SSLOptions +StdEnvVars +ExportCertData

    <Location "/mtls-api">
        SSLVerifyClient require
        SSLVerifyDepth 2
    </Location>
</VirtualHost>
```

`SSLVerifyClient require` ni nastavljen za celoten `VirtualHost`.

Privzeto je:

```apache
SSLVerifyClient none
```

mTLS pa je zahtevan samo za:

```apache
<Location "/mtls-api">
    SSLVerifyClient require
    SSLVerifyDepth 2
</Location>
```

Tako isti HTTPS strežnik uporablja oba načina povezave:

```text
https://localhost:8443/mtls-api/...
        ↓
   HTTPS + mTLS


https://localhost:8443/api/...
        ↓
   HTTPS + Bearer token
```

---

# Certifikati

Projekt uporablja certifikate za vzpostavitev HTTPS in mTLS povezave.

## Server

```text
server/
├── server.key
└── server.crt
```

## Client

```text
client/
├── client.key
└── client.crt
```

## CA

```text
ca.key
ca.crt
```

Root CA se uporablja za preverjanje certifikatov serverja in klienta.

---

# Ustvarjanje dodatnega mTLS uporabnika

Za dodajanje novega uporabnika se uporablja PowerShell skripta, ki kot parameter prejme uporabniško ime.

Primer:

```powershell
.\create-client.ps1 -n janez
```

Skripta:

1. ustvari posebno mapo za uporabnika,
2. ustvari client certifikat,
3. izračuna SHA-256 fingerprint certifikata,
4. vnese uporabnika in fingerprint v tabelo `users`,
5. izpiše podatke o ustvarjenem uporabniku.

Za uporabnika se ustvari:

```text
certs/
└── janez/
    ├── client.key
    ├── client.csr
    └── client.crt
```

Fingerprint predstavlja SHA-256 identifikator client certifikata in se shrani v podatkovno bazo skupaj z uporabniškim imenom.

---

# JWT

Projekt uporablja JWT žetone za avtentikacijo zahtev do API-ja.

Uporabljata se:

```text
server/
└── keys/
    ├── private.key
    └── public.key
```

Zasebni ključ se uporablja za podpis JWT žetonov, javni ključ pa za preverjanje podpisa.

## Proces JWT avtentikacije

```text
private.key
     |
     v
podpis podatkov
     |
     v
JWT token
     |
     v
API
     |
     v
public.key
     |
     v
preverjanje podpisa
```

Zasebni ključ mora ostati zaupen.

---

# Zagon projekta

Projekt kopiraj v:

```text
C:\xampp\htdocs\demo
```

Nato preko XAMPP zaženi:

- Apache
- MySQL

Client je dostopen preko:

```text
http://localhost/demo/client/
```

HTTPS API uporablja:

```text
https://localhost:8443/
```

## Zahteve

- XAMPP
- Apache
- PHP
- MySQL
- firebase/JWT
- monolog 

oziroma samo XAMPP saj vse ostalo pride zraven XAMPP inštalacije

---

# Baza podatkov

SQL datoteko uvozi v **phpMyAdmin**.

Tabela `users` vsebuje uporabnika in fingerprint njegovega client certifikata.

---

# Formar in standard odgovorov 
## Uspešen request 
```
{
    "success": true,
    "message": "Token uspešno osvežen.",
    "data": {
        "access_token": "...",
        "refresh_token": "...",
        "token_type": "Bearer",
    }
}
```
## Napaka 
```
{
    "success": false,
    "error": {
        "code": "INVALID_ACCESS_TOKEN",
        "message": "Access token ni veljaven."
    }
}
```
## seznam error.code 
| HTTP | `error.code` | Kje | Pomen |
|---:|---|---|---|
| 400 | `BAD_REQUEST` | endpoint | Zahteva je sintaktično/nepričakovano napačna |
| 401 | `CERTIFICATE_VERIFICATION_FAILED` | `Certificate` | mTLS certifikat ni bil uspešno preverjen |
| 401 | `CERTIFICATE_MISSING` | `Certificate` | Client certifikat manjka |
| 401 | `CERTIFICATE_INVALID` | `Certificate` | Certifikata ni mogoče obdelati |
| 401 | `AUTHENTICATION_FAILED` | `Certificate` / refresh | Uporabnika ni mogoče avtenticirati |
| 401 | `INVALID_ACCESS_TOKEN` | `JwToken` / `extractToken` | Access token manjka ali ni veljaven |
| 401 | `INVALID_REFRESH_TOKEN` | `RefreshToken` | Refresh token ne obstaja ali je preklican |
| 401 | `REFRESH_TOKEN_EXPIRED` | `RefreshToken` | Refresh token je potekel |
| 403 | `FORBIDDEN` | endpoint | Uporabnik je prijavljen, ampak nima dovoljenja |
| 404 | `NOT_FOUND` | endpoint | Zahtevan vir ne obstaja |
| 409 | `CONFLICT` | endpoint/service | Konflikt podatkov |
| 422 | `VALIDATION_ERROR` | endpoint | Podatki niso validni |
| 429 | `RATE_LIMIT_EXCEEDED` | endpoint | Preveč zahtev |
| 500 | `INTERNAL_SERVER_ERROR` | vsi endpointi | Nepričakovana napaka strežnika |
| 503 | `SERVICE_UNAVAILABLE` | DB/zunanji servis | Storitev trenutno ni na voljo |

---

# Varnost

Zasebni ključi (`.key`) ne smejo biti javno objavljeni.

Posebej zaščiteni morajo biti:

```text
ca.key
server.key
client.key
server/keys/private.key
```

`ca.key` omogoča podpisovanje novih certifikatov, `private.key` pa podpisovanje JWT žetonov.

Razvojnih certifikatov in ključev ne uporabljaj v produkciji.
