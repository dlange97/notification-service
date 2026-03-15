<?php

declare(strict_types=1);

namespace App\Service\TemplateUpdate;

use App\Entity\NotificationTemplate;

interface TemplateChannelUpdaterInterface
{
    public function channel(): string;

    /** @param array<string, mixed> $payload */
    public function update(NotificationTemplate $template, array $payload): void;
}
