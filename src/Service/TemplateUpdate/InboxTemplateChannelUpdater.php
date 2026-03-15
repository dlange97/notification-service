<?php

declare(strict_types=1);

namespace App\Service\TemplateUpdate;

use App\Entity\NotificationTemplate;

final class InboxTemplateChannelUpdater extends AbstractTemplateChannelUpdater
{
    public function channel(): string
    {
        return 'inbox';
    }

    public function update(NotificationTemplate $template, array $payload): void
    {
        $this->applyBoolean($payload, 'enabled', static fn (NotificationTemplate $template, bool $value) => $template->setInboxEnabled($value), $template);
        $this->applyString($payload, 'title', static fn (NotificationTemplate $template, string $value) => $template->setInboxTitle($value), $template, static fn (NotificationTemplate $template) => $template->getInboxTitle());
        $this->applyString($payload, 'body', static fn (NotificationTemplate $template, string $value) => $template->setInboxBody($value), $template, static fn (NotificationTemplate $template) => $template->getInboxBody());
    }
}
