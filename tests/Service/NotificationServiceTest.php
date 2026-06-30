<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\InboxNotification;
use App\Entity\NotificationTemplate;
use App\Repository\InboxNotificationRepository;
use App\Repository\NotificationTemplateRepository;
use App\Service\NotificationService;
use App\Service\NotificationTransportService;
use App\Service\RequestAccessTemplateUpdater;
use App\Service\TemplateSerialization\EmailTemplateChannelSerializer;
use App\Service\TemplateSerialization\InboxTemplateChannelSerializer;
use App\Service\TemplateSerialization\PushTemplateChannelSerializer;
use App\Service\TemplateUpdate\ChannelsPayloadNormalizerStrategy;
use App\Service\TemplateUpdate\LegacyFlatPayloadNormalizerStrategy;
use App\Service\TemplateUpdate\TemplatePayloadNormalizer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NotificationServiceTest extends TestCase
{
    private NotificationTemplateRepository&MockObject $templateRepository;
    private InboxNotificationRepository&MockObject $inboxRepository;
    private NotificationTransportService&MockObject $notificationTransport;
    private NotificationService $service;

    protected function setUp(): void
    {
        $this->templateRepository = $this->createMock(NotificationTemplateRepository::class);
        $this->inboxRepository = $this->createMock(InboxNotificationRepository::class);
        $this->notificationTransport = $this->createMock(NotificationTransportService::class);

        $this->notificationTransport->method('sendEmail')->willReturn(true);
        $this->notificationTransport->method('sendPush')->willReturn(true);

        $this->service = new NotificationService(
            $this->templateRepository,
            $this->inboxRepository,
            new RequestAccessTemplateUpdater(
                [],
                new TemplatePayloadNormalizer([
                    new ChannelsPayloadNormalizerStrategy(),
                    new LegacyFlatPayloadNormalizerStrategy(),
                ]),
            ),
            [
                new InboxTemplateChannelSerializer(),
                new EmailTemplateChannelSerializer(),
                new PushTemplateChannelSerializer(),
            ],
            $this->notificationTransport,
        );
    }

    public function testCreateInboxNotificationUsesProvidedDataAndPersists(): void
    {
        $this->inboxRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(InboxNotification::class), true);

        $notification = $this->service->createInboxNotification([
            'recipientUserId' => 'user-1',
            'recipientEmail' => 'user-1@example.com',
            'type' => 'custom',
            'title' => 'Tytul',
            'body' => 'Tresc',
            'payload' => ['a' => 1],
        ]);

        $this->assertSame('user-1', $notification->getRecipientUserId());
        $this->assertSame('user-1@example.com', $notification->getRecipientEmail());
        $this->assertSame('custom', $notification->getType());
        $this->assertSame('Tytul', $notification->getTitle());
        $this->assertSame('Tresc', $notification->getBody());
        $this->assertSame(['a' => 1], $notification->getPayload());
    }

    public function testCreateRequestAccessNotificationsReturnsZeroWhenInboxDisabled(): void
    {
        $template = (new NotificationTemplate())
            ->setInboxEnabled(false);

        $this->templateRepository->expects($this->once())
            ->method('findByKey')
            ->with(NotificationTemplate::REQUEST_ACCESS_KEY)
            ->willReturn($template);

        $this->inboxRepository->expects($this->never())->method('save');
        $this->inboxRepository->expects($this->never())->method('flush');

        $created = $this->service->createRequestAccessNotifications(
            [['id' => 'u-1', 'email' => 'owner@example.com']],
            ['email' => 'requester@example.com', 'firstName' => 'Jan', 'lastName' => 'Nowak', 'message' => 'Prosze o dostep'],
        );

        $this->assertSame(0, $created);
    }

    public function testCreateRequestAccessNotificationsCreatesMessagesFromTemplate(): void
    {
        $template = (new NotificationTemplate())
            ->setInboxEnabled(true)
            ->setInboxTitle('Wniosek od {{email}}')
            ->setInboxBody('Autor: {{firstName}} {{lastName}}. {{message}}');

        $this->templateRepository->expects($this->once())
            ->method('findByKey')
            ->with(NotificationTemplate::REQUEST_ACCESS_KEY)
            ->willReturn($template);

        $this->inboxRepository->expects($this->exactly(2))
            ->method('save')
            ->with(
                $this->callback(function (InboxNotification $notification): bool {
                    return str_contains($notification->getTitle(), 'requester@example.com')
                        && str_contains($notification->getBody(), 'Jan Nowak')
                        && str_contains($notification->getBody(), 'Prosze o dostep')
                        && is_array($notification->getPayload())
                        && isset($notification->getPayload()['requester']);
                }),
            );

        $this->inboxRepository->expects($this->once())->method('flush');

        $created = $this->service->createRequestAccessNotifications(
            [
                ['id' => 'u-1', 'email' => 'owner-1@example.com'],
                ['id' => 'u-2', 'email' => 'owner-2@example.com'],
            ],
            ['email' => 'requester@example.com', 'firstName' => 'Jan', 'lastName' => 'Nowak', 'message' => 'Prosze o dostep'],
        );

        $this->assertSame(2, $created);
    }

    public function testCreateUserInvitedNotificationRendersInviteLink(): void
    {
        // findByKey returns null so the service builds the template from defaults,
        // which include the {{inviteLink}} placeholder.
        $this->templateRepository->method('findByKey')->willReturn(null);

        $this->inboxRepository->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(function (InboxNotification $notification): bool {
                    $payload = $notification->getPayload();

                    return str_contains($notification->getBody(), 'http://app.example.test/set-password/abc123')
                        && $notification->getType() === 'user-invited'
                        && is_array($payload)
                        && ($payload['inviteLink'] ?? null) === 'http://app.example.test/set-password/abc123'
                        && ($payload['inviteReference'] ?? null) === 'ref-123';
                }),
                true,
            );

        $result = $this->service->createUserInvitedNotification([
            'recipientUserId' => 'user-1',
            'recipientEmail' => 'invitee@example.com',
            'invitedUserEmail' => 'invitee@example.com',
            'inviteLink' => 'http://app.example.test/set-password/abc123',
            'inviteReference' => 'ref-123',
            'invitedBy' => [
                'userId' => 'admin-1',
                'email' => 'admin@example.com',
                'firstName' => 'Ad',
                'lastName' => 'Min',
            ],
        ]);

        $this->assertTrue($result);
    }

    public function testCreateUserInvitedNotificationReturnsFalseWhenRecipientMissing(): void
    {
        $this->inboxRepository->expects($this->never())->method('save');

        $this->assertFalse($this->service->createUserInvitedNotification([
            'recipientUserId' => '',
            'recipientEmail' => '',
        ]));
    }

    public function testMarkAsReadSetsFlagAndPersists(): void
    {
        $notification = new InboxNotification();

        $this->inboxRepository->expects($this->once())
            ->method('save')
            ->with($notification, true);

        $updated = $this->service->markAsRead($notification);

        $this->assertTrue($updated->isRead());
        $this->assertNotNull($updated->getReadAt());
    }
}
