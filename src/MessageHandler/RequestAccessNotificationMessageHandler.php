<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Service\NotificationService;
use MyDashboard\Shared\Message\RequestAccessNotificationMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'async')]
final class RequestAccessNotificationMessageHandler
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function __invoke(RequestAccessNotificationMessage $message): void
    {
        $this->notificationService->createRequestAccessNotifications(
            $message->getRecipients(),
            $message->getRequester(),
        );
    }
}