<?php

namespace ZarulIzham\OpenWa\Notifications\Channels;

use Illuminate\Notifications\Notification;
use InvalidArgumentException;
use ZarulIzham\OpenWa\Data\MessageResponse;
use ZarulIzham\OpenWa\Notifications\MessageType;
use ZarulIzham\OpenWa\Notifications\WhatsAppNotification;
use ZarulIzham\OpenWa\OpenWaClient;

class WhatsAppChannel
{
    public function __construct(protected OpenWaClient $client) {}

    public function send(object $notifiable, Notification $notification): MessageResponse
    {
        if (! $notification instanceof WhatsAppNotification) {
            throw new InvalidArgumentException(
                $notification::class.' must implement '.WhatsAppNotification::class.' to be sent via the openwa channel.'
            );
        }

        if (! method_exists($notifiable, 'routeNotificationForWhatsapp')) {
            throw new InvalidArgumentException(
                $notifiable::class.' must define a routeNotificationForWhatsapp() method to be notified via the openwa channel.'
            );
        }

        $chatId = $notifiable->routeNotificationFor('whatsapp', $notification);

        if (! str_ends_with($chatId, '@c.us') && ! str_ends_with($chatId, '@g.us')) {
            $chatId .= '@c.us';
        }

        $message = $notification->toWhatsApp($notifiable);

        return match ($message->type) {
            MessageType::Text => $this->client->sendText($chatId, $message->text, $message->sessionId, $message->mentions),
            MessageType::Image => $this->client->sendImage($chatId, $message->toMediaPayload(), $message->sessionId),
            MessageType::Document => $this->client->sendDocument($chatId, $message->toMediaPayload(), $message->sessionId),
        };
    }
}
