<?php

namespace ZarulIzham\OpenWa\Notifications;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Document = 'document';
}
