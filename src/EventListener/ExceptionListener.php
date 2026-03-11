<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Catches all exceptions and returns appropriate HTTP responses.
 * - 400 for validation errors
 * - 400 for client errors (bad input)
 * - 500 for unexpected errors
 */
readonly class ExceptionListener
{
    public function __construct(private LoggerInterface $logger) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Log the error
        if ($exception instanceof HttpExceptionInterface) {
            $level = 'info';
        } else {
            $level = 'error';
        }

        $this->logger->log($level, 'Exception caught: ' . $exception->getMessage(), [
            'exception' => $exception::class,
            'code'      => $exception->getCode(),
        ]);

        // Build response
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $data       = ['error' => 'Internal server error'];

        if ($exception instanceof ValidationFailedException) {
            $statusCode = Response::HTTP_BAD_REQUEST;
            $messages   = [];
            foreach ($exception->getViolations() as $v) {
                $messages[] = $v->getPropertyPath() . ': ' . $v->getMessage();
            }
            $data = ['error' => 'Validation error', 'details' => $messages];
        } elseif ($exception instanceof \InvalidArgumentException) {
            $statusCode = Response::HTTP_BAD_REQUEST;
            $data       = ['error' => $exception->getMessage()];
        } elseif ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $data       = ['error' => $exception->getMessage()];
        }

        $response = new JsonResponse($data, $statusCode);
        $event->setResponse($response);
    }
}
