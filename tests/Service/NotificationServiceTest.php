<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\InboxNotification;
use App\Entity\NotificationTemplate;
use App\Repository\InboxNotificationRepository;
use App\Repository\NotificationTemplateRepository;
use App\Service\NotificationService;
use App\Service\RequestAccessTemplateUpdater;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NotificationServiceTest extends TestCase
{
    private NotificationTemplateRepository&MockObject $templateRepository;
    private InboxNotificationRepository&MockObject $inboxRepository;
    private NotificationService $service;

    protected function setUp(): void
    {
        $this->templateRepository = $this->createMock(NotificationTemplateRepository::class);
        $this->inboxRepository = $this->createMock(InboxNotificationRepository::class);

        $this->service = new NotificationService(
            $this->templateRepository,
            $this->inboxRepository,
            new RequestAccessTemplateUpdater([]),
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
