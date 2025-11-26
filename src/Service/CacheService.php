<?php

namespace App\Service;

use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;

class CacheService
{
    private RedisClient $redis;
    private LoggerInterface $logger;
    private string $cachePrefix;

    public function __construct(LoggerInterface $logger, string $redisUrl, string $cachePrefix = 'fund_transfer_')
    {
        $this->redis = new RedisClient($redisUrl);
        $this->logger = $logger;
        $this->cachePrefix = $cachePrefix;
    }

    /**
     * Get account balance from cache
     */
    public function getAccountBalance(string $accountNumber): ?string
    {
        try {
            $key = $this->getCacheKey('account_balance', $accountNumber);
            $balance = $this->redis->get($key);
            
            if ($balance !== null) {
                $this->logger->debug('Cache hit for account balance', ['account' => $accountNumber]);
                return $balance;
            }
            
            $this->logger->debug('Cache miss for account balance', ['account' => $accountNumber]);
            return null;
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to get account balance from cache', [
                'account' => $accountNumber,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Set account balance in cache
     */
    public function setAccountBalance(string $accountNumber, string $balance, int $ttl = 300): void
    {
        try {
            $key = $this->getCacheKey('account_balance', $accountNumber);
            $this->redis->setex($key, $ttl, $balance);
            
            $this->logger->debug('Cached account balance', [
                'account' => $accountNumber,
                'balance' => $balance,
                'ttl' => $ttl
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to cache account balance', [
                'account' => $accountNumber,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear account cache
     */
    public function clearAccountCache(string $accountNumber): void
    {
        try {
            $pattern = $this->getCacheKey('account_*', $accountNumber);
            $keys = $this->redis->keys($pattern);
            
            if (!empty($keys)) {
                $this->redis->del($keys);
                $this->logger->debug('Cleared account cache', [
                    'account' => $accountNumber,
                    'keys_cleared' => count($keys)
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to clear account cache', [
                'account' => $accountNumber,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get recent transactions from cache
     */
    public function getRecentTransactions(string $accountNumber, int $limit = 10): ?array
    {
        try {
            $key = $this->getCacheKey('recent_transactions', $accountNumber);
            $transactions = $this->redis->get($key);
            
            if ($transactions !== null) {
                $this->logger->debug('Cache hit for recent transactions', ['account' => $accountNumber]);
                return json_decode($transactions, true);
            }
            
            return null;
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to get recent transactions from cache', [
                'account' => $accountNumber,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Set recent transactions in cache
     */
    public function setRecentTransactions(string $accountNumber, array $transactions, int $ttl = 180): void
    {
        try {
            $key = $this->getCacheKey('recent_transactions', $accountNumber);
            $this->redis->setex($key, $ttl, json_encode($transactions));
            
            $this->logger->debug('Cached recent transactions', [
                'account' => $accountNumber,
                'count' => count($transactions),
                'ttl' => $ttl
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to cache recent transactions', [
                'account' => $accountNumber,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Rate limiting functionality
     */
    public function isRateLimited(string $identifier, int $limit = 100, int $window = 60): bool
    {
        try {
            $key = $this->getCacheKey('rate_limit', $identifier);
            $current = $this->redis->incr($key);
            
            if ($current === 1) {
                $this->redis->expire($key, $window);
            }
            
            $isLimited = $current > $limit;
            
            if ($isLimited) {
                $this->logger->warning('Rate limit exceeded', [
                    'identifier' => $identifier,
                    'current' => $current,
                    'limit' => $limit
                ]);
            }
            
            return $isLimited;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to check rate limit', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            // Fail open - don't block requests if Redis is down
            return false;
        }
    }

    /**
     * Lock functionality for preventing concurrent operations
     */
    public function acquireLock(string $resource, int $ttl = 30): bool
    {
        try {
            $key = $this->getCacheKey('lock', $resource);
            $result = $this->redis->set($key, '1', 'EX', $ttl, 'NX');
            
            $acquired = $result === 'OK';
            
            if ($acquired) {
                $this->logger->debug('Lock acquired', ['resource' => $resource, 'ttl' => $ttl]);
            } else {
                $this->logger->debug('Lock acquisition failed', ['resource' => $resource]);
            }
            
            return $acquired;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to acquire lock', [
                'resource' => $resource,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Release lock
     */
    public function releaseLock(string $resource): void
    {
        try {
            $key = $this->getCacheKey('lock', $resource);
            $this->redis->del([$key]);
            
            $this->logger->debug('Lock released', ['resource' => $resource]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to release lock', [
                'resource' => $resource,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate cache key
     */
    private function getCacheKey(string $type, string $identifier): string
    {
        return $this->cachePrefix . $type . ':' . $identifier;
    }

    /**
     * Health check for Redis connection
     */
    public function isHealthy(): bool
    {
        try {
            $this->redis->ping();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Redis health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        try {
            $info = $this->redis->info();
            return [
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? '0B',
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get Redis stats', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
