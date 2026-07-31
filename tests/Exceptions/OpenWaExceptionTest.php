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
