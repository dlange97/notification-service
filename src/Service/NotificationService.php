<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\InboxNotification;
use App\Entity\NotificationTemplate;
use App\Repository\InboxNotificationRepository;
use App\Repository\NotificationTemplateRepository;
use App\Service\TemplateSerialization\NotificationTemplateChannelSerializerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class NotificationService
{
    private const RESOURCE_SHARED_KEY_MAP = [
        'note' => 'resource-shared-note',
        'todo' => 'resource-shared-todo',
        'shopping-list' => 'resource-shared-shopping-list',
        'event' => 'resource-shared-event',
    ];

    /**
     * @param iterable<NotificationTemplateChannelSerializerInterface> $templateChannelSerializers
     */
    public function __construct(
        private readonly NotificationTemplateRepository $templateRepository,
        private readonly InboxNotificationRepository $inboxRepository,
        private readonly RequestAccessTemplateUpdater $requestAccessTemplateUpdater,
        private readonly iterable $templateChannelSerializers,
        private readonly NotificationTransportService $notificationTransport,
    ) {
    }

    /** @return list<string> */
    public function getSupportedTemplateKeys(): array
    {
        return array_keys($this->templateDefaults());
    }

    /** @return list<array<string, mixed>> */
    public function getAllTemplates(): array
    {
        $items = [];
        foreach ($this->getSupportedTemplateKeys() as $key) {
            $items[] = $this->serializeTemplate($this->getOrCreateTemplate($key));
        }

        return $items;
    }

    public function getTemplate(string $templateKey): NotificationTemplate
    {
        return $this->getOrCreateTemplate($templateKey);
    }

    /** @param array<string, mixed> $payload */
    public function updateTemplate(string $templateKey, array $payload): NotificationTemplate
    {
        $template = $this->getOrCreateTemplate($templateKey);

        $this->requestAccessTemplateUpdater->update($template, $payload);

        $this->templateRepository->save($template, true);

        return $template;
    }

    public function getOrCreateRequestAccessTemplate(): NotificationTemplate
    {
        return $this->getOrCreateTemplate(NotificationTemplate::REQUEST_ACCESS_KEY);
    }

    public function getResourceSharedTemplateKeyForType(string $resourceType): ?string
    {
        return self::RESOURCE_SHARED_KEY_MAP[$resourceType] ?? null;
    }

    public function getOrCreateTemplate(string $templateKey): NotificationTemplate
    {
        $defaults = $this->templateDefaults()[$templateKey] ?? null;
        if ($defaults === null) {
            throw new \InvalidArgumentException(sprintf('Unsupported notification template key "%s".', $templateKey));
        }

        $template = $this->templateRepository->findByKey($templateKey);
        if ($template) {
            return $template;
        }

        $template = new NotificationTemplate();
        $template
            ->setTemplateKey($templateKey)
            ->setInboxEnabled((bool) $defaults['inbox']['enabled'])
            ->setInboxTitle((string) $defaults['inbox']['title'])
            ->setInboxBody((string) $defaults['inbox']['body'])
            ->setEmailEnabled((bool) $defaults['email']['enabled'])
            ->setEmailTitle((string) $defaults['email']['title'])
            ->setEmailBody((string) $defaults['email']['body'])
            ->setPushEnabled((bool) $defaults['push']['enabled'])
            ->setPushTitle((string) $defaults['push']['title'])
            ->setPushBody((string) $defaults['push']['body']);

        try {
            $this->templateRepository->save($template, true);
        } catch (UniqueConstraintViolationException) {
            $template = $this->templateRepository->findByKey($templateKey);
            if ($template === null) {
                throw new \RuntimeException(sprintf('Failed to get or create notification template "%s".', $templateKey));
            }
        }

        return $template;
    }

    /** @param array<string, mixed> $payload */
    public function updateRequestAccessTemplate(array $payload): NotificationTemplate
    {
        return $this->updateTemplate(NotificationTemplate::REQUEST_ACCESS_KEY, $payload);
    }

    /**
     * @param array<int, array{id:string,email:string}> $recipients
     * @param array<string, mixed> $requester
     */
    public function createRequestAccessNotifications(array $recipients, array $requester): int
    {
        $template = $this->getOrCreateRequestAccessTemplate();

        $created = 0;
        foreach ($recipients as $recipient) {
            $recipientUserId = (string) $recipient['id'];
            $recipientEmail = (string) $recipient['email'];
            $payload = [
                'requester' => $requester,
                'requestedAt' => (new \DateTimeImmutable())->format('c'),
            ];

            if ($template->isInboxEnabled()) {
                $notification = new InboxNotification();
                $notification
                    ->setRecipientUserId($recipientUserId)
                    ->setRecipientEmail($recipientEmail)
                    ->setType(NotificationTemplate::REQUEST_ACCESS_KEY)
                    ->setTitle($this->renderTemplate($template->getInboxTitle(), $requester))
                    ->setBody($this->renderTemplate($template->getInboxBody(), $requester))
                    ->setPayload($payload);

                $this->inboxRepository->save($notification);
                ++$created;
            }

            $this->dispatchConfiguredChannels($template, $recipientUserId, $recipientEmail, $requester, $payload);
        }

        if ($created > 0) {
            $this->inboxRepository->flush();
        }

        return $created;
    }

    /** @param array<string, mixed> $payload */
    public function createResourceSharedNotification(array $payload): bool
    {
        $resourceType = trim((string) ($payload['resourceType'] ?? ''));
        $templateKey = $this->getResourceSharedTemplateKeyForType($resourceType);
        if ($templateKey === null) {
            return false;
        }

        $recipientUserId = trim((string) ($payload['recipientUserId'] ?? ''));
        if ($recipientUserId === '') {
            return false;
        }

        $template = $this->getOrCreateTemplate($templateKey);

        $resourceName = trim((string) ($payload['resourceName'] ?? '')); 
        $sharedBy = is_array($payload['sharedBy'] ?? null) ? $payload['sharedBy'] : [];
        $context = [
            'resourceType' => $resourceType,
            'resourceName' => $resourceName,
            'sharedByUserId' => (string) ($sharedBy['userId'] ?? ''),
        ];
        $notificationPayload = [
            'resourceType' => $resourceType,
            'resourceName' => $resourceName,
            'sharedBy' => $sharedBy,
            'sharedAt' => (new \DateTimeImmutable())->format('c'),
        ];

        $recipientEmail = (string) ($payload['recipientEmail'] ?? '');

        if ($template->isInboxEnabled()) {
            $notification = new InboxNotification();
            $notification
                ->setRecipientUserId($recipientUserId)
                ->setRecipientEmail($recipientEmail)
                ->setType($templateKey)
                ->setTitle($this->renderTemplate($template->getInboxTitle(), $context))
                ->setBody($this->renderTemplate($template->getInboxBody(), $context))
                ->setPayload($notificationPayload);

            $this->inboxRepository->save($notification, true);
        }

        $this->dispatchConfiguredChannels($template, $recipientUserId, $recipientEmail, $context, $notificationPayload);

        return true;
    }

    /** @param array<string, mixed> $payload */
    public function createUserInvitedNotification(array $payload): bool
    {
        $recipientUserId = trim((string) ($payload['recipientUserId'] ?? ''));
        $recipientEmail = trim((string) ($payload['recipientEmail'] ?? ''));
        if ($recipientUserId === '' || $recipientEmail === '') {
            return false;
        }

        $template = $this->getOrCreateTemplate('user-invited');
        $invitedBy = is_array($payload['invitedBy'] ?? null) ? $payload['invitedBy'] : [];
        $context = [
            'invitedUserEmail' => (string) ($payload['invitedUserEmail'] ?? $recipientEmail),
            'invitedByUserId' => (string) ($invitedBy['userId'] ?? ''),
            'invitedByEmail' => (string) ($invitedBy['email'] ?? ''),
            'invitedByName' => trim((string) (($invitedBy['firstName'] ?? '') . ' ' . ($invitedBy['lastName'] ?? ''))),
            'inviteLink' => (string) ($payload['inviteLink'] ?? ''),
            'inviteReference' => (string) ($payload['inviteReference'] ?? ''),
        ];
        $notificationPayload = [
            'invitedBy' => $invitedBy,
            'invitedUserEmail' => $context['invitedUserEmail'],
            'inviteLink' => $context['inviteLink'],
            'inviteReference' => $context['inviteReference'],
            'invitedAt' => (new \DateTimeImmutable())->format('c'),
        ];

        if ($template->isInboxEnabled()) {
            $notification = new InboxNotification();
            $notification
                ->setRecipientUserId($recipientUserId)
                ->setRecipientEmail($recipientEmail)
                ->setType('user-invited')
                ->setTitle($this->renderTemplate($template->getInboxTitle(), $context))
                ->setBody($this->renderTemplate($template->getInboxBody(), $context))
                ->setPayload($notificationPayload);

            $this->inboxRepository->save($notification, true);
        }

        $this->dispatchConfiguredChannels($template, $recipientUserId, $recipientEmail, $context, $notificationPayload);

        return true;
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
        $channels = [];
        foreach ($this->templateChannelSerializers as $serializer) {
            $channels[$serializer->channel()] = $serializer->serialize($template);
        }

        return [
            'key' => $template->getTemplateKey(),
            'channels' => $channels,
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
            '{{resourceType}}' => (string) ($requester['resourceType'] ?? ''),
            '{{resourceName}}' => (string) ($requester['resourceName'] ?? ''),
            '{{sharedByUserId}}' => (string) ($requester['sharedByUserId'] ?? ''),
            '{{invitedUserEmail}}' => (string) ($requester['invitedUserEmail'] ?? ''),
            '{{invitedByUserId}}' => (string) ($requester['invitedByUserId'] ?? ''),
            '{{invitedByEmail}}' => (string) ($requester['invitedByEmail'] ?? ''),
            '{{invitedByName}}' => (string) ($requester['invitedByName'] ?? ''),
            '{{inviteLink}}' => (string) ($requester['inviteLink'] ?? ''),
            '{{inviteReference}}' => (string) ($requester['inviteReference'] ?? ''),
        ];

        return strtr($template, $map);
    }

    /**
     * @return array<string, array<string, array{enabled: bool, title: string, body: string}>>
     */
    private function templateDefaults(): array
    {
        return [
            NotificationTemplate::REQUEST_ACCESS_KEY => [
                'inbox' => [
                    'enabled' => true,
                    'title' => 'Nowy wniosek o dostęp do aplikacji',
                    'body' => "Użytkownik {{email}} poprosił o dostęp do aplikacji.\nImię i nazwisko: {{firstName}} {{lastName}}\nWiadomość: {{message}}",
                ],
                'email' => [
                    'enabled' => false,
                    'title' => 'Request access to My Dashboard',
                    'body' => "User {{email}} requested access.\nName: {{firstName}} {{lastName}}\nMessage: {{message}}",
                ],
                'push' => [
                    'enabled' => false,
                    'title' => 'Nowy request access',
                    'body' => 'Użytkownik {{email}} poprosił o dostęp.',
                ],
            ],
            'resource-shared-note' => [
                'inbox' => [
                    'enabled' => true,
                    'title' => 'Udostępniono notatkę: {{resourceName}}',
                    'body' => 'Użytkownik {{sharedByUserId}} udostępnił Ci notatkę {{resourceName}}.',
                ],
                'email' => [
                    'enabled' => false,
                    'title' => 'A note was shared with you',
                    'body' => 'User {{sharedByUserId}} shared note {{resourceName}} with you.',
                ],
                'push' => [
                    'enabled' => true,
                    'title' => 'Nowa współdzielona notatka',
                    'body' => '{{resourceName}}',
                ],
            ],
            'resource-shared-todo' => [
                'inbox' => [
                    'enabled' => true,
                    'title' => 'Udostępniono zadanie: {{resourceName}}',
                    'body' => 'Użytkownik {{sharedByUserId}} udostępnił Ci zadanie {{resourceName}}.',
                ],
                'email' => [
                    'enabled' => false,
                    'title' => 'A todo item was shared with you',
                    'body' => 'User {{sharedByUserId}} shared todo {{resourceName}} with you.',
                ],
                'push' => [
                    'enabled' => true,
                    'title' => 'Nowe współdzielone zadanie',
                    'body' => '{{resourceName}}',
                ],
            ],
            'resource-shared-shopping-list' => [
                'inbox' => [
                    'enabled' => true,
                    'title' => 'Udostępniono listę zakupów: {{resourceName}}',
                    'body' => 'Użytkownik {{sharedByUserId}} udostępnił Ci listę zakupów {{resourceName}}.',
                ],
                'email' => [
                    'enabled' => false,
                    'title' => 'A shopping list was shared with you',
                    'body' => 'User {{sharedByUserId}} shared shopping list {{resourceName}} with you.',
                ],
                'push' => [
                    'enabled' => true,
                    'title' => 'Nowa współdzielona lista zakupów',
                    'body' => '{{resourceName}}',
                ],
            ],
            'resource-shared-event' => [
                'inbox' => [
                    'enabled' => true,
                    'title' => 'Udostępniono wydarzenie: {{resourceName}}',
                    'body' => 'Użytkownik {{sharedByUserId}} udostępnił Ci wydarzenie {{resourceName}}.',
                ],
                'email' => [
                    'enabled' => false,
                    'title' => 'An event was shared with you',
                    'body' => 'User {{sharedByUserId}} shared event {{resourceName}} with you.',
                ],
                'push' => [
                    'enabled' => true,
                    'title' => 'Nowe współdzielone wydarzenie',
                    'body' => '{{resourceName}}',
                ],
            ],
            'user-invited' => [
                'inbox' => [
                    'enabled' => true,
                    'title' => 'Zaproszenie do aplikacji',
                    'body' => 'Konto {{invitedUserEmail}} zostało utworzone. Ustaw hasło, aby się zalogować: {{inviteLink}}',
                ],
                'email' => [
                    'enabled' => true,
                    'title' => 'You were invited to My Dashboard',
                    'body' => 'Your account {{invitedUserEmail}} was created by {{invitedByName}} ({{invitedByEmail}}). Set your password to sign in: {{inviteLink}}',
                ],
                'push' => [
                    'enabled' => false,
                    'title' => 'Invitation sent',
                    'body' => 'Your account is ready. Set your password to sign in.',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $payload
     */
    private function dispatchConfiguredChannels(
        NotificationTemplate $template,
        string $recipientUserId,
        string $recipientEmail,
        array $context,
        array $payload,
    ): void {
        if ($template->isEmailEnabled() && $recipientEmail !== '') {
            $this->notificationTransport->sendEmail(
                $recipientEmail,
                $this->renderTemplate((string) ($template->getEmailTitle() ?? ''), $context),
                $this->renderTemplate((string) ($template->getEmailBody() ?? ''), $context),
                $payload,
            );
        }

        if ($template->isPushEnabled() && $recipientUserId !== '') {
            $this->notificationTransport->sendPush(
                $recipientUserId,
                $this->renderTemplate((string) ($template->getPushTitle() ?? ''), $context),
                $this->renderTemplate((string) ($template->getPushBody() ?? ''), $context),
                $payload,
            );
        }
    }
}
