# authn-sh/sdk-php

PHP backend SDK for [authn.sh](https://authn.sh) — call the Backend API (BAPI), verify session JWTs, and verify webhook signatures from server-side PHP. Ships with a thin Laravel package layer.

> Status: **0.1.x pre-release.** APIs may change before `1.0`.

## Requirements

- PHP **8.2+**
- A PSR-18 HTTP client (Guzzle, Symfony HttpClient, etc.) and PSR-17 factories. The SDK auto-discovers them via [`php-http/discovery`](https://docs.php-http.org/en/latest/discovery.html), so installing `guzzlehttp/guzzle` (or any PSR-18 client) is enough.

## Install

```bash
composer require authn-sh/sdk-php
```

If you don't already have a PSR-18 client in your project:

```bash
composer require guzzlehttp/guzzle
```

## Usage

```php
use Authn\Sdk\Client;

$client = new Client(secretKey: $_ENV['AUTHN_SECRET_KEY']);

$user = $client->users()->get('user_2x4yT…');
```

Resource managers (`users()`, `sessions()`, `invitations()`, `allowlistIdentifiers()`, `blocklistIdentifiers()`, `redirectUrls()`, `instance()`, `webhookEndpoints()`) are wired up in this release; their full method bodies land in the next milestone.

### Custom HTTP client / logger

```php
use Authn\Sdk\Client;
use GuzzleHttp\Client as Guzzle;

$client = new Client(
    secretKey: $_ENV['AUTHN_SECRET_KEY'],
    apiUrl: 'https://api.authn.sh',
    http: new Guzzle(['timeout' => 5]),
    logger: $psr3Logger,
);
```

### Errors

The transport throws:

- `Authn\Sdk\Http\ApiException` for non-2xx responses, with `getStatusCode()`, `getErrors()` (the parsed `errors[]` envelope), `getTraceId()`, and `getRawBody()`.
- `Authn\Sdk\Http\NetworkException` for connection-level failures.

## Development

```bash
composer install
composer test       # Pest
composer phpstan    # PHPStan @ level 10
composer pint       # code style check
composer pint:fix   # apply fixes
```

CI runs the full suite on PHP 8.2 and 8.3.

## License

[AGPL-3.0-only](LICENSE).
