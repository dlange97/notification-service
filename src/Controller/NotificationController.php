<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\InboxNotification;
use App\Security\JwtUser;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notification', name: 'notification_')]
class NotificationController extends AbstractController
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    #[Route('/inbox', name: 'inbox', methods: ['GET'])]
    public function inbox(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query->get('limit', 50)));
        $ownerId = $this->getOwnerId();

        return $this->json([
            'items' => $this->notificationService->getInboxForUser($ownerId, $limit),
            'unreadCount' => $this->notificationService->countUnreadForUser($ownerId),
        ]);
    }

    #[Route('/inbox/{id}/read', name: 'mark_read', methods: ['PATCH'])]
    public function markRead(InboxNotification $notification): JsonResponse
    {
        if ($notification->getRecipientUserId() !== $this->getOwnerId()) {
            throw $this->createAccessDeniedException('You do not own this notification.');
        }

        $saved = $this->notificationService->markAsRead($notification);

        return $this->json($this->notificationService->serializeInbox($saved));
    }

    #[Route('/inbox', name: 'inbox_clear', methods: ['DELETE'])]
    public function clearInbox(): JsonResponse
    {
        $this->notificationService->clearInboxForUser($this->getOwnerId());

        return $this->json(null, 204);
    }

    #[Route('/settings/template/request-access', name: 'request_access_template_get', methods: ['GET'])]
    public function getRequestAccessTemplate(): JsonResponse
    {
        $template = $this->notificationService->getOrCreateRequestAccessTemplate();
        return $this->json($this->notificationService->serializeTemplate($template));
    }

    #[Route('/settings/template/request-access', name: 'request_access_template_update', methods: ['PUT'])]
    public function updateRequestAccessTemplate(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $template = $this->notificationService->updateRequestAccessTemplate($payload);

        return $this->json($this->notificationService->serializeTemplate($template));
    }

    #[Route('/inbox', name: 'inbox_create', methods: ['POST'])]
    public function createInboxNotification(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $ownerId = $this->getOwnerId();

        $notification = $this->notificationService->createInboxNotification([
            'recipientUserId' => $ownerId,
            'recipientEmail' => $data['recipientEmail'] ?? 'user@example.com',
            'type' => $data['type'] ?? 'request-access',
            'title' => $data['title'] ?? 'Request access',
            'body' => $data['body'] ?? 'User requested access.',
            'payload' => $data['payload'] ?? null,
        ]);

        return $this->json($this->notificationService->serializeInbox($notification), 201);
    }

    #[Route('/inbox/{id}', name: 'inbox_update', methods: ['PUT'])]
    public function updateInboxNotification(Request $request, InboxNotification $notification): JsonResponse
    {
        if ($notification->getRecipientUserId() !== $this->getOwnerId()) {
            throw $this->createAccessDeniedException('You do not own this notification.');
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $updated = $this->notificationService->updateInboxNotification($notification, $data);

        return $this->json($this->notificationService->serializeInbox($updated));
    }

    #[Route('/inbox/{id}', name: 'inbox_delete', methods: ['DELETE'])]
    public function deleteInboxNotification(InboxNotification $notification): JsonResponse
    {
        if ($notification->getRecipientUserId() !== $this->getOwnerId()) {
            throw $this->createAccessDeniedException('You do not own this notification.');
        }

        $this->notificationService->deleteInboxNotification($notification);

        return $this->json(null, 204);
    }

    private function getOwnerId(): string
    {
        /** @var JwtUser $user */
        $user = $this->getUser();
        return $user->getUserId();
    }
}
