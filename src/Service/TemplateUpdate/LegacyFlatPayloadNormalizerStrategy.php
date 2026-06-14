<?php

declare(strict_types=1);

namespace App\Service\TemplateUpdate;

final class LegacyFlatPayloadNormalizerStrategy implements TemplatePayloadNormalizerStrategyInterface
{
    public function apply(array $payload, array $channels): array
    {
        foreach ($this->legacyFieldMap() as $channel => $fieldMap) {
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
    private function legacyFieldMap(): array
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
