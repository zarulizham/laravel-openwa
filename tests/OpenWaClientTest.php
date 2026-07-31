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

it('filters sessions by the configured session name', function () {
    config(['openwa.session_name' => 'bot-2']);

    Http::fake([
        'openwa.test/api/sessions*' => Http::response([
            fakeSession(['id' => 'sess_1', 'name' => 'bot-1', 'status' => 'ready', 'connectedAt' => '2026-07-31T00:00:00Z']),
            fakeSession(['id' => 'sess_2', 'name' => 'bot-2', 'status' => 'ready', 'connectedAt' => '2026-07-01T00:00:00Z']),
        ], 200),
    ]);

    $client = app(OpenWaClient::class);

    expect($client->getLatestReadySessionId())->toBe('sess_2');
});

it('caches the resolved latest ready session id', function () {
    Http::fake([
        'openwa.test/api/sessions*' => Http::response([
            fakeSession(['id' => 'sess_1', 'status' => 'ready']),
        ], 200),
    ]);

    $client = app(OpenWaClient::class);

    expect($client->getLatestReadySessionId())->toBe('sess_1');
    expect($client->getLatestReadySessionId())->toBe('sess_1');

    Http::assertSentCount(1);
});
