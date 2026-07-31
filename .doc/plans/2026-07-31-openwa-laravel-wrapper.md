# OpenWA Laravel Wrapper Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn this spatie skeleton repo into `zarulizham/laravel-openwa`, a Laravel package wrapping the OpenWA REST API (sessions list, send-text/image/document) with a `whatsapp` Notification channel.

**Architecture:** One `OpenWaClient` class built on Laravel's `Http` facade, two readonly DTOs (`SessionData`, `MessageResponse`), one exception type, a facade, and a Notification channel that maps an `OpenWaMessage` value object onto the client's send methods. No queue/manager/connector layers — see spec for rejected alternatives.

**Tech Stack:** PHP 8.4+, Laravel `illuminate/http` (`Http` facade), `spatie/laravel-package-tools`, Pest 4 + `pestphp/pest-plugin-laravel` (`Http::fake`), Orchestra Testbench.

**Spec:** `.doc/specs/2026-07-31-openwa-laravel-wrapper-design.md`

## Global Constraints

- PHP `^8.4`, Laravel `illuminate/contracts` `^11.0||^12.0||^13.0` (from existing `composer.json` — do not lower).
- API auth: header `X-API-Key: <key>` on every request (OpenWA OpenAPI global security scheme).
- Base URL default: `https://openwa.prooffice.com.my/api`; overridable via `OPENWA_BASE_URL` env var.
- All HTTP calls go through Laravel's `Http` facade (user requirement) — no Guzzle client instantiated directly, no third-party HTTP SDK (e.g. Saloon).
- Package namespace: `ZarulIzham\OpenWa`. Composer package: `zarulizham/laravel-openwa`.
- Notification channel key is `whatsapp` (not `openwa`) — notifiable models implement `routeNotificationForWhatsapp()`.
- No retry/backoff logic, no queue-dispatch built into the client — sends are synchronous; queuing is via the Notification class's own `ShouldQueue`.

---

### Task 1: Rename skeleton package to `zarulizham/laravel-openwa`

**Files:**
- Modify: `composer.json`
- Modify: `src/SkeletonServiceProvider.php` → rename to `src/OpenWaServiceProvider.php`
- Modify: `src/Skeleton.php` → rename to `src/OpenWa.php` (temporary placeholder class, replaced by `OpenWaClient` in Task 4 — kept now only so the facade/autoload rename has something to point at)
- Modify: `src/Facades/Skeleton.php` → rename to `src/Facades/OpenWa.php`
- Modify: `tests/TestCase.php`
- Modify: `tests/Pest.php`
- Delete: `src/Commands/SkeletonCommand.php` (package has no artisan command)
- Delete: `config/skeleton.php` → recreated properly in Task 2
- Delete: `database/migrations/create_skeleton_table.php.stub` (package has no migration)
- Delete: `database/factories/ModelFactory.php` (package has no Eloquent model)
- Delete: `resources/views/.gitkeep` and the `resources/views` directory (package has no views)

**Interfaces:**
- Produces: namespace `ZarulIzham\OpenWa` rooted at `src/`, test namespace `ZarulIzham\OpenWa\Tests` rooted at `tests/`, service provider class `ZarulIzham\OpenWa\OpenWaServiceProvider`, package name `openwa`.

- [ ] **Step 1: Rewrite `composer.json` identity, autoload, and provider registration**

Replace the full file with:

```json
{
    "name": "zarulizham/laravel-openwa",
    "description": "Laravel wrapper for the OpenWA WhatsApp gateway API",
    "keywords": [
        "zarulizham",
        "laravel",
        "openwa",
        "whatsapp"
    ],
    "homepage": "https://github.com/zarulizham/laravel-openwa",
    "license": "MIT",
    "authors": [
        {
            "name": "Zarul Izham",
            "email": "mardyoe@gmail.com",
            "role": "Developer"
        }
    ],
    "require": {
        "php": "^8.4",
        "spatie/laravel-package-tools": "^1.16",
        "illuminate/contracts": "^11.0||^12.0||^13.0"
    },
    "require-dev": {
        "laravel/pint": "^1.14",
        "nunomaduro/collision": "^8.8",
        "larastan/larastan": "^3.0",
        "orchestra/testbench": "^11.0.0||^10.0.0||^9.0.0",
        "pestphp/pest": "^4.0",
        "pestphp/pest-plugin-arch": "^4.0",
        "pestphp/pest-plugin-laravel": "^4.0",
        "phpstan/extension-installer": "^1.4",
        "phpstan/phpstan-deprecation-rules": "^2.0",
        "phpstan/phpstan-phpunit": "^2.0",
        "spatie/laravel-ray": "^1.35"
    },
    "autoload": {
        "psr-4": {
            "ZarulIzham\\OpenWa\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "ZarulIzham\\OpenWa\\Tests\\": "tests/",
            "Workbench\\App\\": "workbench/app/"
        }
    },
    "scripts": {
        "post-autoload-dump": "@composer run prepare",
        "prepare": "@php vendor/bin/testbench package:discover --ansi",
        "analyse": "vendor/bin/phpstan analyse",
        "test": "vendor/bin/pest",
        "test-coverage": "vendor/bin/pest --coverage",
        "format": "vendor/bin/pint"
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "phpstan/extension-installer": true
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "ZarulIzham\\OpenWa\\OpenWaServiceProvider"
            ],
            "aliases": {
                "OpenWa": "ZarulIzham\\OpenWa\\Facades\\OpenWa"
            }
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

Note: `database/factories/` is dropped from `autoload-dev`/`autoload` psr-4 since Task 1 deletes `database/factories/ModelFactory.php` and the package defines no Eloquent models.

- [ ] **Step 2: Delete the unused skeleton scaffolding**

```bash
git rm src/Commands/SkeletonCommand.php
git rm database/migrations/create_skeleton_table.php.stub
git rm database/factories/ModelFactory.php
git rm resources/views/.gitkeep
rmdir resources/views resources database/migrations database/factories 2>/dev/null || true
```

- [ ] **Step 3: Rename and rewrite the service provider**

```bash
git mv src/SkeletonServiceProvider.php src/OpenWaServiceProvider.php
```

Replace its contents with:

```php
<?php

namespace ZarulIzham\OpenWa;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class OpenWaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('openwa')
            ->hasConfigFile();
    }
}
```

- [ ] **Step 4: Rename and rewrite the placeholder class + facade**

```bash
git mv src/Skeleton.php src/OpenWa.php
git mv src/Facades/Skeleton.php src/Facades/OpenWa.php
```

`src/OpenWa.php`:

```php
<?php

namespace ZarulIzham\OpenWa;

class OpenWa {}
```

`src/Facades/OpenWa.php`:

```php
<?php

namespace ZarulIzham\OpenWa\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \ZarulIzham\OpenWa\OpenWa
 */
class OpenWa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \ZarulIzham\OpenWa\OpenWa::class;
    }
}
```

(This is a scaffold placeholder — Task 5 replaces the facade accessor and adds real client methods.)

- [ ] **Step 5: Rewrite `tests/TestCase.php`**

```php
<?php

namespace ZarulIzham\OpenWa\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use ZarulIzham\OpenWa\OpenWaServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            OpenWaServiceProvider::class,
        ];
    }
}
```

(Dropped `Factory::guessFactoryNamesUsing` and the commented-out migration loader from the skeleton — the package has no Eloquent models or migrations, so both were dead weight.)

- [ ] **Step 6: Rewrite `tests/Pest.php`**

```php
<?php

use ZarulIzham\OpenWa\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);
```

- [ ] **Step 7: Delete the placeholder example test**

```bash
git rm tests/ExampleTest.php
```

- [ ] **Step 8: Update `phpunit.xml.dist` suite name**

In `phpunit.xml.dist`, change:

```xml
<testsuite name="VendorName Test Suite">
```

to:

```xml
<testsuite name="OpenWa Test Suite">
```

- [ ] **Step 9: Install dependencies**

Run: `composer install`
Expected: exits 0, `vendor/` created, no errors about missing package name.

- [ ] **Step 10: Run the full test suite to confirm the rename didn't break anything**

Run: `vendor/bin/pest`
Expected: `Tests:  1 passed` (the `ArchTest.php` debugging-functions check) — no other tests exist yet.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "chore: rename skeleton package to zarulizham/laravel-openwa"
```

---

### Task 2: Package config (`config/openwa.php`)

**Files:**
- Create: `config/openwa.php`
- Create: `tests/ConfigTest.php`

**Interfaces:**
- Consumes: `OpenWaServiceProvider::configurePackage()` (Task 1) already calls `->hasConfigFile()`, which auto-publishes/merges `config/openwa.php` under the `openwa` key because the package name is `openwa`.
- Produces: `config('openwa.base_url')`, `config('openwa.api_key')`, `config('openwa.timeout')` — consumed by `OpenWaServiceProvider::packageRegistered()` in Task 4.

- [ ] **Step 1: Write the failing test**

`tests/ConfigTest.php`:

```php
<?php

it('has default config values', function () {
    expect(config('openwa.base_url'))->toBe('https://openwa.prooffice.com.my/api');
    expect(config('openwa.api_key'))->toBeNull();
    expect(config('openwa.timeout'))->toBe(30);
});

it('reads config values overridden at runtime', function () {
    config(['openwa.base_url' => 'https://openwa.example.test/api']);
    config(['openwa.api_key' => 'secret-key']);

    expect(config('openwa.base_url'))->toBe('https://openwa.example.test/api');
    expect(config('openwa.api_key'))->toBe('secret-key');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/ConfigTest.php`
Expected: FAIL — `config('openwa.base_url')` returns `null` because `config/openwa.php` does not exist yet.

- [ ] **Step 3: Create `config/openwa.php`**

```php
<?php

return [
    'base_url' => env('OPENWA_BASE_URL', 'https://openwa.prooffice.com.my/api'),

    'api_key' => env('OPENWA_API_KEY'),

    'timeout' => env('OPENWA_TIMEOUT', 30),
];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/ConfigTest.php`
Expected: `Tests:  2 passed`

- [ ] **Step 5: Commit**

```bash
git add config/openwa.php tests/ConfigTest.php
git commit -m "feat: add openwa config file"
```

---

### Task 3: Data DTOs and exception type

**Files:**
- Create: `src/Data/SessionData.php`
- Create: `src/Data/MessageResponse.php`
- Create: `src/Exceptions/OpenWaException.php`
- Test: `tests/Data/SessionDataTest.php`
- Test: `tests/Data/MessageResponseTest.php`
- Test: `tests/Exceptions/OpenWaExceptionTest.php`

**Interfaces:**
- Produces: `SessionData::fromArray(array $data): SessionData` with public readonly properties `id, name, status, phone, pushName, connectedAt, lastActive, createdAt, updatedAt, lastError, engineLoaded`.
- Produces: `MessageResponse::fromArray(array $data): MessageResponse` with public readonly properties `messageId` (string), `timestamp` (int).
- Produces: `OpenWaException::fromResponse(\Illuminate\Http\Client\Response $response): OpenWaException` and `OpenWaException::noReadySession(): OpenWaException`, both extending `\Exception`, exposing public readonly `?int $status` and `?array $body`.
- Consumed by: `OpenWaClient` (Task 4/5) and `WhatsAppChannel` (Task 6).

- [ ] **Step 1: Write the failing tests**

`tests/Data/SessionDataTest.php`:

```php
<?php

use ZarulIzham\OpenWa\Data\SessionData;

it('builds from an api array', function () {
    $session = SessionData::fromArray([
        'id' => 'sess_1',
        'name' => 'bot-1',
        'status' => 'ready',
        'phone' => '60123456789',
        'pushName' => 'Bot One',
        'connectedAt' => '2026-07-30T10:00:00Z',
        'lastActive' => '2026-07-31T09:00:00Z',
        'createdAt' => '2026-07-01T09:00:00Z',
        'updatedAt' => '2026-07-31T09:00:00Z',
        'lastError' => null,
        'engineLoaded' => true,
    ]);

    expect($session->id)->toBe('sess_1');
    expect($session->status)->toBe('ready');
    expect($session->phone)->toBe('60123456789');
    expect($session->engineLoaded)->toBeTrue();
});

it('defaults nullable fields when absent', function () {
    $session = SessionData::fromArray([
        'id' => 'sess_2',
        'name' => 'bot-2',
        'status' => 'created',
        'createdAt' => '2026-07-01T09:00:00Z',
        'updatedAt' => '2026-07-01T09:00:00Z',
        'engineLoaded' => false,
    ]);

    expect($session->phone)->toBeNull();
    expect($session->pushName)->toBeNull();
    expect($session->connectedAt)->toBeNull();
    expect($session->lastError)->toBeNull();
});
```

`tests/Data/MessageResponseTest.php`:

```php
<?php

use ZarulIzham\OpenWa\Data\MessageResponse;

it('builds from an api array', function () {
    $message = MessageResponse::fromArray([
        'messageId' => 'true_628123456789@c.us_3EB0123456789',
        'timestamp' => 1706868000,
    ]);

    expect($message->messageId)->toBe('true_628123456789@c.us_3EB0123456789');
    expect($message->timestamp)->toBe(1706868000);
});
```

`tests/Exceptions/OpenWaExceptionTest.php`:

```php
<?php

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use ZarulIzham\OpenWa\Exceptions\OpenWaException;

it('builds from a failed response with a message field', function () {
    $response = new Response(new Psr7Response(
        422,
        ['Content-Type' => 'application/json'],
        json_encode(['message' => 'chatId is required']),
    ));

    $exception = OpenWaException::fromResponse($response);

    expect($exception->getMessage())->toBe('chatId is required');
    expect($exception->status)->toBe(422);
    expect($exception->body)->toBe(['message' => 'chatId is required']);
});

it('builds a no-ready-session exception', function () {
    $exception = OpenWaException::noReadySession();

    expect($exception->getMessage())->toBe('No OpenWA session with status "ready" is available.');
    expect($exception->status)->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Data tests/Exceptions`
Expected: FAIL — classes `ZarulIzham\OpenWa\Data\SessionData`, `MessageResponse`, `ZarulIzham\OpenWa\Exceptions\OpenWaException` do not exist.

- [ ] **Step 3: Create `src/Data/SessionData.php`**

```php
<?php

namespace ZarulIzham\OpenWa\Data;

readonly class SessionData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
        public ?string $phone,
        public ?string $pushName,
        public ?string $connectedAt,
        public ?string $lastActive,
        public string $createdAt,
        public string $updatedAt,
        public ?string $lastError,
        public bool $engineLoaded,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            status: $data['status'],
            phone: $data['phone'] ?? null,
            pushName: $data['pushName'] ?? null,
            connectedAt: $data['connectedAt'] ?? null,
            lastActive: $data['lastActive'] ?? null,
            createdAt: $data['createdAt'],
            updatedAt: $data['updatedAt'],
            lastError: $data['lastError'] ?? null,
            engineLoaded: $data['engineLoaded'],
        );
    }
}
```

- [ ] **Step 4: Create `src/Data/MessageResponse.php`**

```php
<?php

namespace ZarulIzham\OpenWa\Data;

readonly class MessageResponse
{
    public function __construct(
        public string $messageId,
        public int $timestamp,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            messageId: $data['messageId'],
            timestamp: (int) $data['timestamp'],
        );
    }
}
```

- [ ] **Step 5: Create `src/Exceptions/OpenWaException.php`**

```php
<?php

namespace ZarulIzham\OpenWa\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

class OpenWaException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?array $body = null,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();

        $message = is_array($body)
            ? ($body['message'] ?? $body['error'] ?? "OpenWA API request failed with status {$response->status()}")
            : "OpenWA API request failed with status {$response->status()}";

        if (is_array($message)) {
            $message = implode(', ', $message);
        }

        return new self($message, $response->status(), is_array($body) ? $body : null);
    }

    public static function noReadySession(): self
    {
        return new self('No OpenWA session with status "ready" is available.');
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Data tests/Exceptions`
Expected: `Tests:  6 passed`

- [ ] **Step 7: Commit**

```bash
git add src/Data src/Exceptions tests/Data tests/Exceptions
git commit -m "feat: add SessionData, MessageResponse DTOs and OpenWaException"
```

---

### Task 4: `OpenWaClient` — list sessions + resolve latest ready session

**Files:**
- Create: `src/OpenWaClient.php`
- Modify: `src/OpenWaServiceProvider.php:9-16` (add `packageRegistered()` to bind the singleton)
- Test: `tests/OpenWaClientTest.php`

**Interfaces:**
- Consumes: `SessionData::fromArray()`, `OpenWaException::fromResponse()`/`::noReadySession()` (Task 3); `config('openwa.*')` (Task 2).
- Produces: `OpenWaClient::__construct(string $baseUrl, string $apiKey, int $timeout = 30)`, `getSessions(?int $limit = null, ?int $offset = null): \Illuminate\Support\Collection` (of `SessionData`), `getLatestReadySessionId(): ?string`. Bound in the container as a singleton under `ZarulIzham\OpenWa\OpenWaClient::class`. Consumed by Task 5 (send methods on the same class) and Task 6 (`WhatsAppChannel`).

- [ ] **Step 1: Write the failing test**

`tests/OpenWaClientTest.php`:

```php
<?php

use Illuminate\Support\Facades\Http;
use ZarulIzham\OpenWa\Data\SessionData;
use ZarulIzham\OpenWa\OpenWaClient;

beforeEach(function () {
    config(['openwa.base_url' => 'https://openwa.test/api']);
    config(['openwa.api_key' => 'test-key']);
});

function fakeSession(array $overrides = []): array
{
    return array_merge([
        'id' => 'sess_1',
        'name' => 'bot-1',
        'status' => 'ready',
        'phone' => '60123456789',
        'pushName' => 'Bot One',
        'connectedAt' => '2026-07-30T10:00:00Z',
        'lastActive' => '2026-07-31T09:00:00Z',
        'createdAt' => '2026-07-01T09:00:00Z',
        'updatedAt' => '2026-07-31T09:00:00Z',
        'lastError' => null,
        'engineLoaded' => true,
    ], $overrides);
}

it('lists sessions with the api key header', function () {
    Http::fake([
        'openwa.test/api/sessions*' => Http::response([fakeSession()], 200),
    ]);

    $client = app(OpenWaClient::class);
    $sessions = $client->getSessions();

    expect($sessions)->toHaveCount(1);
    expect($sessions->first())->toBeInstanceOf(SessionData::class);
    expect($sessions->first()->id)->toBe('sess_1');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://openwa.test/api/sessions'
            && $request->hasHeader('X-API-Key', 'test-key');
    });
});

it('resolves the most recently connected ready session', function () {
    Http::fake([
        'openwa.test/api/sessions*' => Http::response([
            fakeSession(['id' => 'sess_old', 'status' => 'ready', 'connectedAt' => '2026-07-01T00:00:00Z']),
            fakeSession(['id' => 'sess_new', 'status' => 'ready', 'connectedAt' => '2026-07-30T00:00:00Z']),
            fakeSession(['id' => 'sess_failed', 'status' => 'failed', 'connectedAt' => '2026-07-31T00:00:00Z']),
        ], 200),
    ]);

    $client = app(OpenWaClient::class);

    expect($client->getLatestReadySessionId())->toBe('sess_new');
});

it('returns null when no session is ready', function () {
    Http::fake([
        'openwa.test/api/sessions*' => Http::response([
            fakeSession(['id' => 'sess_1', 'status' => 'disconnected']),
        ], 200),
    ]);

    $client = app(OpenWaClient::class);

    expect($client->getLatestReadySessionId())->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/OpenWaClientTest.php`
Expected: FAIL — class `ZarulIzham\OpenWa\OpenWaClient` does not exist, and no binding is registered for it.

- [ ] **Step 3: Create `src/OpenWaClient.php`**

```php
<?php

namespace ZarulIzham\OpenWa;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use ZarulIzham\OpenWa\Data\SessionData;
use ZarulIzham\OpenWa\Exceptions\OpenWaException;

class OpenWaClient
{
    public function __construct(
        protected string $baseUrl,
        protected string $apiKey,
        protected int $timeout = 30,
    ) {}

    public function getSessions(?int $limit = null, ?int $offset = null): Collection
    {
        $query = array_filter([
            'limit' => $limit,
            'offset' => $offset,
        ], fn ($value) => $value !== null);

        $response = $this->request()->get('/sessions', $query);

        return collect($this->handle($response))
            ->map(fn (array $session) => SessionData::fromArray($session));
    }

    public function getLatestReadySessionId(): ?string
    {
        return $this->getSessions()
            ->filter(fn (SessionData $session) => $session->status === 'ready')
            ->sortByDesc(fn (SessionData $session) => $session->connectedAt ?? $session->createdAt)
            ->first()
            ?->id;
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->timeout($this->timeout)
            ->acceptJson();
    }

    protected function handle(Response $response): array
    {
        if ($response->failed()) {
            throw OpenWaException::fromResponse($response);
        }

        return $response->json();
    }
}
```

- [ ] **Step 4: Bind the singleton in the service provider**

In `src/OpenWaServiceProvider.php`, add a `packageRegistered()` method after `configurePackage()`:

```php
<?php

namespace ZarulIzham\OpenWa;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class OpenWaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('openwa')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(OpenWaClient::class, function ($app) {
            return new OpenWaClient(
                baseUrl: (string) $app['config']->get('openwa.base_url'),
                apiKey: (string) $app['config']->get('openwa.api_key'),
                timeout: (int) $app['config']->get('openwa.timeout', 30),
            );
        });
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest tests/OpenWaClientTest.php`
Expected: `Tests:  3 passed`

- [ ] **Step 6: Commit**

```bash
git add src/OpenWaClient.php src/OpenWaServiceProvider.php tests/OpenWaClientTest.php
git commit -m "feat: add OpenWaClient session listing and latest-ready resolution"
```

---

### Task 5: `OpenWaClient` — send text/image/document + facade

**Files:**
- Modify: `src/OpenWaClient.php` (add send methods)
- Modify: `src/Facades/OpenWa.php` (point accessor at `OpenWaClient`)
- Modify: `src/OpenWa.php` → delete (placeholder no longer needed)
- Test: `tests/OpenWaClientSendTest.php`
- Test: `tests/FacadeTest.php`

**Interfaces:**
- Consumes: `getLatestReadySessionId()`, `request()`, `handle()` (Task 4, same class); `MessageResponse::fromArray()` (Task 3).
- Produces: `sendText(string $chatId, string $text, ?string $sessionId = null, array $mentions = []): MessageResponse`, `sendImage(string $chatId, array $media, ?string $sessionId = null): MessageResponse`, `sendDocument(string $chatId, array $media, ?string $sessionId = null): MessageResponse`. `$media` shape: `['url' => ?string, 'base64' => ?string, 'mimetype' => ?string, 'filename' => ?string, 'caption' => ?string, 'mentions' => ?array]`. Consumed by Task 6 (`WhatsAppChannel`).

- [ ] **Step 1: Write the failing tests**

`tests/OpenWaClientSendTest.php`:

```php
<?php

use Illuminate\Support\Facades\Http;
use ZarulIzham\OpenWa\Data\MessageResponse;
use ZarulIzham\OpenWa\Exceptions\OpenWaException;
use ZarulIzham\OpenWa\OpenWaClient;

beforeEach(function () {
    config(['openwa.base_url' => 'https://openwa.test/api']);
    config(['openwa.api_key' => 'test-key']);
});

function fakeMessageResponse(): array
{
    return [
        'messageId' => 'true_628123456789@c.us_3EB0123456789',
        'timestamp' => 1706868000,
    ];
}

it('sends a text message to an explicit session', function () {
    Http::fake([
        'openwa.test/api/sessions/sess_1/messages/send-text' => Http::response(fakeMessageResponse(), 201),
    ]);

    $client = app(OpenWaClient::class);
    $response = $client->sendText('628123456789@c.us', 'Hello from OpenWA!', 'sess_1');

    expect($response)->toBeInstanceOf(MessageResponse::class);
    expect($response->messageId)->toBe('true_628123456789@c.us_3EB0123456789');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://openwa.test/api/sessions/sess_1/messages/send-text'
            && $request['chatId'] === '628123456789@c.us'
            && $request['text'] === 'Hello from OpenWA!';
    });
});

it('auto-resolves the session when none is given', function () {
    Http::fake([
        'openwa.test/api/sessions' => Http::response([[
            'id' => 'sess_auto',
            'name' => 'bot-1',
            'status' => 'ready',
            'phone' => null,
            'pushName' => null,
            'connectedAt' => '2026-07-31T00:00:00Z',
            'lastActive' => null,
            'createdAt' => '2026-07-01T00:00:00Z',
            'updatedAt' => '2026-07-31T00:00:00Z',
            'lastError' => null,
            'engineLoaded' => true,
        ]], 200),
        'openwa.test/api/sessions/sess_auto/messages/send-text' => Http::response(fakeMessageResponse(), 201),
    ]);

    $client = app(OpenWaClient::class);
    $client->sendText('628123456789@c.us', 'Hi');

    Http::assertSent(fn ($request) => $request->url() === 'https://openwa.test/api/sessions/sess_auto/messages/send-text');
});

it('throws when auto-resolving with no ready session', function () {
    Http::fake([
        'openwa.test/api/sessions' => Http::response([], 200),
    ]);

    $client = app(OpenWaClient::class);

    expect(fn () => $client->sendText('628123456789@c.us', 'Hi'))
        ->toThrow(OpenWaException::class, 'No OpenWA session with status "ready" is available.');
});

it('sends an image message with a url', function () {
    Http::fake([
        'openwa.test/api/sessions/sess_1/messages/send-image' => Http::response(fakeMessageResponse(), 201),
    ]);

    $client = app(OpenWaClient::class);
    $client->sendImage('628123456789@c.us', [
        'url' => 'https://example.com/image.jpg',
        'caption' => 'Check this out!',
    ], 'sess_1');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://openwa.test/api/sessions/sess_1/messages/send-image'
            && $request['url'] === 'https://example.com/image.jpg'
            && $request['caption'] === 'Check this out!'
            && ! isset($request['base64']);
    });
});

it('sends a document message with base64 content', function () {
    Http::fake([
        'openwa.test/api/sessions/sess_1/messages/send-document' => Http::response(fakeMessageResponse(), 201),
    ]);

    $client = app(OpenWaClient::class);
    $client->sendDocument('628123456789@c.us', [
        'base64' => 'ZmFrZS1wZGYtY29udGVudA==',
        'mimetype' => 'application/pdf',
        'filename' => 'invoice.pdf',
    ], 'sess_1');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://openwa.test/api/sessions/sess_1/messages/send-document'
            && $request['base64'] === 'ZmFrZS1wZGYtY29udGVudA=='
            && $request['mimetype'] === 'application/pdf'
            && $request['filename'] === 'invoice.pdf';
    });
});

it('throws OpenWaException on a failed send', function () {
    Http::fake([
        'openwa.test/api/sessions/sess_1/messages/send-text' => Http::response(['message' => 'chatId is required'], 400),
    ]);

    $client = app(OpenWaClient::class);

    expect(fn () => $client->sendText('', 'Hi', 'sess_1'))
        ->toThrow(OpenWaException::class, 'chatId is required');
});
```

`tests/FacadeTest.php`:

```php
<?php

use Illuminate\Support\Facades\Http;
use ZarulIzham\OpenWa\Facades\OpenWa;

it('proxies to the bound OpenWaClient', function () {
    config(['openwa.base_url' => 'https://openwa.test/api']);
    config(['openwa.api_key' => 'test-key']);

    Http::fake([
        'openwa.test/api/sessions' => Http::response([], 200),
    ]);

    expect(OpenWa::getSessions())->toHaveCount(0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/OpenWaClientSendTest.php tests/FacadeTest.php`
Expected: FAIL — `sendText`/`sendImage`/`sendDocument` don't exist on `OpenWaClient`, and the `OpenWa` facade still points at the placeholder `OpenWa` class.

- [ ] **Step 3: Add send methods to `src/OpenWaClient.php`**

Add this import alongside the existing `use ZarulIzham\OpenWa\Data\SessionData;` line at the top of the file:

```php
use ZarulIzham\OpenWa\Data\MessageResponse;
```

Then add these methods to the `OpenWaClient` class (after `getLatestReadySessionId()`, before `protected function request()`):

```php
    public function sendText(string $chatId, string $text, ?string $sessionId = null, array $mentions = []): MessageResponse
    {
        $payload = array_filter([
            'chatId' => $chatId,
            'text' => $text,
            'mentions' => $mentions ?: null,
        ], fn ($value) => $value !== null);

        return $this->sendMessage('send-text', $sessionId, $payload);
    }

    public function sendImage(string $chatId, array $media, ?string $sessionId = null): MessageResponse
    {
        return $this->sendMessage('send-image', $sessionId, $this->mediaPayload($chatId, $media));
    }

    public function sendDocument(string $chatId, array $media, ?string $sessionId = null): MessageResponse
    {
        return $this->sendMessage('send-document', $sessionId, $this->mediaPayload($chatId, $media));
    }

    protected function mediaPayload(string $chatId, array $media): array
    {
        return array_filter([
            'chatId' => $chatId,
            'url' => $media['url'] ?? null,
            'base64' => $media['base64'] ?? null,
            'mimetype' => $media['mimetype'] ?? null,
            'filename' => $media['filename'] ?? null,
            'caption' => $media['caption'] ?? null,
            'mentions' => $media['mentions'] ?? null,
        ], fn ($value) => $value !== null);
    }

    protected function sendMessage(string $endpoint, ?string $sessionId, array $payload): MessageResponse
    {
        $sessionId ??= $this->getLatestReadySessionId();

        if ($sessionId === null) {
            throw OpenWaException::noReadySession();
        }

        $response = $this->request()->post("/sessions/{$sessionId}/messages/{$endpoint}", $payload);

        return MessageResponse::fromArray($this->handle($response));
    }
```

- [ ] **Step 4: Delete the placeholder class and repoint the facade**

```bash
git rm src/OpenWa.php
```

Replace `src/Facades/OpenWa.php`:

```php
<?php

namespace ZarulIzham\OpenWa\Facades;

use Illuminate\Support\Facades\Facade;
use ZarulIzham\OpenWa\OpenWaClient;

/**
 * @method static \Illuminate\Support\Collection getSessions(?int $limit = null, ?int $offset = null)
 * @method static ?string getLatestReadySessionId()
 * @method static \ZarulIzham\OpenWa\Data\MessageResponse sendText(string $chatId, string $text, ?string $sessionId = null, array $mentions = [])
 * @method static \ZarulIzham\OpenWa\Data\MessageResponse sendImage(string $chatId, array $media, ?string $sessionId = null)
 * @method static \ZarulIzham\OpenWa\Data\MessageResponse sendDocument(string $chatId, array $media, ?string $sessionId = null)
 *
 * @see \ZarulIzham\OpenWa\OpenWaClient
 */
class OpenWa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OpenWaClient::class;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/OpenWaClientSendTest.php tests/FacadeTest.php`
Expected: `Tests:  7 passed`

- [ ] **Step 6: Run the full suite so far**

Run: `vendor/bin/pest`
Expected: all tests passed (ArchTest + Config + Data + Exceptions + OpenWaClient + OpenWaClientSend + Facade).

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add send-text/image/document to OpenWaClient and wire the facade"
```

---

### Task 6: `whatsapp` Notification channel

**Files:**
- Create: `src/Notifications/OpenWaMessage.php`
- Create: `src/Notifications/Channels/WhatsAppChannel.php`
- Modify: `src/OpenWaServiceProvider.php` (add `packageBooted()` to register the channel)
- Test: `tests/Notifications/WhatsAppChannelTest.php`
- Modify: `README.md` (usage docs)

**Interfaces:**
- Consumes: `OpenWaClient::sendText/sendImage/sendDocument` (Task 5).
- Produces: `OpenWaMessage::text(string $text, array $mentions = []): OpenWaMessage`, `::image(string $urlOrBase64, bool $isBase64 = false, ?string $caption = null, ?string $mimetype = null): OpenWaMessage`, `::document(string $urlOrBase64, bool $isBase64 = false, ?string $filename = null, ?string $caption = null, ?string $mimetype = null): OpenWaMessage`, `->sessionId(string $id): OpenWaMessage`. Notification channel key `whatsapp`, calling `$notifiable->routeNotificationFor('whatsapp', $notification)` and `$notification->toWhatsApp($notifiable)`.

- [ ] **Step 1: Write the failing test**

`tests/Notifications/WhatsAppChannelTest.php`:

```php
<?php

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use ZarulIzham\OpenWa\Notifications\OpenWaMessage;

class TestWhatsAppNotifiable
{
    use \Illuminate\Notifications\Notifiable;

    public function routeNotificationForWhatsapp(): string
    {
        return '628123456789@c.us';
    }
}

class TestTextNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsApp($notifiable): OpenWaMessage
    {
        return OpenWaMessage::text('Order shipped!')->sessionId('sess_1');
    }
}

class TestImageNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsApp($notifiable): OpenWaMessage
    {
        return OpenWaMessage::image('https://example.com/receipt.jpg', caption: 'Your receipt')->sessionId('sess_1');
    }
}

beforeEach(function () {
    config(['openwa.base_url' => 'https://openwa.test/api']);
    config(['openwa.api_key' => 'test-key']);
});

it('sends a text notification through the whatsapp channel', function () {
    Http::fake([
        'openwa.test/api/sessions/sess_1/messages/send-text' => Http::response([
            'messageId' => 'msg_1',
            'timestamp' => 1706868000,
        ], 201),
    ]);

    (new TestWhatsAppNotifiable)->notify(new TestTextNotification);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://openwa.test/api/sessions/sess_1/messages/send-text'
            && $request['chatId'] === '628123456789@c.us'
            && $request['text'] === 'Order shipped!';
    });
});

it('sends an image notification through the whatsapp channel', function () {
    Http::fake([
        'openwa.test/api/sessions/sess_1/messages/send-image' => Http::response([
            'messageId' => 'msg_2',
            'timestamp' => 1706868000,
        ], 201),
    ]);

    (new TestWhatsAppNotifiable)->notify(new TestImageNotification);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://openwa.test/api/sessions/sess_1/messages/send-image'
            && $request['chatId'] === '628123456789@c.us'
            && $request['url'] === 'https://example.com/receipt.jpg'
            && $request['caption'] === 'Your receipt';
    });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Notifications/WhatsAppChannelTest.php`
Expected: FAIL — `ZarulIzham\OpenWa\Notifications\OpenWaMessage` doesn't exist and the `whatsapp` channel isn't registered, so `notify()` throws.

- [ ] **Step 3: Create `src/Notifications/OpenWaMessage.php`**

```php
<?php

namespace ZarulIzham\OpenWa\Notifications;

class OpenWaMessage
{
    public ?string $text = null;
    public ?string $url = null;
    public ?string $base64 = null;
    public ?string $mimetype = null;
    public ?string $filename = null;
    public ?string $caption = null;
    public array $mentions = [];
    public ?string $sessionId = null;

    protected function __construct(public string $type)
    {
    }

    public static function text(string $text, array $mentions = []): self
    {
        $message = new self('text');
        $message->text = $text;
        $message->mentions = $mentions;

        return $message;
    }

    public static function image(string $urlOrBase64, bool $isBase64 = false, ?string $caption = null, ?string $mimetype = null): self
    {
        return self::media('image', $urlOrBase64, $isBase64, $caption, $mimetype);
    }

    public static function document(string $urlOrBase64, bool $isBase64 = false, ?string $filename = null, ?string $caption = null, ?string $mimetype = null): self
    {
        $message = self::media('document', $urlOrBase64, $isBase64, $caption, $mimetype);
        $message->filename = $filename;

        return $message;
    }

    protected static function media(string $type, string $urlOrBase64, bool $isBase64, ?string $caption, ?string $mimetype): self
    {
        $message = new self($type);

        if ($isBase64) {
            $message->base64 = $urlOrBase64;
            $message->mimetype = $mimetype;
        } else {
            $message->url = $urlOrBase64;
        }

        $message->caption = $caption;

        return $message;
    }

    public function sessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function toMediaPayload(): array
    {
        return [
            'url' => $this->url,
            'base64' => $this->base64,
            'mimetype' => $this->mimetype,
            'filename' => $this->filename,
            'caption' => $this->caption,
            'mentions' => $this->mentions ?: null,
        ];
    }
}
```

- [ ] **Step 4: Create `src/Notifications/Channels/WhatsAppChannel.php`**

```php
<?php

namespace ZarulIzham\OpenWa\Notifications\Channels;

use Illuminate\Notifications\Notification;
use ZarulIzham\OpenWa\Data\MessageResponse;
use ZarulIzham\OpenWa\Notifications\OpenWaMessage;
use ZarulIzham\OpenWa\OpenWaClient;

class WhatsAppChannel
{
    public function __construct(protected OpenWaClient $client)
    {
    }

    public function send(object $notifiable, Notification $notification): MessageResponse
    {
        $chatId = $notifiable->routeNotificationFor('whatsapp', $notification);

        /** @var OpenWaMessage $message */
        $message = $notification->toWhatsApp($notifiable);

        return match ($message->type) {
            'text' => $this->client->sendText($chatId, $message->text, $message->sessionId, $message->mentions),
            'image' => $this->client->sendImage($chatId, $message->toMediaPayload(), $message->sessionId),
            'document' => $this->client->sendDocument($chatId, $message->toMediaPayload(), $message->sessionId),
        };
    }
}
```

- [ ] **Step 5: Register the channel in the service provider**

Replace `src/OpenWaServiceProvider.php` with:

```php
<?php

namespace ZarulIzham\OpenWa;

use Illuminate\Notifications\ChannelManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use ZarulIzham\OpenWa\Notifications\Channels\WhatsAppChannel;

class OpenWaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('openwa')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(OpenWaClient::class, function ($app) {
            return new OpenWaClient(
                baseUrl: (string) $app['config']->get('openwa.base_url'),
                apiKey: (string) $app['config']->get('openwa.api_key'),
                timeout: (int) $app['config']->get('openwa.timeout', 30),
            );
        });
    }

    public function packageBooted(): void
    {
        $this->app->make(ChannelManager::class)->extend('whatsapp', function ($app) {
            return $app->make(WhatsAppChannel::class);
        });
    }
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `vendor/bin/pest tests/Notifications/WhatsAppChannelTest.php`
Expected: `Tests:  2 passed`

- [ ] **Step 7: Run the entire suite**

Run: `vendor/bin/pest`
Expected: all tests pass, 0 failures.

- [ ] **Step 8: Run Pint and PHPStan**

Run: `vendor/bin/pint` then `vendor/bin/phpstan analyse`
Expected: Pint reports no style violations (or auto-fixes them — re-run tests after if it changes anything); PHPStan reports no errors.

- [ ] **Step 9: Update `README.md`**

Replace the skeleton's README body (keep the badges/header section at the top as-is, replace everything from the "Installation" heading onward) with:

```markdown
## Installation

You can install the package via composer:

```bash
composer require zarulizham/laravel-openwa
```

Publish the config file:

```bash
php artisan vendor:publish --tag="openwa-config"
```

Set your OpenWA server details in `.env`:

```
OPENWA_BASE_URL=https://openwa.prooffice.com.my/api
OPENWA_API_KEY=your-api-key
OPENWA_TIMEOUT=30
```

## Usage

```php
use ZarulIzham\OpenWa\Facades\OpenWa;

// List all sessions
$sessions = OpenWa::getSessions();

// Get the id of the most recently connected session with status "ready"
$sessionId = OpenWa::getLatestReadySessionId();

// Send a text message (auto-resolves the latest ready session if omitted)
OpenWa::sendText('628123456789@c.us', 'Hello from OpenWA!');

// Send an image
OpenWa::sendImage('628123456789@c.us', [
    'url' => 'https://example.com/image.jpg',
    'caption' => 'Check this out!',
]);

// Send a document
OpenWa::sendDocument('628123456789@c.us', [
    'url' => 'https://example.com/invoice.pdf',
    'filename' => 'invoice.pdf',
]);
```

### Notifications

Add a `routeNotificationForWhatsapp()` method to your notifiable model:

```php
public function routeNotificationForWhatsapp(): string
{
    return $this->phone.'@c.us';
}
```

Return an `OpenWaMessage` from your notification's `toWhatsApp()` method:

```php
use ZarulIzham\OpenWa\Notifications\OpenWaMessage;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification
{
    public function via($notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsApp($notifiable): OpenWaMessage
    {
        return OpenWaMessage::text("Your order #{$this->order->id} has shipped!");
    }
}
```
```

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: add whatsapp Notification channel"
```

---

## Post-plan verification

- [ ] Run `vendor/bin/pest` — full suite green.
- [ ] Run `vendor/bin/phpstan analyse` — no errors.
- [ ] Confirm `composer.json` no longer contains any `:vendor_slug`/`:package_slug`/`VendorName` placeholder (`grep -r "VendorName\|:vendor_slug\|:package_slug" src tests config composer.json` returns nothing).
