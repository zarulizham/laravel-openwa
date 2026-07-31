# OpenWA Laravel Wrapper — Design

## Purpose

Wrap the OpenWA REST API (https://openwa.prooffice.com.my/api/docs) as a Laravel package so the host app can list WhatsApp sessions and send text/image/document messages, including a Notification channel for standard Laravel `Notification::send()` integration.

## Package identity

- Composer package: `zarulizham/laravel-openwa`
- PHP namespace: `ZarulIzham\OpenWa`
- Repo currently the raw `spatie/laravel-package-tools` skeleton (`VendorName\Skeleton`, `:vendor_slug/:package_slug` placeholders) — this work includes running the rename across `composer.json`, `src/`, `config/`, `tests/`.

## API surface consumed

Base: `https://openwa.prooffice.com.my/api` (configurable). Auth: header `X-API-Key: <key>` on every request (global security scheme in the OpenAPI spec).

| Method | Path | Use |
|---|---|---|
| GET | `/sessions` | List all sessions → `SessionResponseDto[]` |
| POST | `/sessions/{sessionId}/messages/send-text` | `{chatId, text, mentions?}` → `MessageResponseDto` |
| POST | `/sessions/{sessionId}/messages/send-image` | `{chatId, url\|base64, mimetype?, filename?, caption?, mentions?}` → `MessageResponseDto` |
| POST | `/sessions/{sessionId}/messages/send-document` | same body shape as send-image | `MessageResponseDto` |

`SessionResponseDto` fields used: `id`, `name`, `status` (`created\|initializing\|qr_ready\|authenticating\|ready\|disconnected\|action_required\|failed`), `phone`, `pushName`, `connectedAt`, `lastActive`, `createdAt`, `updatedAt`, `lastError`, `engineLoaded`.

`MessageResponseDto`: `{messageId: string, timestamp: number}`. A 2xx only means the gateway accepted the send — not delivery confirmation.

## Config

`config/openwa.php`:
```php
return [
    'base_url' => env('OPENWA_BASE_URL', 'https://openwa.prooffice.com.my/api'),
    'api_key' => env('OPENWA_API_KEY'),
    'timeout' => env('OPENWA_TIMEOUT', 30),
];
```

## Architecture

Single client class, no manager-layer split (rejected: pulling in Saloon for 3 endpoints — unneeded dependency; splitting into separate Session/Message manager classes — unneeded layering for this surface).

| File | Responsibility |
|---|---|
| `src/OpenWaClient.php` | `Illuminate\Http\Client` (`Http::` facade) wrapper. Public API below. |
| `src/Facades/OpenWa.php` | Static facade proxy to the bound `OpenWaClient` singleton. |
| `src/Data/SessionData.php` | Readonly DTO mirroring `SessionResponseDto`. |
| `src/Data/MessageResponse.php` | Readonly DTO `{messageId, timestamp}`. |
| `src/Exceptions/OpenWaException.php` | Thrown on non-2xx or connection failure; carries HTTP status + decoded body. |
| `src/Notifications/OpenWaMessage.php` | Value object describing what to send from a Notification (text/image/document). |
| `src/Notifications/Channels/WhatsAppChannel.php` | Laravel Notification channel, registered under key `whatsapp`. |
| `src/OpenWaServiceProvider.php` | Publishes config, registers singleton + facade + notification channel. |

### `OpenWaClient` public methods

```php
getSessions(?int $limit = null, ?int $offset = null): Illuminate\Support\Collection  // Collection<SessionData>
getLatestReadySessionId(): ?string
sendText(string $chatId, string $text, ?string $sessionId = null, array $mentions = []): MessageResponse
sendImage(string $chatId, array $media, ?string $sessionId = null): MessageResponse   // $media: url|base64+mimetype, filename?, caption?, mentions?
sendDocument(string $chatId, array $media, ?string $sessionId = null): MessageResponse // same $media shape
```

Every send method accepts `?string $sessionId = null`. Null means: resolve automatically via `getLatestReadySessionId()`; throw `OpenWaException` if no session is `ready`. Callers can still pass an explicit session id to bypass auto-resolution.

### "Latest ready session" rule

1. Fetch all sessions.
2. Filter `status === 'ready'`.
3. Sort by `connectedAt ?? createdAt` descending.
4. Return the first `id`, or `null` if none are ready.

### Errors

Any non-2xx response → `OpenWaException` with the HTTP status code and decoded JSON error body (message surfaced from the API's error shape). No retry/backoff logic in v1 — YAGNI.

## Notification channel

- Registered as `whatsapp` (not `openwa`) — so notifiable models implement `routeNotificationForWhatsapp(): string` returning the destination `chatId`.
- Notification classes return an `OpenWaMessage` from `toWhatsApp($notifiable)`:
  ```php
  OpenWaMessage::text(string $text, array $mentions = []): self
  OpenWaMessage::image(string $urlOrBase64, bool $isBase64 = false, ?string $caption = null, ?string $mimetype = null): self
  OpenWaMessage::document(string $urlOrBase64, bool $isBase64 = false, ?string $filename = null, ?string $caption = null, ?string $mimetype = null): self
  ```
  Optional `->sessionId(string $id)` to override auto-resolution.
- `WhatsAppChannel::send($notifiable, $notification)`: resolves chatId via `routeNotificationForWhatsapp`, builds `OpenWaMessage` via `toWhatsApp`, dispatches to the matching `OpenWaClient` method. Sending is synchronous inside the channel; queuing is achieved the standard Laravel way — the Notification class implements `ShouldQueue`.

## Testing

Pest + `Http::fake()`:
- `getSessions()` parses fixture response into `SessionData[]`.
- `getLatestReadySessionId()`: multiple sessions, tie-break by `connectedAt`/`createdAt`, none-ready returns `null`.
- `sendText/sendImage/sendDocument`: request shape assertion (`Http::assertSent`) + response mapped to `MessageResponse`; auto-resolves session when `sessionId` omitted; url-based and base64-based media paths.
- `OpenWaException` thrown on 4xx/5xx, carries status + body.
- `WhatsAppChannel`: dispatches correct client method per `OpenWaMessage` type, uses `routeNotificationForWhatsapp`.

## Out of scope (v1)

Everything else in the OpenWA API — session lifecycle (start/stop/create/qr), groups, contacts, templates, webhooks, bulk send, other message types (video/audio/location/contact/sticker/poll), status, channels/newsletters, labels, catalog. Can be added later following the same `OpenWaClient` pattern.
