# Login.php

## Description

API endpoint za prijavo klienta z uporabo **OpenSSL certifikata**.

Ob uspešni avtentikaciji API ustvari **Access Token** in **Refresh Token**, ki se uporabljata za nadaljnje API zahteve.

## URL and Method

**[POST]** `https://localhost:8443/auth/login`

## Authentication

Za uspešno prijavo mora klient uporabiti veljaven **OpenSSL certifikat**.

Certifikat se uporabi za preverjanje identitete klienta.

## Successful response

HTTP Status **200**

```json
{
  "success": true,
  "message": "Successful login",
  "data": {
    "access_token": "eyJhbGciOi...",
    "refresh_token": "d8f7c1...",
    "token_type": "Bearer"
  }
}
```

### Response parameters

| Parameter       | Type    | Description                                       |
| --------------- | ------- | ------------------------------------------------- |
| `success`       | Boolean | Označuje, ali je bila prijava uspešna.            |
| `message`       | String  | Sporočilo o uspešni prijavi.                      |
| `access_token`  | String  | JWT Access Token za avtentikacijo API zahtev.     |
| `refresh_token` | String  | Refresh Token za pridobitev novega Access Tokena. |
| `token_type`    | String  | Tip tokena. Vedno `Bearer`.                       |

## Error handling

### 429 Too many requests

Client je presegel dovoljeno število zahtev v določenem časovnem obdobju.

Response:

```json
{
  "success": false,
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "RATE_LIMIT_EXCEEDED"
  }
}
```

Header:

```http
Retry-After: 120
```

Vrednost `Retry-After` določa število sekund, po katerih lahko klient ponovno izvede zahtevo.

### 401 Unauthorised

Certifikat ni veljaven oziroma klienta ni mogoče uspešno avtenticirati.

Response:

```json
{
  "success": false,
  "error": {
    "code": "AUTHENTICATION_FAILED",
    "message": "Authentication failed."
  }
}
```

### 500 Internal server error

Če pride do nepričakovane napake na strežniku, se sproži Internal Server Error.

Response:

```json
{
  "success": false,
  "error": {
    "code": "INTERNAL_SERVER_ERROR",
    "message": "INTERNAL_SERVER_ERROR"
  }
}
```
