<?php

declare(strict_types=1);

namespace App\Service\TemplateUpdate;

use App\Entity\NotificationTemplate;

abstract class AbstractTemplateChannelUpdater implements TemplateChannelUpdaterInterface
{
    protected function applyBoolean(array $payload, string $field, callable $setter, NotificationTemplate $template): void
    {
        if (!array_key_exists($field, $payload)) {
            return;
        }

        $setter($template, (bool) $payload[$field]);
    }

    protected function applyString(array $payload, string $field, callable $setter, NotificationTemplate $template, ?callable $fallback = null): void
    {
        if (!array_key_exists($field, $payload) || !is_string($payload[$field])) {
            return;
        }

        $value = trim($payload[$field]);
        if ($value === '' && $fallback !== null) {
            $value = $fallback($template);
        }

        $setter($template, $value);
    }
}