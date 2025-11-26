<?php

namespace App\Controller;

use App\Service\CacheService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/health', name: 'health_')]
class HealthController extends AbstractController
{
    private Connection $connection;
    private CacheService $cacheService;

    public function __construct(Connection $connection, CacheService $cacheService)
    {
        $this->connection = $connection;
        $this->cacheService = $cacheService;
    }

    /**
     * Basic health check
     */
    #[Route('', methods: ['GET'], name: 'check')]
    public function healthCheck(): JsonResponse
    {
        try {
            $health = [
                'status' => 'healthy',
                'timestamp' => (new \DateTime())->format('c'),
                'version' => '1.0.0',
                'environment' => $_ENV['APP_ENV'] ?? 'unknown'
            ];

            return new JsonResponse($health);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => 'unhealthy',
                'timestamp' => (new \DateTime())->format('c'),
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Detailed health check with dependencies
     */
    #[Route('/detailed', methods: ['GET'], name: 'detailed')]
    public function detailedHealthCheck(): JsonResponse
    {
        $checks = [
            'overall' => 'healthy',
            'timestamp' => (new \DateTime())->format('c'),
            'services' => []
        ];

        // Database check
        try {
            $this->connection->executeQuery('SELECT 1');
            $checks['services']['database'] = [
                'status' => 'healthy',
                'response_time' => $this->measureResponseTime(function() {
                    $this->connection->executeQuery('SELECT 1');
                })
            ];
        } catch (\Throwable $e) {
            $checks['services']['database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
            $checks['overall'] = 'degraded';
        }

        // Redis check
        try {
            $redisHealthy = $this->cacheService->isHealthy();
            $checks['services']['redis'] = [
                'status' => $redisHealthy ? 'healthy' : 'unhealthy',
                'response_time' => $this->measureResponseTime(function() {
                    $this->cacheService->isHealthy();
                })
            ];
            
            if (!$redisHealthy) {
                $checks['overall'] = 'degraded';
            }
        } catch (\Throwable $e) {
            $checks['services']['redis'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
            $checks['overall'] = 'degraded';
        }

        // System resources
        $checks['services']['system'] = [
            'status' => 'healthy',
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'disk_space' => disk_free_space('/'),
            'load_average' => sys_getloadavg()
        ];

        $statusCode = $checks['overall'] === 'healthy' ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;
        
        return new JsonResponse($checks, $statusCode);
    }

    /**
     * Database connectivity check
     */
    #[Route('/database', methods: ['GET'], name: 'database')]
    public function databaseCheck(): JsonResponse
    {
        try {
            $startTime = microtime(true);
            $result = $this->connection->executeQuery('SELECT COUNT(*) as count FROM accounts')->fetchAssociative();
            $responseTime = (microtime(true) - $startTime) * 1000;

            return new JsonResponse([
                'status' => 'healthy',
                'response_time_ms' => round($responseTime, 2),
                'account_count' => $result['count'],
                'timestamp' => (new \DateTime())->format('c')
            ]);

        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => (new \DateTime())->format('c')
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    /**
     * Redis connectivity and statistics check
     */
    #[Route('/redis', methods: ['GET'], name: 'redis')]
    public function redisCheck(): JsonResponse
    {
        try {
            $startTime = microtime(true);
            $isHealthy = $this->cacheService->isHealthy();
            $responseTime = (microtime(true) - $startTime) * 1000;

            if ($isHealthy) {
                $stats = $this->cacheService->getStats();
                
                return new JsonResponse([
                    'status' => 'healthy',
                    'response_time_ms' => round($responseTime, 2),
                    'stats' => $stats,
                    'timestamp' => (new \DateTime())->format('c')
                ]);
            } else {
                return new JsonResponse([
                    'status' => 'unhealthy',
                    'response_time_ms' => round($responseTime, 2),
                    'timestamp' => (new \DateTime())->format('c')
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => (new \DateTime())->format('c')
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    private function measureResponseTime(callable $callback): float
    {
        $startTime = microtime(true);
        $callback();
        return round((microtime(true) - $startTime) * 1000, 2);
    }
}
