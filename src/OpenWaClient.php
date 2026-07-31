<?php

namespace ZarulIzham\OpenWa;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use ZarulIzham\OpenWa\Data\MessageResponse;
use ZarulIzham\OpenWa\Data\SessionData;
use ZarulIzham\OpenWa\Exceptions\OpenWaException;

class OpenWaClient
{
    public function __construct(
        protected string $baseUrl,
        protected string $apiKey,
        protected int $timeout = 30,
        protected ?string $sessionName = null,
    ) {}

    public function getSessions(?int $limit = null, ?int $offset = null): Collection
    {
        $query = array_filter([
            'limit' => $limit,
            'offset' => $offset,
        ], fn ($value) => $value !== null);

        $response = $this->request()->get('/sessions', $query);

        return collect($this->handle($response))
            ->map(fn (array $session) => SessionData::fromArray($session));
    }

    public function getLatestReadySessionId(): ?string
    {
        $cacheKey = 'openwa.latest_ready_session_id'.($this->sessionName !== null ? ":{$this->sessionName}" : '');

        return Cache::remember($cacheKey, now()->addHours(3), function () {
            return $this->getSessions()
                ->filter(fn (SessionData $session) => $session->status === 'ready')
                ->when(
                    $this->sessionName !== null,
                    fn (Collection $sessions) => $sessions->filter(fn (SessionData $session) => $session->name === $this->sessionName)
                )
                ->sortByDesc(fn (SessionData $session) => $session->connectedAt ?? $session->createdAt)
                ->first()
                ?->id;
        });
    }

    public function sendText(string $chatId, string $text, ?string $sessionId = null, array $mentions = []): MessageResponse
    {
        $payload = array_filter([
            'chatId' => $chatId,
            'text' => $text,
            'mentions' => $mentions ?: null,
        ], fn ($value) => $value !== null);

        return $this->sendMessage('send-text', $sessionId, $payload);
    }

    public function sendImage(string $chatId, array $media, ?string $sessionId = null): MessageResponse
    {
        return $this->sendMessage('send-image', $sessionId, $this->mediaPayload($chatId, $media));
    }

    public function sendDocument(string $chatId, array $media, ?string $sessionId = null): MessageResponse
    {
        return $this->sendMessage('send-document', $sessionId, $this->mediaPayload($chatId, $media));
    }

    protected function mediaPayload(string $chatId, array $media): array
    {
        return array_filter([
            'chatId' => $chatId,
            'url' => $media['url'] ?? null,
            'base64' => $media['base64'] ?? null,
            'mimetype' => $media['mimetype'] ?? null,
            'filename' => $media['filename'] ?? null,
            'caption' => $media['caption'] ?? null,
            'mentions' => $media['mentions'] ?? null,
        ], fn ($value) => $value !== null);
    }

    protected function sendMessage(string $endpoint, ?string $sessionId, array $payload): MessageResponse
    {
        $sessionId ??= $this->getLatestReadySessionId();

        if ($sessionId === null) {
            throw OpenWaException::noReadySession();
        }

        $response = $this->request()->post("/sessions/{$sessionId}/messages/{$endpoint}", $payload);

        return MessageResponse::fromArray($this->handle($response));
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['X-API-Key' => $this->apiKey])
            ->timeout($this->timeout)
            ->acceptJson();
    }

    protected function handle(Response $response): array
    {
        if ($response->failed()) {
            throw OpenWaException::fromResponse($response);
        }

        return $response->json();
    }
}
