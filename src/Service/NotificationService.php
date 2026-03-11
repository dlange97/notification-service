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

    public function updateRequestAccessTemplate(array $payload): NotificationTemplate
    {
        $template = $this->getOrCreateRequestAccessTemplate();

        if (isset($payload['channels']) && is_array($payload['channels'])) {
            $channels = $payload['channels'];

            if (isset($channels['inbox']) && is_array($channels['inbox'])) {
                $inbox = $channels['inbox'];
                if (array_key_exists('enabled', $inbox)) {
                    $template->setInboxEnabled((bool) $inbox['enabled']);
                }
                if (array_key_exists('title', $inbox) && is_string($inbox['title'])) {
                    $template->setInboxTitle(trim($inbox['title']) ?: $template->getInboxTitle());
                }
                if (array_key_exists('body', $inbox) && is_string($inbox['body'])) {
                    $template->setInboxBody(trim($inbox['body']) ?: $template->getInboxBody());
                }
            }

            if (isset($channels['email']) && is_array($channels['email'])) {
                $email = $channels['email'];
                if (array_key_exists('enabled', $email)) {
                    $template->setEmailEnabled((bool) $email['enabled']);
                }
                if (array_key_exists('title', $email) && is_string($email['title'])) {
                    $template->setEmailTitle(trim($email['title']));
                }
                if (array_key_exists('body', $email) && is_string($email['body'])) {
                    $template->setEmailBody(trim($email['body']));
                }
            }

            if (isset($channels['push']) && is_array($channels['push'])) {
                $push = $channels['push'];
                if (array_key_exists('enabled', $push)) {
                    $template->setPushEnabled((bool) $push['enabled']);
                }
                if (array_key_exists('title', $push) && is_string($push['title'])) {
                    $template->setPushTitle(trim($push['title']));
                }
                if (array_key_exists('body', $push) && is_string($push['body'])) {
                    $template->setPushBody(trim($push['body']));
                }
            }
        }

        if (array_key_exists('inboxEnabled', $payload)) {
            $template->setInboxEnabled((bool) $payload['inboxEnabled']);
        }
        if (array_key_exists('inboxTitle', $payload) && is_string($payload['inboxTitle'])) {
            $template->setInboxTitle(trim($payload['inboxTitle']) ?: $template->getInboxTitle());
        }
        if (array_key_exists('inboxBody', $payload) && is_string($payload['inboxBody'])) {
            $template->setInboxBody(trim($payload['inboxBody']) ?: $template->getInboxBody());
        }

        if (array_key_exists('emailEnabled', $payload)) {
            $template->setEmailEnabled((bool) $payload['emailEnabled']);
        }
        if (array_key_exists('emailTitle', $payload) && is_string($payload['emailTitle'])) {
            $template->setEmailTitle(trim($payload['emailTitle']));
        }
        if (array_key_exists('emailBody', $payload) && is_string($payload['emailBody'])) {
            $template->setEmailBody(trim($payload['emailBody']));
        }

        if (array_key_exists('pushEnabled', $payload)) {
            $template->setPushEnabled((bool) $payload['pushEnabled']);
        }
        if (array_key_exists('pushTitle', $payload) && is_string($payload['pushTitle'])) {
            $template->setPushTitle(trim($payload['pushTitle']));
        }
        if (array_key_exists('pushBody', $payload) && is_string($payload['pushBody'])) {
            $template->setPushBody(trim($payload['pushBody']));
        }

        $this->templateRepository->save($template, true);

        return $template;
    }

    /** @param array<int, array{id:string,email:string}> $recipients */
    public function createRequestAccessNotifications(array $recipients, array $requester): int
    {
        $template = $this->getOrCreateRequestAccessTemplate();
        if (!$template->isInboxEnabled()) {
            return 0;
        }

        $created = 0;
        foreach ($recipients as $recipient) {
            if (!isset($recipient['id'], $recipient['email'])) {
                continue;
            }

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
            $this->inboxRepository->getEntityManager()->flush();
        }

        return $created;
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
