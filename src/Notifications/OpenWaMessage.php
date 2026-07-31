<?php

namespace ZarulIzham\OpenWa\Notifications;

class OpenWaMessage
{
    public ?string $text = null;

    public ?string $url = null;

    public ?string $base64 = null;

    public ?string $mimetype = null;

    public ?string $filename = null;

    public ?string $caption = null;

    public array $mentions = [];

    public ?string $sessionId = null;

    protected function __construct(public MessageType $type) {}

    public static function text(string $text, array $mentions = []): self
    {
        $message = new self(MessageType::Text);
        $message->text = $text;
        $message->mentions = $mentions;

        return $message;
    }

    public static function image(string $urlOrBase64, bool $isBase64 = false, ?string $caption = null, ?string $mimetype = null): self
    {
        return self::media(MessageType::Image, $urlOrBase64, $isBase64, $caption, $mimetype);
    }

    public static function document(string $urlOrBase64, bool $isBase64 = false, ?string $filename = null, ?string $caption = null, ?string $mimetype = null): self
    {
        $message = self::media(MessageType::Document, $urlOrBase64, $isBase64, $caption, $mimetype);
        $message->filename = $filename;

        return $message;
    }

    protected static function media(MessageType $type, string $urlOrBase64, bool $isBase64, ?string $caption, ?string $mimetype): self
    {
        $message = new self($type);

        if ($isBase64) {
            $message->base64 = $urlOrBase64;
            $message->mimetype = $mimetype;
        } else {
            $message->url = $urlOrBase64;
        }

        $message->caption = $caption;

        return $message;
    }

    public function sessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function toMediaPayload(): array
    {
        return [
            'url' => $this->url,
            'base64' => $this->base64,
            'mimetype' => $this->mimetype,
            'filename' => $this->filename,
            'caption' => $this->caption,
            'mentions' => $this->mentions ?: null,
        ];
    }
}
