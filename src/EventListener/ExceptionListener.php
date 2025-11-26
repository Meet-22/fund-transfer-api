<?php

namespace App\EventListener;

use App\Exception\TransferException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class ExceptionListener
{
    private LoggerInterface $logger;
    private bool $debug;

    public function __construct(LoggerInterface $logger, bool $debug = false)
    {
        $this->logger = $logger;
        $this->debug = $debug;
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle API requests
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $response = $this->createApiErrorResponse($exception);
        $event->setResponse($response);
    }

    private function createApiErrorResponse(\Throwable $exception): JsonResponse
    {
        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        $message = 'An error occurred';
        $details = [];

        // Handle different types of exceptions
        if ($exception instanceof HttpException) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage();
        } elseif ($exception instanceof TransferException) {
            $statusCode = Response::HTTP_BAD_REQUEST;
            $message = $exception->getMessage();
        } elseif ($exception instanceof ValidationFailedException) {
            $statusCode = Response::HTTP_BAD_REQUEST;
            $message = 'Validation failed';
            $details = $this->formatValidationErrors($exception);
        } elseif ($exception instanceof \InvalidArgumentException) {
            $statusCode = Response::HTTP_BAD_REQUEST;
            $message = $exception->getMessage();
        }

        // Log the error
        $this->logger->error('API Error', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'status_code' => $statusCode
        ]);

        $responseData = [
            'success' => false,
            'message' => $message,
            'timestamp' => (new \DateTime())->format('c'),
            'status_code' => $statusCode
        ];

        if (!empty($details)) {
            $responseData['details'] = $details;
        }

        // Include stack trace in debug mode
        if ($this->debug && $statusCode >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            $responseData['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }

        return new JsonResponse($responseData, $statusCode);
    }

    private function formatValidationErrors(ValidationFailedException $exception): array
    {
        $errors = [];
        $violations = $exception->getViolations();

        foreach ($violations as $violation) {
            $errors[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
                'invalid_value' => $violation->getInvalidValue()
            ];
        }

        return $errors;
    }
}
