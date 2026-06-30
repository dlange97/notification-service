<?php

declare(strict_types=1);

namespace App\Service\TemplateSerialization;

use App\Entity\NotificationTemplate;

final class PushTemplateChannelSerializer implements NotificationTemplateChannelSerializerInterface
{
    public function channel(): string
    {
        return 'push';
    }

    public function serialize(NotificationTemplate $template): array
    {
        return [
            'enabled' => $template->isPushEnabled(),
            'title' => $template->getPushTitle(),
            'body' => $template->getPushBody(),
        ];
    }
}
