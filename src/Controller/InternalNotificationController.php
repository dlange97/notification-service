<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications/internal', name: 'api_notifications_internal_')]
class InternalNotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationService $notificationService,
        #[Autowire('%env(string:INTERNAL_NOTIFICATION_TOKEN)%')]
        private readonly string $internalToken,
    ) {
    }

    #[Route('/request-access', name: 'request_access', methods: ['POST'])]
    public function requestAccess(Request $request): JsonResponse
    {
        $providedToken = (string) $request->headers->get('X-Internal-Token', '');
        if ($providedToken === '' || !hash_equals($this->internalToken, $providedToken)) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $requester = is_array($payload['requester'] ?? null) ? $payload['requester'] : [];
        $recipients = is_array($payload['recipients'] ?? null) ? $payload['recipients'] : [];

        if (($requester['email'] ?? '') === '' || count($recipients) === 0) {
            return $this->json(['error' => 'Invalid payload.'], Response::HTTP_BAD_REQUEST);
        }

        $created = $this->notificationService->createRequestAccessNotifications($recipients, $requester);

        return $this->json([
            'created' => $created,
            'type' => 'request-access',
        ], Response::HTTP_CREATED);
    }
}
