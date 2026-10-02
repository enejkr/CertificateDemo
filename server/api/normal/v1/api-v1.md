# Api.php

## Description

API endpoint za preverjanje veljavnosti Access Tokena in preverjanje omejitve števila zahtev (rate limit).

Endpoint iz JWT Access Tokena pridobi podatke o klientu (`client_id` in `client_name`), nato preveri, ali je klient presegel dovoljeno število zahtev v določenem časovnem obdobju.

Če je zahteva uspešna, API vrne potrjen Access Token.

## URL and Method

**[GET]** `https://localhost:8443/api/v1/api`

## Authentication

Endpoint zahteva veljaven **Bearer Access Token**.

Header:

```http
Authorization: Bearer <access_token>
```

Access Token mora biti veljaven in vsebovati podatke:

```json
{
  "client_id": "...",
  "client_name": "..."
}
```

## Successful response

HTTP Status **200**

```json
{
  "success": true,
  "message": "Successful connection",
  "data": {
    "access_token": "eyJhbGciOi..."
  }
}
```

## Error handling

### 401 Unauthorised

Če Access Token ni veljaven oziroma ga ni mogoče uspešno preveriti.

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

# Refresh Token

## Description

API endpoint za osvežitev Access Tokena z uporabo veljavnega Refresh Tokena.

Ob uspešni zahtevi API preveri Refresh Token, preveri rate limit klienta, preveri obstoj klienta v podatkovni bazi ter ustvari nov Access Token in nov Refresh Token.

## URL and Method

**[POST]** `https://localhost:8443/api/v1/refresh`

## Authentication

Endpoint zahteva veljaven **Refresh Token**.

Header:

```http
Authorization: Bearer <refresh_token>
```

Refresh Token se preveri pred izvedbo nadaljnjih operacij.

## Successful response

HTTP Status **200**

```json
{
  "success": true,
  "message": "Token successfully refreshed.",
  "data": {
    "access_token": "eyJhbGciOi...",
    "refresh_token": "d8f7c1...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

`expires_in` predstavlja čas veljavnosti novega Access Tokena v sekundah.

## Error handling

### 401 Unauthorised

Če Refresh Token ni veljaven, ni mogoče uspešno preveriti njegove veljavnosti ali klient ne obstaja v podatkovni bazi.

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
