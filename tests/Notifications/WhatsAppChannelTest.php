<?php

use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use ZarulIzham\OpenWa\Notifications\OpenWaMessage;
use ZarulIzham\OpenWa\Notifications\WhatsAppNotification;

class TestWhatsAppNotifiable
{
    use Notifiable;

    public function routeNotificationForWhatsapp(): string
    {
        return '628123456789@c.us';
    }
}

class TestUnroutableNotifiable
{
    use Notifiable;
}

class TestTextNotification extends Notification implements WhatsAppNotification
{
    public function via($notifiable): array
    {
        return ['openwa'];
    }

    public function toWhatsApp($notifiable): OpenWaMessage
    {
        return OpenWaMessage::text('Order shipped!')->sessionId('sess_1');
    }
}

class TestImageNotification extends Notification implements WhatsAppNotification
{
    public function via($notifiable): array
    {
        return ['openwa'];
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

it('sends a text notification through the openwa channel', function () {
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

it('throws when the notifiable has no routeNotificationForWhatsapp method', function () {
    (new TestUnroutableNotifiable)->notify(new TestTextNotification);
})->throws(InvalidArgumentException::class, TestUnroutableNotifiable::class.' must define a routeNotificationForWhatsapp() method to be notified via the openwa channel.');

it('sends an image notification through the openwa channel', function () {
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
