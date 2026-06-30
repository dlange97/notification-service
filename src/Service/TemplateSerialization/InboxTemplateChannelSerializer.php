<?php

declare(strict_types=1);

namespace App\Service\TemplateSerialization;

use App\Entity\NotificationTemplate;

final class InboxTemplateChannelSerializer implements NotificationTemplateChannelSerializerInterface
{
    public function channel(): string
    {
        return 'inbox';
    }

    public function serialize(NotificationTemplate $template): array
    {
        return [
            'enabled' => $template->isInboxEnabled(),
            'title' => $template->getInboxTitle(),
            'body' => $template->getInboxBody(),
        ];
    }
}
