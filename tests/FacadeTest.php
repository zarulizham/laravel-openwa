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
