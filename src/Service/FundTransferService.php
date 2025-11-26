<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\Transaction;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use App\Exception\TransferException;
use App\Exception\InvalidTransferException;
use App\Exception\AccountNotFoundException;
use App\Exception\InsufficientFundsException;
use App\Exception\DuplicateTransactionException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FundTransferService
{
    private EntityManagerInterface $entityManager;
    private AccountRepository $accountRepository;
    private TransactionRepository $transactionRepository;
    private ValidatorInterface $validator;
    private LoggerInterface $logger;
    private CacheService $cacheService;

    public function __construct(
        EntityManagerInterface $entityManager,
        AccountRepository $accountRepository,
        TransactionRepository $transactionRepository,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        CacheService $cacheService
    ) {
        $this->entityManager = $entityManager;
        $this->accountRepository = $accountRepository;
        $this->transactionRepository = $transactionRepository;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->cacheService = $cacheService;
    }

    /**
     * Transfer funds between accounts with full transaction integrity
     */
    public function transferFunds(
        string $fromAccountNumber,
        string $toAccountNumber,
        string $amount,
        string $description = null,
        array $metadata = []
    ): Transaction {
        $this->logger->info('Starting fund transfer', [
            'from_account' => $fromAccountNumber,
            'to_account' => $toAccountNumber,
            'amount' => $amount
        ]);

        // Validate input parameters
        $this->validateTransferInput($fromAccountNumber, $toAccountNumber, $amount);

        // Create transaction record
        $transaction = new Transaction();
        $transaction->setType(Transaction::TYPE_TRANSFER);
        $transaction->setAmount($amount);
        $transaction->setDescription($description);
        $transaction->setMetadata($metadata);

        try {
            // Use database transaction for atomicity
            $this->entityManager->beginTransaction();

            // Get accounts with pessimistic locking
            $fromAccount = $this->getAccountForUpdate($fromAccountNumber);
            $toAccount = $this->getAccountForUpdate($toAccountNumber);

            // Set account references
            $transaction->setFromAccount($fromAccount);
            $transaction->setToAccount($toAccount);

            // Validate business rules
            $this->validateTransferBusinessRules($fromAccount, $toAccount, $amount, $transaction);

            // Mark transaction as processing
            $transaction->markAsProcessing();
            $this->entityManager->persist($transaction);
            $this->entityManager->flush();

            // Perform the actual transfer
            $this->performTransfer($fromAccount, $toAccount, $amount);

            // Mark transaction as completed
            $transaction->markAsCompleted();
            $this->entityManager->flush();

            // Commit the database transaction
            $this->entityManager->commit();

            // Clear cache for updated accounts
            $this->cacheService->clearAccountCache($fromAccountNumber);
            $this->cacheService->clearAccountCache($toAccountNumber);

            $this->logger->info('Fund transfer completed successfully', [
                'transaction_id' => $transaction->getTransactionId(),
                'from_account' => $fromAccountNumber,
                'to_account' => $toAccountNumber,
                'amount' => $amount
            ]);

            return $transaction;

        } catch (\Throwable $e) {
            // Rollback transaction on any error
            $this->entityManager->rollback();

            // Mark transaction as failed if it was created
            if ($transaction->getId()) {
                try {
                    $this->entityManager->beginTransaction();
                    $transaction = $this->entityManager->find(Transaction::class, $transaction->getId());
                    if ($transaction) {
                        $transaction->markAsFailed($e->getMessage());
                        $this->entityManager->flush();
                    }
                    $this->entityManager->commit();
                } catch (\Throwable $rollbackException) {
                    $this->entityManager->rollback();
                    $this->logger->error('Failed to mark transaction as failed', [
                        'transaction_id' => $transaction->getTransactionId(),
                        'error' => $rollbackException->getMessage()
                    ]);
                }
            }

            $this->logger->error('Fund transfer failed', [
                'from_account' => $fromAccountNumber,
                'to_account' => $toAccountNumber,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new TransferException(
                'Transfer failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get account with pessimistic write lock
     */
    private function getAccountForUpdate(string $accountNumber): Account
    {
        $account = $this->accountRepository->findByAccountNumberForUpdate($accountNumber);
        
        if (!$account) {
            throw new AccountNotFoundException("Account not found: {$accountNumber}");
        }

        return $account;
    }

    /**
     * Validate input parameters
     */
    private function validateTransferInput(string $fromAccountNumber, string $toAccountNumber, string $amount): void
    {
        if (empty($fromAccountNumber) || empty($toAccountNumber)) {
            throw new InvalidTransferException('Account numbers cannot be empty');
        }

        if ($fromAccountNumber === $toAccountNumber) {
            throw new InvalidTransferException('Cannot transfer to the same account');
        }

        if (!is_numeric($amount) || bccomp($amount, '0', 2) <= 0) {
            throw new InvalidTransferException('Amount must be a positive number');
        }

        // Check minimum transfer amount
        $minAmount = $_ENV['MINIMUM_TRANSFER_AMOUNT'] ?? '0.01';
        if (bccomp($amount, $minAmount, 2) < 0) {
            throw new InvalidTransferException("Amount must be at least {$minAmount}");
        }

        // Check maximum transfer amount
        $maxAmount = $_ENV['SINGLE_TRANSFER_LIMIT'] ?? '50000.00';
        if (bccomp($amount, $maxAmount, 2) > 0) {
            throw new InvalidTransferException("Amount exceeds maximum transfer limit of {$maxAmount}");
        }
    }

    /**
     * Validate business rules for the transfer
     */
    private function validateTransferBusinessRules(
        Account $fromAccount,
        Account $toAccount,
        string $amount,
        Transaction $transaction
    ): void {
        // Check if accounts are active
        if (!$fromAccount->isActive()) {
            throw new InvalidTransferException('Source account is not active');
        }

        if (!$toAccount->isActive()) {
            throw new InvalidTransferException('Destination account is not active');
        }

        // Check currency compatibility
        if ($fromAccount->getCurrency() !== $toAccount->getCurrency()) {
            throw new InvalidTransferException('Currency mismatch between accounts');
        }

        // Check sufficient balance
        if (!$fromAccount->hasSufficientBalance($amount)) {
            throw new InsufficientFundsException('Insufficient funds in source account');
        }

        // Check for duplicate transactions
        $duplicateCheck = $this->transactionRepository->findDuplicateTransactions(
            $fromAccount,
            $toAccount,
            $amount,
            new \DateTimeImmutable('-5 minutes')
        );

        if (!empty($duplicateCheck)) {
            throw new DuplicateTransactionException('Duplicate transaction detected');
        }

        // Validate transaction entity
        $errors = $this->validator->validate($transaction);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            throw new InvalidTransferException('Transaction validation failed: ' . implode(', ', $errorMessages));
        }
    }

    /**
     * Perform the actual balance transfer
     */
    private function performTransfer(Account $fromAccount, Account $toAccount, string $amount): void
    {
        // Debit from source account
        $fromAccount->debit($amount);
        
        // Credit to destination account
        $toAccount->credit($amount);

        // Persist changes
        $this->entityManager->persist($fromAccount);
        $this->entityManager->persist($toAccount);
    }

    /**
     * Get transaction by ID
     */
    public function getTransaction(string $transactionId): ?Transaction
    {
        return $this->transactionRepository->findOneBy(['transactionId' => $transactionId]);
    }

    /**
     * Get transactions for an account
     */
    public function getAccountTransactions(string $accountNumber, int $limit = 50, int $offset = 0): array
    {
        $account = $this->accountRepository->findOneBy(['accountNumber' => $accountNumber]);
        
        if (!$account) {
            throw new AccountNotFoundException("Account not found: {$accountNumber}");
        }

        return $this->transactionRepository->findByAccount($account, $limit, $offset);
    }

    /**
     * Get account balance with caching
     */
    public function getAccountBalance(string $accountNumber): string
    {
        // Try to get from cache first
        $balance = $this->cacheService->getAccountBalance($accountNumber);
        
        if ($balance === null) {
            $account = $this->accountRepository->findOneBy(['accountNumber' => $accountNumber]);
            
            if (!$account) {
                throw new AccountNotFoundException("Account not found: {$accountNumber}");
            }

            $balance = $account->getBalance();
            $this->cacheService->setAccountBalance($accountNumber, $balance);
        }

        return $balance;
    }

    /**
     * Process pending transactions (for background job)
     */
    public function processPendingTransactions(int $timeoutMinutes = 5): int
    {
        $pendingTransactions = $this->transactionRepository->findPendingTransactionsOlderThan($timeoutMinutes);
        $processedCount = 0;

        foreach ($pendingTransactions as $transaction) {
            try {
                $this->entityManager->beginTransaction();
                
                $transaction->markAsFailed('Transaction timeout');
                $this->entityManager->flush();
                
                $this->entityManager->commit();
                $processedCount++;
                
                $this->logger->info('Marked transaction as failed due to timeout', [
                    'transaction_id' => $transaction->getTransactionId()
                ]);
                
            } catch (\Throwable $e) {
                $this->entityManager->rollback();
                $this->logger->error('Failed to process pending transaction', [
                    'transaction_id' => $transaction->getTransactionId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $processedCount;
    }
}
