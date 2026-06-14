<?php

declare(strict_types=1);

namespace App\Service\TemplateUpdate;

final class TemplatePayloadNormalizer
{
    /**
     * @param iterable<TemplatePayloadNormalizerStrategyInterface> $strategies
     */
    public function __construct(private readonly iterable $strategies)
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, array<string, mixed>>
     */
    public function normalize(array $payload): array
    {
        $channels = [];

        foreach ($this->strategies as $strategy) {
            $channels = $strategy->apply($payload, $channels);
        }

        return $channels;
    }
}
