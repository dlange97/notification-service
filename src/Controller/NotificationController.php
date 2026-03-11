<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\InboxNotification;
use App\Security\JwtUser;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications', name: 'api_notifications_')]
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

    private function getOwnerId(): string
    {
        /** @var JwtUser $user */
        $user = $this->getUser();
        return $user->getUserId();
    }
}
