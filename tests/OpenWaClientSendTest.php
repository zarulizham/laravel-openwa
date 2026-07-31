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
