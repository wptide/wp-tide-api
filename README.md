# WP Tide API

[![Build Status](https://travis-ci.org/wptide/wp-tide-api.svg?branch=develop)](https://travis-ci.org/wptide/wp-tide-api) [![Coverage Status](https://coveralls.io/repos/wptide/wp-tide-api/badge.svg?branch=develop)](https://coveralls.io/github/wptide/wp-tide-api) [![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)


If you're looking for the plugin which updates the WordPress admin UI with the audit results, **this is not the plugin you’re looking for**, that plugin does not yet exist.

## Dependencies

* [Composer](https://getcomposer.org/)  

## Composer Install  

Not required to run if using [tide-local](https://github.com/wptide/tide-local).

```
composer install
```

## Authentication

JWT is implemented for token-based authentication of REST API requests. Each request to the API will need to specify an HTTP Authorization header with the JWT provided as the Bearer Token.

Example: Get token with API Key and API Secret

```
curl -X POST -F 'api_key=<API_KEY>' -F 'api_secret=<API_SECRET>' http://tide.local/api/tide/v1/auth
```

Once a token is obtained you can access other REST API resources by specifying the HTTP Authorization header with a bearer token.

Example: Standard WordPress endpoint request with JWT authentication active and using a Bearer token

```
curl -H "Authorization: Bearer <TOKEN>" http://tide.local/api/tide/v1/audit
```

## Submitting an audit request

Example request: 

```
curl -X POST -H "Authorization: Bearer <TOKEN>" -H "Cache-Control: no-cache"  -H "Content-Type: multipart/form-data;" -F "title=<TITLE>" -F "content=<CONTENT>" -F "visibility=public" -F "source_url=<SOURCE_URL>" -F "source_type=zip" -F "request_client=wporg" -F "force=false" -F "slug=<SLUG>"  "http://tide.local/api/tide/v1/audit/"
```
Request fields:

| field  | description |
|:--- |:--- |
| `title` | Title of the plugin or theme |
| `content` | Description of the plugin or theme |
| `slug` | The slug of the plugin or theme |
| `project_type` | The type of project to audit: `theme`, `plugin` |
| `source_url` | The source to the zip file or git repository | 
| `source_type` | Either `zip` or `git` |
| `request_client` | The login name of a user in the api |
| `force` | Force a re-audit for existing audits |
| `visibility` | Whether it is `public` or `private` |
| `standards` | Available standards (comma separated for multiple): `phpcs_wordpress`, `phpcs_wordpress-core`, `phpcs_wordpress-docs`, `phpcs_wordpress-extras`, `phpcs_wordpress-vip`, `phpcs_phpcompatibility` |

## Private Audits

Audits with visibility set to `private` will require a Bearer Token for the GET request.

```
curl -H "Authorization: Bearer <TOKEN>" http://tide.local/api/tide/v1/audit/<ID|CHECKSUM>
```

or

```
curl -H "Authorization: Bearer <TOKEN>" http://tide.local/api/tide/v1/audit/<USER_LOGIN>/<SLUG>
```

## Props  

[@rheinardkorf](https://github.com/rheinardkorf), [@valendesigns](https://github.com/valendesigns), [@danlouw](https://github.com/danlouw), [@miina](https://github.com/miina), [@sayedtaqui](https://github.com/sayedtaqui), [@DavidCramer](https://github.com/DavidCramer), [@PatelUtkarsh](https://github.com/PatelUtkarsh), [@davidlonjon](https://github.com/davidlonjon), [@kopepasah](https://github.com/kopepasah)   


