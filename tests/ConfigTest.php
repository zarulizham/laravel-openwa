<?php

it('has default config values', function () {
    expect(config('openwa.base_url'))->toBe('https://openwa.prooffice.com.my/api');
    expect(config('openwa.api_key'))->toBeNull();
    expect(config('openwa.timeout'))->toBe(30);
    expect(config('openwa.session_name'))->toBeNull();
});

it('reads config values overridden at runtime', function () {
    config(['openwa.base_url' => 'https://openwa.example.test/api']);
    config(['openwa.api_key' => 'secret-key']);

    expect(config('openwa.base_url'))->toBe('https://openwa.example.test/api');
    expect(config('openwa.api_key'))->toBe('secret-key');
});
