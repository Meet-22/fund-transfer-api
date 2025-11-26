<?php

namespace App\Controller;

use App\Entity\Account;
use App\Repository\AccountRepository;
use App\Service\CacheService;
use App\Service\FundTransferService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/v1/accounts', name: 'account_api_')]
class AccountController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private AccountRepository $accountRepository;
    private FundTransferService $transferService;
    private SerializerInterface $serializer;
    private ValidatorInterface $validator;
    private LoggerInterface $logger;
    private CacheService $cacheService;

    public function __construct(
        EntityManagerInterface $entityManager,
        AccountRepository $accountRepository,
        FundTransferService $transferService,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        CacheService $cacheService
    ) {
        $this->entityManager = $entityManager;
        $this->accountRepository = $accountRepository;
        $this->transferService = $transferService;
        $this->serializer = $serializer;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->cacheService = $cacheService;
    }

    /**
     * Create a new account
     */
    #[Route('', methods: ['POST'], name: 'create')]
    public function createAccount(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createErrorResponse('Invalid JSON format', Response::HTTP_BAD_REQUEST);
            }

            $account = new Account();
            $account->setAccountNumber($data['account_number'] ?? '');
            $account->setHolderName($data['holder_name'] ?? '');
            $account->setBalance($data['balance'] ?? '0.00');
            $account->setCurrency($data['currency'] ?? 'USD');
            $account->setStatus($data['status'] ?? 'active');

            // Validate the account
            $errors = $this->validator->validate($account);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $fieldName = $error->getPropertyPath();
                    $message = $error->getMessage();
                    $errorMessages[] = $fieldName . ': ' . $message;
                }
                return $this->createErrorResponse('Validation failed: ' . implode(', ', $errorMessages), Response::HTTP_BAD_REQUEST);
            }

            // Check if account number already exists
            $existingAccount = $this->accountRepository->findOneBy(['accountNumber' => $account->getAccountNumber()]);
            if ($existingAccount) {
                return $this->createErrorResponse('Account number already exists', Response::HTTP_CONFLICT);
            }

            $this->entityManager->persist($account);
            $this->entityManager->flush();

            $this->logger->info('Account created successfully', [
                'account_number' => $account->getAccountNumber(),
                'holder_name' => $account->getHolderName()
            ]);

            return $this->createSuccessResponse(
                $this->serializer->serialize($account, 'json', ['groups' => ['account:read']]),
                'Account created successfully',
                Response::HTTP_CREATED
            );

        } catch (\Throwable $e) {
            $this->logger->error('Failed to create account', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->createErrorResponse('Failed to create account', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get account by account number
     */
    #[Route('/{accountNumber}', methods: ['GET'], name: 'get')]
    public function getAccount(string $accountNumber): JsonResponse
    {
        try {
            $account = $this->accountRepository->findOneBy(['accountNumber' => $accountNumber]);
            
            if (!$account) {
                return $this->createErrorResponse('Account not found', Response::HTTP_NOT_FOUND);
            }

            return $this->createSuccessResponse(
                $this->serializer->serialize($account, 'json', ['groups' => ['account:read']]),
                'Account retrieved successfully'
            );

        } catch (\Throwable $e) {
            $this->logger->error('Failed to retrieve account', [
                'account_number' => $accountNumber,
                'error' => $e->getMessage()
            ]);
            
            return $this->createErrorResponse('Failed to retrieve account', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get account balance
     */
    #[Route('/{accountNumber}/balance', methods: ['GET'], name: 'balance')]
    public function getBalance(string $accountNumber): JsonResponse
    {
        try {
            $balance = $this->transferService->getAccountBalance($accountNumber);
            
            return $this->createSuccessResponse([
                'account_number' => $accountNumber,
                'balance' => $balance,
                'currency' => 'USD'
            ], 'Balance retrieved successfully');

        } catch (\Throwable $e) {
            $this->logger->error('Failed to retrieve balance', [
                'account_number' => $accountNumber,
                'error' => $e->getMessage()
            ]);
            
            return $this->createErrorResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Get account transaction history
     */
    #[Route('/{accountNumber}/transactions', methods: ['GET'], name: 'transactions')]
    public function getTransactions(string $accountNumber, Request $request): JsonResponse
    {
        try {
            $limit = min((int) $request->query->get('limit', 50), 100);
            $offset = max((int) $request->query->get('offset', 0), 0);

            $transactions = $this->transferService->getAccountTransactions($accountNumber, $limit, $offset);
            
            return $this->createSuccessResponse(
                $this->serializer->serialize($transactions, 'json', ['groups' => ['transaction:read']]),
                'Transactions retrieved successfully'
            );

        } catch (\Throwable $e) {
            $this->logger->error('Failed to retrieve transactions', [
                'account_number' => $accountNumber,
                'error' => $e->getMessage()
            ]);
            
            return $this->createErrorResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Update account status
     */
    #[Route('/{accountNumber}/status', methods: ['PUT'], name: 'update_status')]
    public function updateAccountStatus(string $accountNumber, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createErrorResponse('Invalid JSON format', Response::HTTP_BAD_REQUEST);
            }

            $account = $this->accountRepository->findOneBy(['accountNumber' => $accountNumber]);
            
            if (!$account) {
                return $this->createErrorResponse('Account not found', Response::HTTP_NOT_FOUND);
            }

            $newStatus = $data['status'] ?? '';
            $allowedStatuses = ['active', 'inactive', 'frozen'];
            
            if (!in_array($newStatus, $allowedStatuses)) {
                return $this->createErrorResponse('Invalid status. Allowed values: ' . implode(', ', $allowedStatuses), Response::HTTP_BAD_REQUEST);
            }

            $account->setStatus($newStatus);
            $account->preUpdate(); // Explicitly update the timestamp
            $this->entityManager->flush();

            // Clear cache
            $this->cacheService->clearAccountCache($accountNumber);

            $this->logger->info('Account status updated', [
                'account_number' => $accountNumber,
                'new_status' => $newStatus
            ]);

            return $this->createSuccessResponse(
                $this->serializer->serialize($account, 'json', ['groups' => ['account:read']]),
                'Account status updated successfully'
            );

        } catch (\Throwable $e) {
            $this->logger->error('Failed to update account status', [
                'account_number' => $accountNumber,
                'error' => $e->getMessage()
            ]);
            
            return $this->createErrorResponse('Failed to update account status', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * List accounts with pagination and filtering
     */
    #[Route('', methods: ['GET'], name: 'list')]
    public function listAccounts(Request $request): JsonResponse
    {
        try {
            $limit = min((int) $request->query->get('limit', 20), 100);
            $offset = max((int) $request->query->get('offset', 0), 0);
            $status = $request->query->get('status', 'active');
            
            $criteria = [];
            if ($status) {
                $criteria['status'] = $status;
            }

            $accounts = $this->accountRepository->findBy($criteria, ['createdAt' => 'DESC'], $limit, $offset);
            
            return $this->createSuccessResponse(
                $this->serializer->serialize($accounts, 'json', ['groups' => ['account:read']]),
                'Accounts retrieved successfully'
            );

        } catch (\Throwable $e) {
            $this->logger->error('Failed to list accounts', [
                'error' => $e->getMessage()
            ]);
            
            return $this->createErrorResponse('Failed to list accounts', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a standardized success response
     */
    private function createSuccessResponse($data, string $message, int $statusCode = Response::HTTP_OK): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => (new \DateTime())->format('c')
        ];

        if (is_string($data)) {
            $response['data'] = json_decode($data, true);
        } else {
            $response['data'] = $data;
        }

        return new JsonResponse($response, $statusCode);
    }

    /**
     * Create a standardized error response
     */
    private function createErrorResponse(string $message, int $statusCode, array $details = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => (new \DateTime())->format('c')
        ];

        if (!empty($details)) {
            $response['details'] = $details;
        }

        return new JsonResponse($response, $statusCode);
    }
}
