<?php

namespace ZarulIzham\OpenWa\Facades;

use Illuminate\Support\Facades\Facade;
use ZarulIzham\OpenWa\OpenWaClient;

/**
 * @method static \Illuminate\Support\Collection getSessions(?int $limit = null, ?int $offset = null)
 * @method static ?string getLatestReadySessionId()
 * @method static \ZarulIzham\OpenWa\Data\MessageResponse sendText(string $chatId, string $text, ?string $sessionId = null, array $mentions = [])
 * @method static \ZarulIzham\OpenWa\Data\MessageResponse sendImage(string $chatId, array $media, ?string $sessionId = null)
 * @method static \ZarulIzham\OpenWa\Data\MessageResponse sendDocument(string $chatId, array $media, ?string $sessionId = null)
 *
 * @see OpenWaClient
 */
class OpenWa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return OpenWaClient::class;
    }
}
