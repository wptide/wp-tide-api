# wp-tide-api

[![Build Status](https://travis-ci.org/wptide/wp-tide-api.svg?branch=develop)](https://travis-ci.org/wptide/wp-tide-api) [![Coverage Status](https://coveralls.io/repos/wptide/wp-tide-api/badge.svg?branch=develop)](https://coveralls.io/github/wptide/wp-tide-api) [![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)

WP Tide has a few dependencies that is managed using composer.

Please run `composer install` (or `php composer.phar install`) to install project dependencies.

Run `composer update` (or `php composer.phar update`) to update dependencies.

**DEPENDENCIES**

* **Firebase/php-jwt** - used for JWT encoding and decoding.


## Authentication

JWT is implemented for token-based authentication of REST API requests. Each request to the API will need to specify an
HTTP Authorization header with the JWT provided as the Bearer Token.

A JWT token can be obtained by sending a POST {site}/{rest-api-prefix}/tide/v1/auth with the relevant
authentication method.  The following methods are currently available:  

* User Authentication: POST-ing `username` and `password` as form data (not recommended and will be disabled)
* User API Key/API Secret: POST `api_key` and `api-secret` as form data (obtained by visiting user profile)

Both above authentication methods will return a JWT that contains the user's ID and other token information.

Example: Get token with User Authentication
```
curl -X POST -F 'username=admin' -F 'password=password' http://local.dev/wp-json/tide/v1/auth
```

Example: Get token with API Key and API Secret
```
curl -X POST -F 'api_key=1guJ0omfNkzoXsUu6NRp9rwCr' -F 'api_secret=X)WxT3Wp&NPHPWFFxRGDioP1UUVNuBfH' http://local.dev/api/tide/v2/auth
```

Once a token is obtained you can access other REST API resources by specifying the HTTP Authorization header with a bearer token.

Example: Standard WordPress endpoint request with JWT authentication active and using a Bearer token
```
curl -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE0ODc1NzAwOTIsImlzcyI6Imh0dHA6XC9cL3NpbmdsZTUuZGV2IiwiZXhwIjoxNDkwMTYyMDkyLCJkYXRhIjp7ImNsaWVudCI6eyJpZCI6MSwidHlwZSI6IndwX3VzZXIifX19.HGqNFaH742QPsMy0zkFmuiiRvlBczVoeorr6uVnwwZ4" http://local.dev/wp-json/wp/v2/posts
```

### Refresh tokens (optional, but recommended)

The Bearer tokens above have a short life (default: 30 days) and when a token expires a client has to re-authenticate. To make this process easier
we are using "Refresh Tokens", which only get issues when a user authenticates using the methods above.

A "Refresh Token" has a longer life (default: 1 year) which can also be used as an authentication method using a POST request to
the /auth endpoint. This will generate a new JWT by only specifying the refresh token as the Bearer token. All subsequent
requests will use the new JWT token and not the "Refresh Token".

Because of the long life of a "Refresh Token" it is recommended that the client stores this key locally in a safe place upon
authentication as it will not be available any other way.

It is the client's responsibility to keep the "Refresh Token" safe and to use it to generate new JWT tokens. A client may also
choose not to use a "Refresh Tokens" and simply re-authenticate using one of the other authentication methods. It is recommended
that software clients use "Refresh Tokens" to authenticate when possible to avoid sending credentials.

Format:
```
curl -X POST -H "Authorization: Bearer [REFRESH_TOKEN]" "https://[SITE]/api/tide/v1/auth"
```

Example: Re-authenticate with a "Refresh Token"
```
curl -X POST -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpYXQiOjE0ODgxNjYwNjQsImlzcyI6Imh0dHA6XC9cL3NpbmdsZTUuZGV2IiwiZXhwIjoxNTE5NzAyMDY0LCJkYXRhIjp7InRva2VuX3R5cGUiOiJyZWZyZXNoIiwiY2xpZW50Ijp7ImlkIjoxLCJ0eXBlIjoid3BfdXNlciJ9fX0.FP11UCDo-5AiYKacL545tPwgsEQUYwMXkqapqoPVYuw" "http://example.dev/wp-json/tide/v2/auth"
```
