<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Account;
use PHPUnit\Framework\TestCase;

class AccountTest extends TestCase
{
    private Account $account;

    protected function setUp(): void
    {
        $this->account = new Account();
        $this->account->setAccountNumber('ACC123456789');
        $this->account->setHolderName('John Doe');
        $this->account->setBalance('1000.00');
        $this->account->setCurrency('USD');
        $this->account->setStatus('active');
    }

    public function testAccountCreation(): void
    {
        $this->assertEquals('ACC123456789', $this->account->getAccountNumber());
        $this->assertEquals('John Doe', $this->account->getHolderName());
        $this->assertEquals('1000.00', $this->account->getBalance());
        $this->assertEquals('USD', $this->account->getCurrency());
        $this->assertEquals('active', $this->account->getStatus());
    }

    public function testIsActive(): void
    {
        $this->assertTrue($this->account->isActive());
        
        $this->account->setStatus('inactive');
        $this->assertFalse($this->account->isActive());
        
        $this->account->setStatus('frozen');
        $this->assertFalse($this->account->isActive());
    }

    public function testHasSufficientBalance(): void
    {
        $this->assertTrue($this->account->hasSufficientBalance('500.00'));
        $this->assertTrue($this->account->hasSufficientBalance('1000.00'));
        $this->assertFalse($this->account->hasSufficientBalance('1000.01'));
        $this->assertFalse($this->account->hasSufficientBalance('2000.00'));
    }

    public function testDebit(): void
    {
        $this->account->debit('250.50');
        $this->assertEquals('749.50', $this->account->getBalance());
    }

    public function testDebitInsufficientFunds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient balance');
        
        $this->account->debit('1500.00');
    }

    public function testCredit(): void
    {
        $this->account->credit('500.25');
        $this->assertEquals('1500.25', $this->account->getBalance());
    }

    public function testBalanceOperationsWithDecimals(): void
    {
        // Test precise decimal operations
        $this->account->setBalance('100.00');
        $this->account->debit('33.33');
        $this->assertEquals('66.67', $this->account->getBalance());
        
        $this->account->credit('33.34');
        $this->assertEquals('100.01', $this->account->getBalance());
    }

    public function testVersioningInitialization(): void
    {
        $newAccount = new Account();
        $this->assertEquals(1, $newAccount->getVersion());
    }
}
