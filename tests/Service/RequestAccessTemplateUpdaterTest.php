<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\NotificationTemplate;
use App\Service\RequestAccessTemplateUpdater;
use App\Service\TemplateUpdate\ChannelsPayloadNormalizerStrategy;
use App\Service\TemplateUpdate\LegacyFlatPayloadNormalizerStrategy;
use App\Service\TemplateUpdate\TemplateChannelUpdaterInterface;
use App\Service\TemplateUpdate\TemplatePayloadNormalizer;
use PHPUnit\Framework\TestCase;

final class RequestAccessTemplateUpdaterTest extends TestCase
{
    public function testUpdateAppliesBothChannelsAndLegacyFields(): void
    {
        $inboxSpy = new ChannelPayloadSpy('inbox');
        $emailSpy = new ChannelPayloadSpy('email');
        $pushSpy = new ChannelPayloadSpy('push');

        $updater = new RequestAccessTemplateUpdater(
            [$inboxSpy, $emailSpy, $pushSpy],
            new TemplatePayloadNormalizer([
                new ChannelsPayloadNormalizerStrategy(),
                new LegacyFlatPayloadNormalizerStrategy(),
            ]),
        );

        $template = new NotificationTemplate();

        $updater->update($template, [
            'channels' => [
                'inbox' => ['title' => 'Inbox title'],
            ],
            'emailBody' => 'Legacy email body',
            'pushEnabled' => true,
        ]);

        $this->assertSame(['title' => 'Inbox title'], $inboxSpy->receivedPayload);
        $this->assertSame(['body' => 'Legacy email body'], $emailSpy->receivedPayload);
        $this->assertSame(['enabled' => true], $pushSpy->receivedPayload);
    }

    public function testUpdatePrefersLegacyValuesWhenBothArePresent(): void
    {
        $inboxSpy = new ChannelPayloadSpy('inbox');

        $updater = new RequestAccessTemplateUpdater(
            [$inboxSpy],
            new TemplatePayloadNormalizer([
                new ChannelsPayloadNormalizerStrategy(),
                new LegacyFlatPayloadNormalizerStrategy(),
            ]),
        );

        $template = new NotificationTemplate();

        $updater->update($template, [
            'channels' => [
                'inbox' => ['title' => 'new value'],
            ],
            'inboxTitle' => 'legacy value',
        ]);

        $this->assertSame('legacy value', $inboxSpy->receivedPayload['title']);
    }
}

final class ChannelPayloadSpy implements TemplateChannelUpdaterInterface
{
    /** @var array<string, mixed> */
    public array $receivedPayload = [];

    public function __construct(private readonly string $channel)
    {
    }

    public function channel(): string
    {
        return $this->channel;
    }

    public function update(NotificationTemplate $template, array $payload): void
    {
        $this->receivedPayload = $payload;
    }
}
