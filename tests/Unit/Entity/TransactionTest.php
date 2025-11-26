<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Account;
use App\Entity\Transaction;
use PHPUnit\Framework\TestCase;

class TransactionTest extends TestCase
{
    private Transaction $transaction;
    private Account $fromAccount;
    private Account $toAccount;

    protected function setUp(): void
    {
        $this->transaction = new Transaction();
        
        $this->fromAccount = new Account();
        $this->fromAccount->setAccountNumber('FROM123456789');
        $this->fromAccount->setHolderName('From Account');
        $this->fromAccount->setBalance('1000.00');
        
        $this->toAccount = new Account();
        $this->toAccount->setAccountNumber('TO123456789');
        $this->toAccount->setHolderName('To Account');
        $this->toAccount->setBalance('500.00');

        $this->transaction->setType(Transaction::TYPE_TRANSFER);
        $this->transaction->setFromAccount($this->fromAccount);
        $this->transaction->setToAccount($this->toAccount);
        $this->transaction->setAmount('250.00');
        $this->transaction->setCurrency('USD');
    }

    public function testTransactionCreation(): void
    {
        $this->assertEquals(Transaction::TYPE_TRANSFER, $this->transaction->getType());
        $this->assertEquals($this->fromAccount, $this->transaction->getFromAccount());
        $this->assertEquals($this->toAccount, $this->transaction->getToAccount());
        $this->assertEquals('250.00', $this->transaction->getAmount());
        $this->assertEquals('USD', $this->transaction->getCurrency());
        $this->assertEquals(Transaction::STATUS_PENDING, $this->transaction->getStatus());
    }

    public function testStatusTransitions(): void
    {
        // Initial status
        $this->assertTrue($this->transaction->isPending());
        $this->assertFalse($this->transaction->isProcessing());
        $this->assertFalse($this->transaction->isCompleted());
        $this->assertFalse($this->transaction->isFailed());

        // Mark as processing
        $this->transaction->markAsProcessing();
        $this->assertFalse($this->transaction->isPending());
        $this->assertTrue($this->transaction->isProcessing());
        $this->assertFalse($this->transaction->isCompleted());
        $this->assertFalse($this->transaction->isFailed());

        // Mark as completed
        $this->transaction->markAsCompleted();
        $this->assertFalse($this->transaction->isPending());
        $this->assertFalse($this->transaction->isProcessing());
        $this->assertTrue($this->transaction->isCompleted());
        $this->assertFalse($this->transaction->isFailed());
        $this->assertNotNull($this->transaction->getProcessedAt());
    }

    public function testFailedTransactionWithReason(): void
    {
        $reason = 'Insufficient funds';
        $this->transaction->markAsFailed($reason);
        
        $this->assertTrue($this->transaction->isFailed());
        $this->assertEquals($reason, $this->transaction->getFailureReason());
        $this->assertNotNull($this->transaction->getProcessedAt());
    }

    public function testTransactionIdGeneration(): void
    {
        $transactionId = $this->transaction->generateTransactionId();
        
        $this->assertNotEmpty($transactionId);
        $this->assertStringStartsWith('TXN-', $transactionId);
        $this->assertStringContainsString(date('Y'), $transactionId);
        
        // Generate another ID to ensure uniqueness
        $anotherTransactionId = $this->transaction->generateTransactionId();
        $this->assertNotEquals($transactionId, $anotherTransactionId);
    }

    public function testTransactionConstants(): void
    {
        // Status constants
        $this->assertEquals('pending', Transaction::STATUS_PENDING);
        $this->assertEquals('processing', Transaction::STATUS_PROCESSING);
        $this->assertEquals('completed', Transaction::STATUS_COMPLETED);
        $this->assertEquals('failed', Transaction::STATUS_FAILED);
        $this->assertEquals('cancelled', Transaction::STATUS_CANCELLED);

        // Type constants
        $this->assertEquals('transfer', Transaction::TYPE_TRANSFER);
        $this->assertEquals('deposit', Transaction::TYPE_DEPOSIT);
        $this->assertEquals('withdrawal', Transaction::TYPE_WITHDRAWAL);
    }

    public function testMetadataHandling(): void
    {
        $metadata = [
            'client_ip' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'request_id' => 'req_123'
        ];

        $this->transaction->setMetadata($metadata);
        $this->assertEquals($metadata, $this->transaction->getMetadata());
    }

    public function testDescriptionHandling(): void
    {
        $description = 'Payment for invoice #12345';
        $this->transaction->setDescription($description);
        $this->assertEquals($description, $this->transaction->getDescription());
    }
}
