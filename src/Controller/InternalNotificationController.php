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

#[Route('/notification/internal', name: 'notification_internal_')]
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
        if (!$this->isAuthorized($request)) {
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

    #[Route('/resource-shared', name: 'resource_shared', methods: ['POST'])]
    public function resourceShared(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return $this->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $ok = $this->notificationService->createResourceSharedNotification($payload);
        if (!$ok) {
            return $this->json(['error' => 'Invalid payload.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'created' => true,
            'type' => 'resource-shared',
        ], Response::HTTP_CREATED);
    }

    private function isAuthorized(Request $request): bool
    {
        $providedToken = (string) $request->headers->get('X-Internal-Token', '');

        return $providedToken !== '' && hash_equals($this->internalToken, $providedToken);
    }
}
