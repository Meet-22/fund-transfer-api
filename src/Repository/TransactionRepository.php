<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 *
 * @method Transaction|null find($id, $lockMode = null, $version = null)
 * @method Transaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method Transaction[]    findAll()
 * @method Transaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * Find transactions by account (both incoming and outgoing)
     */
    public function findByAccount(Account $account, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.fromAccount', 'fa')
            ->leftJoin('t.toAccount', 'ta')
            ->andWhere('t.fromAccount = :account OR t.toAccount = :account')
            ->setParameter('account', $account)
            ->orderBy('t.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find transactions by status
     */
    public function findByStatus(string $status, int $limit = 100): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', $status)
            ->orderBy('t.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pending transactions older than specified minutes
     */
    public function findPendingTransactionsOlderThan(int $minutes): array
    {
        $dateThreshold = new \DateTimeImmutable("-{$minutes} minutes");

        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->andWhere('t.createdAt < :threshold')
            ->setParameter('status', Transaction::STATUS_PENDING)
            ->setParameter('threshold', $dateThreshold)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get transaction statistics for a date range
     */
    public function getTransactionStats(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select(
                't.status',
                'COUNT(t.id) as count',
                'SUM(t.amount) as totalAmount'
            )
            ->andWhere('t.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('t.status');

        return $qb->getQuery()->getResult();
    }

    /**
     * Get daily transaction volume
     */
    public function getDailyVolume(\DateTimeInterface $date): array
    {
        $startOfDay = $date->format('Y-m-d 00:00:00');
        $endOfDay = $date->format('Y-m-d 23:59:59');

        return $this->createQueryBuilder('t')
            ->select(
                'COUNT(t.id) as transactionCount',
                'SUM(CASE WHEN t.status = :completedStatus THEN t.amount ELSE 0 END) as completedVolume',
                'SUM(t.amount) as totalVolume'
            )
            ->andWhere('t.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startOfDay)
            ->setParameter('endDate', $endOfDay)
            ->setParameter('completedStatus', Transaction::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Find duplicate transaction attempts
     */
    public function findDuplicateTransactions(Account $fromAccount, Account $toAccount, string $amount, \DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.fromAccount = :fromAccount')
            ->andWhere('t.toAccount = :toAccount')
            ->andWhere('t.amount = :amount')
            ->andWhere('t.createdAt >= :since')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('fromAccount', $fromAccount)
            ->setParameter('toAccount', $toAccount)
            ->setParameter('amount', $amount)
            ->setParameter('since', $since)
            ->setParameter('statuses', [Transaction::STATUS_PENDING, Transaction::STATUS_PROCESSING, Transaction::STATUS_COMPLETED])
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recent transactions for an account (for caching)
     */
    public function getRecentTransactionsByAccount(string $accountNumber, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.fromAccount', 'fa')
            ->leftJoin('t.toAccount', 'ta')
            ->andWhere('fa.accountNumber = :accountNumber OR ta.accountNumber = :accountNumber')
            ->setParameter('accountNumber', $accountNumber)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
