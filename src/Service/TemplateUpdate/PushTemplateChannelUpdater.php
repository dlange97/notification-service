<?php

declare(strict_types=1);

namespace App\Service\TemplateUpdate;

use App\Entity\NotificationTemplate;

final class PushTemplateChannelUpdater extends AbstractTemplateChannelUpdater
{
    public function channel(): string
    {
        return 'push';
    }

    public function update(NotificationTemplate $template, array $payload): void
    {
        $this->applyBoolean($payload, 'enabled', static fn (NotificationTemplate $template, bool $value) => $template->setPushEnabled($value), $template);
        $this->applyString($payload, 'title', static fn (NotificationTemplate $template, string $value) => $template->setPushTitle($value), $template);
        $this->applyString($payload, 'body', static fn (NotificationTemplate $template, string $value) => $template->setPushBody($value), $template);
    }
}
