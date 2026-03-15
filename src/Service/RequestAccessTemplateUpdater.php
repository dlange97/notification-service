<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NotificationTemplate;
use App\Service\TemplateUpdate\TemplateChannelUpdaterInterface;

final class RequestAccessTemplateUpdater
{
    /**
     * @param iterable<TemplateChannelUpdaterInterface> $channelUpdaters
     */
    public function __construct(private readonly iterable $channelUpdaters)
    {
    }

    /** @param array<string, mixed> $payload */
    public function update(NotificationTemplate $template, array $payload): void
    {
        $channels = $this->normalizeChannels($payload);

        foreach ($this->channelUpdaters as $channelUpdater) {
            $channelUpdater->update(
                $template,
                is_array($channels[$channelUpdater->channel()] ?? null) ? $channels[$channelUpdater->channel()] : [],
            );
        }
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, array<string, mixed>>
     */
    private function normalizeChannels(array $payload): array
    {
        $channels = is_array($payload['channels'] ?? null) ? $payload['channels'] : [];

        foreach (self::legacyFieldMap() as $channel => $fieldMap) {
            $current = is_array($channels[$channel] ?? null) ? $channels[$channel] : [];

            foreach ($fieldMap as $field => $legacyKey) {
                if (array_key_exists($legacyKey, $payload)) {
                    $current[$field] = $payload[$legacyKey];
                }
            }

            $channels[$channel] = $current;
        }

        return $channels;
    }

    /** @return array<string, array<string, string>> */
    private static function legacyFieldMap(): array
    {
        return [
            'inbox' => [
                'enabled' => 'inboxEnabled',
                'title' => 'inboxTitle',
                'body' => 'inboxBody',
            ],
            'email' => [
                'enabled' => 'emailEnabled',
                'title' => 'emailTitle',
                'body' => 'emailBody',
            ],
            'push' => [
                'enabled' => 'pushEnabled',
                'title' => 'pushTitle',
                'body' => 'pushBody',
            ],
        ];
    }
}
