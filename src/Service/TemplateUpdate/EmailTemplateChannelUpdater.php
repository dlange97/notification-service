<?php

declare(strict_types=1);

namespace App\Service\TemplateUpdate;

use App\Entity\NotificationTemplate;

final class EmailTemplateChannelUpdater extends AbstractTemplateChannelUpdater
{
    public function channel(): string
    {
        return 'email';
    }

    public function update(NotificationTemplate $template, array $payload): void
    {
        $this->applyBoolean($payload, 'enabled', static fn (NotificationTemplate $template, bool $value) => $template->setEmailEnabled($value), $template);
        $this->applyString($payload, 'title', static fn (NotificationTemplate $template, string $value) => $template->setEmailTitle($value), $template);
        $this->applyString($payload, 'body', static fn (NotificationTemplate $template, string $value) => $template->setEmailBody($value), $template);
    }
}
