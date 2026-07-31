<?php

namespace ZarulIzham\OpenWa\Data;

readonly class MessageResponse
{
    public function __construct(
        public string $messageId,
        public int $timestamp,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            messageId: $data['messageId'],
            timestamp: (int) $data['timestamp'],
        );
    }
}
