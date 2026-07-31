<?php

namespace ZarulIzham\OpenWa\Data;

readonly class SessionData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
        public ?string $phone,
        public ?string $pushName,
        public ?string $connectedAt,
        public ?string $lastActive,
        public string $createdAt,
        public string $updatedAt,
        public ?string $lastError,
        public bool $engineLoaded,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            status: $data['status'],
            phone: $data['phone'] ?? null,
            pushName: $data['pushName'] ?? null,
            connectedAt: $data['connectedAt'] ?? null,
            lastActive: $data['lastActive'] ?? null,
            createdAt: $data['createdAt'],
            updatedAt: $data['updatedAt'],
            lastError: $data['lastError'] ?? null,
            engineLoaded: $data['engineLoaded'],
        );
    }
}
