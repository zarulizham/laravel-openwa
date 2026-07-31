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
