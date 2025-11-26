<?php

namespace App\Repository;

use App\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 *
 * @method Account|null find($id, $lockMode = null, $version = null)
 * @method Account|null findOneBy(array $criteria, array $orderBy = null)
 * @method Account[]    findAll()
 * @method Account[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    /**
     * Find account by account number with read lock for fund transfer
     */
    public function findByAccountNumberForUpdate(string $accountNumber): ?Account
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.accountNumber = :accountNumber')
            ->setParameter('accountNumber', $accountNumber)
            ->getQuery()
            ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    /**
     * Find active accounts by account numbers
     */
    public function findActiveAccountsByNumbers(array $accountNumbers): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.accountNumber IN (:accountNumbers)')
            ->andWhere('a.status = :status')
            ->setParameter('accountNumbers', $accountNumbers)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get accounts with low balance
     */
    public function findAccountsWithLowBalance(string $threshold): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.balance < :threshold')
            ->andWhere('a.status = :status')
            ->setParameter('threshold', $threshold)
            ->setParameter('status', 'active')
            ->orderBy('a.balance', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total balance across all active accounts
     */
    public function getTotalActiveBalance(): string
    {
        $result = $this->createQueryBuilder('a')
            ->select('SUM(a.balance) as total')
            ->andWhere('a.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }

    /**
     * Find accounts by holder name (search functionality)
     */
    public function findByHolderName(string $holderName, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.holderName LIKE :holderName')
            ->setParameter('holderName', '%' . $holderName . '%')
            ->orderBy('a.holderName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
