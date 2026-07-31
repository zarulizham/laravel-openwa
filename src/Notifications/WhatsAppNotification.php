<?php

namespace ZarulIzham\OpenWa\Notifications;

interface WhatsAppNotification
{
    public function toWhatsApp(object $notifiable): OpenWaMessage;
}
