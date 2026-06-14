<?php

declare(strict_types=1);

namespace App\Service\TemplateUpdate;

interface TemplatePayloadNormalizerStrategyInterface
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, array<string, mixed>> $channels
     * @return array<string, array<string, mixed>>
     */
    public function apply(array $payload, array $channels): array;
}
