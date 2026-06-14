<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NotificationTemplate;
use App\Service\TemplateUpdate\TemplateChannelUpdaterInterface;
use App\Service\TemplateUpdate\TemplatePayloadNormalizer;

final class RequestAccessTemplateUpdater
{
    /**
     * @param iterable<TemplateChannelUpdaterInterface> $channelUpdaters
     */
    public function __construct(
        private readonly iterable $channelUpdaters,
        private readonly TemplatePayloadNormalizer $payloadNormalizer,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function update(NotificationTemplate $template, array $payload): void
    {
        $channels = $this->payloadNormalizer->normalize($payload);

        foreach ($this->channelUpdaters as $channelUpdater) {
            $channelUpdater->update(
                $template,
                is_array($channels[$channelUpdater->channel()] ?? null) ? $channels[$channelUpdater->channel()] : [],
            );
        }
    }
}
