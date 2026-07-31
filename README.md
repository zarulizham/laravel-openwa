# Laravel OpenWA

[![Latest Version on Packagist](https://img.shields.io/packagist/v/zarulizham/laravel-openwa.svg?style=flat-square)](https://packagist.org/packages/zarulizham/laravel-openwa)
[![Total Downloads](https://img.shields.io/packagist/dt/zarulizham/laravel-openwa.svg?style=flat-square)](https://packagist.org/packages/zarulizham/laravel-openwa)

A Laravel wrapper for the [OpenWA](https://openwa.prooffice.com.my/api/docs) WhatsApp gateway API — list sessions, send text/image/document messages, and a `whatsapp` Notification channel.

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
OPENWA_SESSION_NAME=my-bot
```

`OPENWA_SESSION_NAME` is optional. When set, `getLatestReadySessionId()` (and any send call that omits an explicit session id) only considers the session with that `name` — instead of the most recently connected `ready` session across all of them. The resolved session id is cached for 3 hours.

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

Implement `ZarulIzham\OpenWa\Notifications\WhatsAppNotification` on your notification and return an `OpenWaMessage` from `toWhatsApp()`:

```php
use Illuminate\Notifications\Notification;
use ZarulIzham\OpenWa\Notifications\OpenWaMessage;
use ZarulIzham\OpenWa\Notifications\WhatsAppNotification;

class OrderShipped extends Notification implements WhatsAppNotification
{
    public function via($notifiable): array
    {
        return ['openwa'];
    }

    public function toWhatsApp($notifiable): OpenWaMessage
    {
        return OpenWaMessage::text("Your order #{$this->order->id} has shipped!");
    }
}
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Zarul Izham](https://github.com/zarulizham)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
