<?php

namespace App\Service;

use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use Psr\Log\LoggerInterface;

class MonitoringService
{
    private AccountRepository $accountRepository;
    private TransactionRepository $transactionRepository;
    private CacheService $cacheService;
    private LoggerInterface $logger;

    public function __construct(
        AccountRepository $accountRepository,
        TransactionRepository $transactionRepository,
        CacheService $cacheService,
        LoggerInterface $logger
    ) {
        $this->accountRepository = $accountRepository;
        $this->transactionRepository = $transactionRepository;
        $this->cacheService = $cacheService;
        $this->logger = $logger;
    }

    /**
     * Get system health metrics
     */
    public function getSystemHealth(): array
    {
        $startTime = microtime(true);

        try {
            $metrics = [
                'timestamp' => (new \DateTime())->format('c'),
                'status' => 'healthy',
                'services' => [],
                'metrics' => []
            ];

            // Database health
            $dbHealth = $this->checkDatabaseHealth();
            $metrics['services']['database'] = $dbHealth;

            // Redis health
            $redisHealth = $this->checkRedisHealth();
            $metrics['services']['redis'] = $redisHealth;

            // Application metrics
            $appMetrics = $this->getApplicationMetrics();
            $metrics['metrics'] = $appMetrics;

            // Overall status
            $allHealthy = $dbHealth['status'] === 'healthy' && $redisHealth['status'] === 'healthy';
            $metrics['status'] = $allHealthy ? 'healthy' : 'degraded';

            $processingTime = (microtime(true) - $startTime) * 1000;
            $metrics['processing_time_ms'] = round($processingTime, 2);

            return $metrics;

        } catch (\Throwable $e) {
            $this->logger->error('Health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'timestamp' => (new \DateTime())->format('c'),
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];
        }
    }

    /**
     * Check database connectivity and performance
     */
    private function checkDatabaseHealth(): array
    {
        try {
            $startTime = microtime(true);
            
            // Simple query to check connectivity
            $accountCount = $this->accountRepository->createQueryBuilder('a')
                ->select('COUNT(a.id)')
                ->getQuery()
                ->getSingleScalarResult();

            $responseTime = (microtime(true) - $startTime) * 1000;

            return [
                'status' => 'healthy',
                'response_time_ms' => round($responseTime, 2),
                'account_count' => (int) $accountCount
            ];

        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check Redis connectivity and performance
     */
    private function checkRedisHealth(): array
    {
        try {
            $startTime = microtime(true);
            
            $isHealthy = $this->cacheService->isHealthy();
            $responseTime = (microtime(true) - $startTime) * 1000;

            $result = [
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'response_time_ms' => round($responseTime, 2)
            ];

            if ($isHealthy) {
                $stats = $this->cacheService->getStats();
                $result['stats'] = $stats;
            }

            return $result;

        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get application-specific metrics
     */
    private function getApplicationMetrics(): array
    {
        try {
            $today = new \DateTime('today');
            $yesterday = new \DateTime('yesterday');

            // Today's transaction volume
            $todayVolume = $this->transactionRepository->getDailyVolume($today);
            
            // Yesterday's volume for comparison
            $yesterdayVolume = $this->transactionRepository->getDailyVolume($yesterday);

            // Total system balance
            $totalBalance = $this->accountRepository->getTotalActiveBalance();

            // Pending transactions
            $pendingCount = $this->transactionRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->andWhere('t.status = :status')
                ->setParameter('status', 'pending')
                ->getQuery()
                ->getSingleScalarResult();

            return [
                'transactions' => [
                    'today' => [
                        'count' => (int) $todayVolume['transactionCount'],
                        'volume' => $todayVolume['completedVolume'] ?? '0.00',
                        'total_volume' => $todayVolume['totalVolume'] ?? '0.00'
                    ],
                    'yesterday' => [
                        'count' => (int) $yesterdayVolume['transactionCount'],
                        'volume' => $yesterdayVolume['completedVolume'] ?? '0.00'
                    ],
                    'pending_count' => (int) $pendingCount
                ],
                'accounts' => [
                    'total_balance' => $totalBalance
                ],
                'system' => [
                    'memory_usage' => memory_get_usage(true),
                    'memory_peak' => memory_get_peak_usage(true),
                    'load_average' => sys_getloadavg()[0] ?? 0
                ]
            ];

        } catch (\Throwable $e) {
            $this->logger->error('Failed to get application metrics', [
                'error' => $e->getMessage()
            ]);

            return [
                'error' => 'Failed to retrieve metrics'
            ];
        }
    }

    /**
     * Log performance metrics
     */
    public function logPerformanceMetric(string $operation, float $duration, array $context = []): void
    {
        $this->logger->info('Performance metric', [
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'timestamp' => (new \DateTime())->format('c'),
            'context' => $context
        ], ['performance']);
    }

    /**
     * Log security event
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        $this->logger->warning('Security event', [
            'event' => $event,
            'timestamp' => (new \DateTime())->format('c'),
            'context' => $context
        ], ['security']);
    }

    /**
     * Get transaction statistics for monitoring dashboard
     */
    public function getTransactionStatistics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        try {
            $stats = $this->transactionRepository->getTransactionStats($startDate, $endDate);
            
            $formattedStats = [
                'period' => [
                    'start' => $startDate->format('Y-m-d H:i:s'),
                    'end' => $endDate->format('Y-m-d H:i:s')
                ],
                'summary' => [
                    'total_count' => 0,
                    'total_volume' => '0.00',
                    'success_rate' => 0.0
                ],
                'by_status' => []
            ];

            $totalCount = 0;
            $totalVolume = '0.00';
            $completedCount = 0;

            foreach ($stats as $stat) {
                $count = (int) $stat['count'];
                $amount = $stat['totalAmount'] ?? '0.00';

                $formattedStats['by_status'][$stat['status']] = [
                    'count' => $count,
                    'total_amount' => $amount
                ];

                $totalCount += $count;
                $totalVolume = bcadd($totalVolume, $amount, 2);

                if ($stat['status'] === 'completed') {
                    $completedCount = $count;
                }
            }

            $formattedStats['summary']['total_count'] = $totalCount;
            $formattedStats['summary']['total_volume'] = $totalVolume;
            $formattedStats['summary']['success_rate'] = $totalCount > 0 
                ? round(($completedCount / $totalCount) * 100, 2) 
                : 0.0;

            return $formattedStats;

        } catch (\Throwable $e) {
            $this->logger->error('Failed to get transaction statistics', [
                'error' => $e->getMessage(),
                'start_date' => $startDate->format('Y-m-d H:i:s'),
                'end_date' => $endDate->format('Y-m-d H:i:s')
            ]);

            throw $e;
        }
    }

    /**
     * Alert if system metrics exceed thresholds
     */
    public function checkAlerts(): array
    {
        $alerts = [];

        try {
            // Check pending transactions
            $pendingCount = $this->transactionRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->andWhere('t.status = :status')
                ->andWhere('t.createdAt < :threshold')
                ->setParameter('status', 'pending')
                ->setParameter('threshold', new \DateTimeImmutable('-5 minutes'))
                ->getQuery()
                ->getSingleScalarResult();

            if ($pendingCount > 10) {
                $alerts[] = [
                    'type' => 'high_pending_transactions',
                    'severity' => 'warning',
                    'message' => "High number of pending transactions: {$pendingCount}",
                    'count' => (int) $pendingCount
                ];
            }

            // Check memory usage
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = ini_get('memory_limit');
            $memoryLimitBytes = $this->parseBytes($memoryLimit);
            
            if ($memoryLimitBytes > 0 && ($memoryUsage / $memoryLimitBytes) > 0.8) {
                $alerts[] = [
                    'type' => 'high_memory_usage',
                    'severity' => 'critical',
                    'message' => 'Memory usage exceeds 80% of limit',
                    'usage' => $memoryUsage,
                    'limit' => $memoryLimitBytes,
                    'percentage' => round(($memoryUsage / $memoryLimitBytes) * 100, 2)
                ];
            }

            // Check error rate
            $errorCount = $this->transactionRepository->createQueryBuilder('t')
                ->select('COUNT(t.id)')
                ->andWhere('t.status = :status')
                ->andWhere('t.createdAt > :threshold')
                ->setParameter('status', 'failed')
                ->setParameter('threshold', new \DateTimeImmutable('-1 hour'))
                ->getQuery()
                ->getSingleScalarResult();

            if ($errorCount > 50) {
                $alerts[] = [
                    'type' => 'high_error_rate',
                    'severity' => 'critical',
                    'message' => "High error rate: {$errorCount} failed transactions in the last hour",
                    'count' => (int) $errorCount
                ];
            }

        } catch (\Throwable $e) {
            $this->logger->error('Alert checking failed', [
                'error' => $e->getMessage()
            ]);

            $alerts[] = [
                'type' => 'monitoring_failure',
                'severity' => 'critical',
                'message' => 'Failed to check system alerts: ' . $e->getMessage()
            ];
        }

        return $alerts;
    }

    private function parseBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int) $val;
        
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }
}
