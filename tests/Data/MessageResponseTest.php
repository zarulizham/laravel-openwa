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
