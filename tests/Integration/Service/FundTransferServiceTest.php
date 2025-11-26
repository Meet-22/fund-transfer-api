<?php

namespace App\Tests\Integration\Service;

use App\Entity\Account;
use App\Entity\Transaction;
use App\Exception\InsufficientFundsException;
use App\Exception\InvalidTransferException;
use App\Service\FundTransferService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FundTransferServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private FundTransferService $fundTransferService;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->fundTransferService = $kernel->getContainer()
            ->get(FundTransferService::class);

        // Start transaction for test isolation
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback transaction to keep tests isolated
        $this->entityManager->rollback();
        parent::tearDown();
    }

    public function testSuccessfulTransfer(): void
    {
        // Create test accounts
        $fromAccount = $this->createAccount('FROM001', 'Source Account', '1000.00');
        $toAccount = $this->createAccount('TO001', 'Destination Account', '500.00');

        // Perform transfer
        $transaction = $this->fundTransferService->transferFunds(
            'FROM001',
            'TO001',
            '200.00',
            'Test transfer'
        );

        // Assert transaction was created
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals('200.00', $transaction->getAmount());
        $this->assertEquals(Transaction::STATUS_COMPLETED, $transaction->getStatus());

        // Refresh entities to get updated balances
        $this->entityManager->refresh($fromAccount);
        $this->entityManager->refresh($toAccount);

        // Assert balances were updated
        $this->assertEquals('800.00', $fromAccount->getBalance());
        $this->assertEquals('700.00', $toAccount->getBalance());
    }

    public function testInsufficientFundsTransfer(): void
    {
        // Create test accounts
        $fromAccount = $this->createAccount('FROM002', 'Source Account', '100.00');
        $toAccount = $this->createAccount('TO002', 'Destination Account', '0.00');

        $this->expectException(InsufficientFundsException::class);

        // Attempt transfer with insufficient funds
        $this->fundTransferService->transferFunds(
            'FROM002',
            'TO002',
            '200.00',
            'Insufficient funds test'
        );
    }

    public function testTransferToSameAccount(): void
    {
        // Create test account
        $this->createAccount('SAME001', 'Same Account', '1000.00');

        $this->expectException(InvalidTransferException::class);
        $this->expectExceptionMessage('Cannot transfer to the same account');

        // Attempt transfer to same account
        $this->fundTransferService->transferFunds(
            'SAME001',
            'SAME001',
            '100.00',
            'Same account test'
        );
    }

    public function testInvalidAmount(): void
    {
        // Create test accounts
        $this->createAccount('FROM003', 'Source Account', '1000.00');
        $this->createAccount('TO003', 'Destination Account', '0.00');

        $this->expectException(InvalidTransferException::class);
        $this->expectExceptionMessage('Amount must be a positive number');

        // Attempt transfer with negative amount
        $this->fundTransferService->transferFunds(
            'FROM003',
            'TO003',
            '-100.00',
            'Invalid amount test'
        );
    }

    public function testTransferWithInactiveAccount(): void
    {
        // Create test accounts
        $fromAccount = $this->createAccount('FROM004', 'Source Account', '1000.00');
        $toAccount = $this->createAccount('TO004', 'Destination Account', '0.00');
        
        // Make source account inactive
        $fromAccount->setStatus('inactive');
        $this->entityManager->flush();

        $this->expectException(InvalidTransferException::class);
        $this->expectExceptionMessage('Source account is not active');

        // Attempt transfer from inactive account
        $this->fundTransferService->transferFunds(
            'FROM004',
            'TO004',
            '100.00',
            'Inactive account test'
        );
    }

    public function testGetTransaction(): void
    {
        // Create test accounts and perform a transfer
        $this->createAccount('FROM005', 'Source Account', '1000.00');
        $this->createAccount('TO005', 'Destination Account', '0.00');

        $transaction = $this->fundTransferService->transferFunds(
            'FROM005',
            'TO005',
            '100.00',
            'Get transaction test'
        );

        // Retrieve transaction by ID
        $retrievedTransaction = $this->fundTransferService->getTransaction(
            $transaction->getTransactionId()
        );

        $this->assertInstanceOf(Transaction::class, $retrievedTransaction);
        $this->assertEquals($transaction->getTransactionId(), $retrievedTransaction->getTransactionId());
        $this->assertEquals('100.00', $retrievedTransaction->getAmount());
    }

    public function testGetAccountTransactions(): void
    {
        // Create test accounts
        $fromAccount = $this->createAccount('FROM006', 'Source Account', '1000.00');
        $toAccount = $this->createAccount('TO006', 'Destination Account', '0.00');

        // Perform multiple transfers
        $this->fundTransferService->transferFunds('FROM006', 'TO006', '100.00', 'Transfer 1');
        $this->fundTransferService->transferFunds('FROM006', 'TO006', '200.00', 'Transfer 2');

        // Get transactions for source account
        $transactions = $this->fundTransferService->getAccountTransactions('FROM006');

        $this->assertCount(2, $transactions);
        $this->assertInstanceOf(Transaction::class, $transactions[0]);
        $this->assertEquals('FROM006', $transactions[0]->getFromAccount()->getAccountNumber());
    }

    private function createAccount(string $accountNumber, string $holderName, string $balance): Account
    {
        $account = new Account();
        $account->setAccountNumber($accountNumber);
        $account->setHolderName($holderName);
        $account->setBalance($balance);
        $account->setCurrency('USD');
        $account->setStatus('active');

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $account;
    }
}
