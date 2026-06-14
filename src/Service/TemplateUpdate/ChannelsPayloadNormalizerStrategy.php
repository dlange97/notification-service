<?php

declare(strict_types=1);

namespace App\Service\TemplateUpdate;

final class ChannelsPayloadNormalizerStrategy implements TemplatePayloadNormalizerStrategyInterface
{
    public function apply(array $payload, array $channels): array
    {
        if (!is_array($payload['channels'] ?? null)) {
            return $channels;
        }

        foreach ($payload['channels'] as $channel => $channelPayload) {
            if (!is_string($channel) || !is_array($channelPayload)) {
                continue;
            }

            $current = is_array($channels[$channel] ?? null) ? $channels[$channel] : [];
            $channels[$channel] = array_merge($current, $channelPayload);
        }

        return $channels;
    }
}
