<?php

declare(strict_types=1);

namespace App\Service\TemplateSerialization;

use App\Entity\NotificationTemplate;

final class EmailTemplateChannelSerializer implements NotificationTemplateChannelSerializerInterface
{
    public function channel(): string
    {
        return 'email';
    }

    public function serialize(NotificationTemplate $template): array
    {
        return [
            'enabled' => $template->isEmailEnabled(),
            'title' => $template->getEmailTitle(),
            'body' => $template->getEmailBody(),
        ];
    }
}
