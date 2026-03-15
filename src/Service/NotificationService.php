<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\InboxNotification;
use App\Entity\NotificationTemplate;
use App\Repository\InboxNotificationRepository;
use App\Repository\NotificationTemplateRepository;

class NotificationService
{
    public function __construct(
        private readonly NotificationTemplateRepository $templateRepository,
        private readonly InboxNotificationRepository $inboxRepository,
        private readonly RequestAccessTemplateUpdater $requestAccessTemplateUpdater,
    ) {
    }

    public function getOrCreateRequestAccessTemplate(): NotificationTemplate
    {
        $template = $this->templateRepository->findByKey(NotificationTemplate::REQUEST_ACCESS_KEY);
        if ($template) {
            return $template;
        }

        $template = new NotificationTemplate();
        $template->setTemplateKey(NotificationTemplate::REQUEST_ACCESS_KEY);

        $this->templateRepository->save($template, true);

        return $template;
    }

    /** @param array<string, mixed> $payload */
    public function updateRequestAccessTemplate(array $payload): NotificationTemplate
    {
        $template = $this->getOrCreateRequestAccessTemplate();

        $this->requestAccessTemplateUpdater->update($template, $payload);

        $this->templateRepository->save($template, true);

        return $template;
    }

    /**
     * @param array<int, array{id:string,email:string}> $recipients
     * @param array<string, mixed> $requester
     */
    public function createRequestAccessNotifications(array $recipients, array $requester): int
    {
        $template = $this->getOrCreateRequestAccessTemplate();
        if (!$template->isInboxEnabled()) {
            return 0;
        }

        $created = 0;
        foreach ($recipients as $recipient) {
            $notification = new InboxNotification();
            $notification
                ->setRecipientUserId((string) $recipient['id'])
                ->setRecipientEmail((string) $recipient['email'])
                ->setType(NotificationTemplate::REQUEST_ACCESS_KEY)
                ->setTitle($this->renderTemplate($template->getInboxTitle(), $requester))
                ->setBody($this->renderTemplate($template->getInboxBody(), $requester))
                ->setPayload([
                    'requester' => $requester,
                    'requestedAt' => (new \DateTimeImmutable())->format('c'),
                ]);

            $this->inboxRepository->save($notification);
            ++$created;
        }

        if ($created > 0) {
            $this->inboxRepository->flush();
        }

        return $created;
    }

    /** @param array<string, mixed> $data */
    public function createInboxNotification(array $data): InboxNotification
    {
        $notification = new InboxNotification();
        $notification
            ->setRecipientUserId((string) ($data['recipientUserId'] ?? ''))
            ->setRecipientEmail((string) ($data['recipientEmail'] ?? ''))
            ->setType((string) ($data['type'] ?? 'request-access'))
            ->setTitle((string) ($data['title'] ?? 'Request access'))
            ->setBody((string) ($data['body'] ?? 'User requested access.'))
            ->setPayload($data['payload'] ?? null);

        $this->inboxRepository->save($notification, true);

        return $notification;
    }

    /** @param array<string, mixed> $data */
    public function updateInboxNotification(InboxNotification $notification, array $data): InboxNotification
    {
        if (isset($data['title'])) {
            $notification->setTitle((string) $data['title']);
        }
        if (isset($data['body'])) {
            $notification->setBody((string) $data['body']);
        }
        if (isset($data['payload'])) {
            $notification->setPayload($data['payload']);
        }

        $this->inboxRepository->save($notification, true);

        return $notification;
    }

    public function deleteInboxNotification(InboxNotification $notification): void
    {
        $this->inboxRepository->remove($notification, true);
    }

    /** @return array<int, array<string, mixed>> */
    public function getInboxForUser(string $userId, int $limit = 50): array
    {
        $items = $this->inboxRepository->findInboxByUser($userId, $limit);
        return array_map($this->serializeInbox(...), $items);
    }

    public function countUnreadForUser(string $userId): int
    {
        return $this->inboxRepository->countUnreadByUser($userId);
    }

    public function clearInboxForUser(string $userId): int
    {
        return $this->inboxRepository->deleteAllByUser($userId);
    }

    public function markAsRead(InboxNotification $notification): InboxNotification
    {
        $notification->setIsRead(true);
        $this->inboxRepository->save($notification, true);

        return $notification;
    }

    /** @return array<string, mixed> */
    public function serializeInbox(InboxNotification $notification): array
    {
        return [
            'id' => $notification->getId(),
            'type' => $notification->getType(),
            'title' => $notification->getTitle(),
            'body' => $notification->getBody(),
            'isRead' => $notification->isRead(),
            'payload' => $notification->getPayload(),
            'createdAt' => $notification->getCreatedAt()?->format('c'),
            'readAt' => $notification->getReadAt()?->format('c'),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeTemplate(NotificationTemplate $template): array
    {
        return [
            'key' => $template->getTemplateKey(),
            'channels' => [
                'inbox' => [
                    'enabled' => $template->isInboxEnabled(),
                    'title' => $template->getInboxTitle(),
                    'body' => $template->getInboxBody(),
                ],
                'email' => [
                    'enabled' => $template->isEmailEnabled(),
                    'title' => $template->getEmailTitle(),
                    'body' => $template->getEmailBody(),
                ],
                'push' => [
                    'enabled' => $template->isPushEnabled(),
                    'title' => $template->getPushTitle(),
                    'body' => $template->getPushBody(),
                ],
            ],
            'updatedAt' => $template->getUpdatedAt()?->format('c'),
        ];
    }

    /** @param array<string, mixed> $requester */
    private function renderTemplate(string $template, array $requester): string
    {
        $map = [
            '{{email}}' => (string) ($requester['email'] ?? ''),
            '{{firstName}}' => (string) ($requester['firstName'] ?? ''),
            '{{lastName}}' => (string) ($requester['lastName'] ?? ''),
            '{{message}}' => (string) ($requester['message'] ?? ''),
        ];

        return strtr($template, $map);
    }
}
