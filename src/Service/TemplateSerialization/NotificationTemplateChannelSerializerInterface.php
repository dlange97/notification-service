<?php

declare(strict_types=1);

namespace App\Service\TemplateSerialization;

use App\Entity\NotificationTemplate;

interface NotificationTemplateChannelSerializerInterface
{
    public function channel(): string;

    /** @return array<string, mixed> */
    public function serialize(NotificationTemplate $template): array;
}
