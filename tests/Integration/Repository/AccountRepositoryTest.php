<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Account;
use App\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;

class AccountRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AccountRepository $accountRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
            
        $this->accountRepository = $this->entityManager
            ->getRepository(Account::class);
            
        // Clean database
        $purger = new ORMPurger($this->entityManager);
        $purger->purge();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    public function testFindByAccountNumber(): void
    {
        // Create test account
        $account = new Account();
        $account->setAccountNumber('TEST-123456');
        $account->setHolderName('John Doe');
        $account->setBalance('1000.00');
        $account->setStatus('active');
        
        $this->entityManager->persist($account);
        $this->entityManager->flush();

        // Test finding by account number
        $foundAccount = $this->accountRepository->findOneBy(['accountNumber' => 'TEST-123456']);
        
        $this->assertNotNull($foundAccount);
        $this->assertEquals('TEST-123456', $foundAccount->getAccountNumber());
        $this->assertEquals('John Doe', $foundAccount->getHolderName());
        $this->assertEquals('1000.00', $foundAccount->getBalance());
    }

    public function testFindByAccountNumberNotFound(): void
    {
        $foundAccount = $this->accountRepository->findOneBy(['accountNumber' => 'NON-EXISTENT']);
        $this->assertNull($foundAccount);
    }

    public function testFindActiveAccounts(): void
    {
        // Create active account
        $activeAccount = new Account();
        $activeAccount->setAccountNumber('ACTIVE-123');
        $activeAccount->setHolderName('Active User');
        $activeAccount->setBalance('500.00');
        $activeAccount->setStatus('active');
        
        // Create inactive account
        $inactiveAccount = new Account();
        $inactiveAccount->setAccountNumber('INACTIVE-456');
        $inactiveAccount->setHolderName('Inactive User');
        $inactiveAccount->setBalance('300.00');
        $inactiveAccount->setStatus('inactive');
        
        $this->entityManager->persist($activeAccount);
        $this->entityManager->persist($inactiveAccount);
        $this->entityManager->flush();

        // Test finding active accounts
        $activeAccounts = $this->accountRepository->findBy(['status' => 'active']);
        
        $this->assertCount(1, $activeAccounts);
        $this->assertEquals('ACTIVE-123', $activeAccounts[0]->getAccountNumber());
    }

    public function testPersistAccount(): void
    {
        $account = new Account();
        $account->setAccountNumber('PERSIST-789');
        $account->setHolderName('Test Persist');
        $account->setBalance('750.50');
        $account->setStatus('active');
        
        $this->entityManager->persist($account);
        $this->entityManager->flush();
        
        // Clear entity manager to force database fetch
        $this->entityManager->clear();
        
        $savedAccount = $this->accountRepository->find($account->getId());
        
        $this->assertNotNull($savedAccount);
        $this->assertEquals('PERSIST-789', $savedAccount->getAccountNumber());
        $this->assertEquals('Test Persist', $savedAccount->getHolderName());
        $this->assertEquals('750.50', $savedAccount->getBalance());
        $this->assertEquals('active', $savedAccount->getStatus());
    }
}
