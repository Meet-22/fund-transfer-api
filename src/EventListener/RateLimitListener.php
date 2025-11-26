<?php

namespace App\EventListener;

use App\Service\CacheService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class RateLimitListener
{
    private CacheService $cacheService;
    private LoggerInterface $logger;

    public function __construct(CacheService $cacheService, LoggerInterface $logger)
    {
        $this->cacheService = $cacheService;
        $this->logger = $logger;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Only apply rate limiting to API routes
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        // Skip health checks
        if (str_starts_with($request->getPathInfo(), '/api/v1/health')) {
            return;
        }

        $clientIp = $request->getClientIp();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // Different limits for different endpoints
        $limits = $this->getRateLimits($path, $method);
        
        foreach ($limits as $limit) {
            $identifier = $limit['identifier'] . ':' . $clientIp;
            
            if ($this->cacheService->isRateLimited($identifier, $limit['requests'], $limit['window'])) {
                $this->logger->warning('Rate limit exceeded', [
                    'client_ip' => $clientIp,
                    'path' => $path,
                    'method' => $method,
                    'limit' => $limit
                ]);

                $response = new JsonResponse([
                    'success' => false,
                    'message' => 'Rate limit exceeded. Too many requests.',
                    'retry_after' => $limit['window'],
                    'timestamp' => (new \DateTime())->format('c')
                ], Response::HTTP_TOO_MANY_REQUESTS);

                $response->headers->set('Retry-After', (string) $limit['window']);
                $response->headers->set('X-RateLimit-Limit', (string) $limit['requests']);
                $response->headers->set('X-RateLimit-Window', (string) $limit['window']);

                $event->setResponse($response);
                return;
            }
        }
    }

    private function getRateLimits(string $path, string $method): array
    {
        $limits = [];

        // Global API rate limit
        $limits[] = [
            'identifier' => 'api_global',
            'requests' => (int) ($_ENV['API_RATE_LIMIT_PER_MINUTE'] ?? 100),
            'window' => 60
        ];

        // Specific limits for transfer endpoints
        if (str_contains($path, '/transfers') && $method === 'POST') {
            $limits[] = [
                'identifier' => 'transfer_create',
                'requests' => 20,
                'window' => 60
            ];
        }

        // Account creation limits
        if (str_contains($path, '/accounts') && $method === 'POST') {
            $limits[] = [
                'identifier' => 'account_create',
                'requests' => 10,
                'window' => 300 // 5 minutes
            ];
        }

        return $limits;
    }
}
