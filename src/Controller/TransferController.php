<?php

namespace App\Controller;

use App\Service\FundTransferService;
use App\Service\CacheService;
use App\Repository\TransactionRepository;
use App\Exception\TransferException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/v1/transfers', name: 'transfer_api_')]
class TransferController extends AbstractController
{
    private FundTransferService $transferService;
    private TransactionRepository $transactionRepository;
    private SerializerInterface $serializer;
    private LoggerInterface $logger;
    private CacheService $cacheService;

    public function __construct(
        FundTransferService $transferService,
        TransactionRepository $transactionRepository,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        CacheService $cacheService
    ) {
        $this->transferService = $transferService;
        $this->transactionRepository = $transactionRepository;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->cacheService = $cacheService;
    }

    /**
     * Transfer funds between accounts
     */
    #[Route('', methods: ['POST'], name: 'create')]
    public function transferFunds(Request $request): JsonResponse
    {
        try {
            // Rate limiting check
            $clientIp = $request->getClientIp();
            if ($this->cacheService->isRateLimited("transfer:{$clientIp}", 20, 60)) {
                return $this->createErrorResponse('Rate limit exceeded. Please try again later.', Response::HTTP_TOO_MANY_REQUESTS);
            }

            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createErrorResponse('Invalid JSON format', Response::HTTP_BAD_REQUEST);
            }

            // Validate required fields
            $requiredFields = ['from_account', 'to_account', 'amount'];
            $missingFields = [];
            
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                return $this->createErrorResponse(
                    'Missing required fields: ' . implode(', ', $missingFields),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Extract transfer data
            $fromAccount = $data['from_account'];
            $toAccount = $data['to_account'];
            $amount = $data['amount'];
            $description = $data['description'] ?? null;
            $metadata = $data['metadata'] ?? [];

            // Add request metadata
            $metadata['client_ip'] = $clientIp;
            $metadata['user_agent'] = $request->headers->get('User-Agent', 'Unknown');
            $metadata['request_id'] = uniqid();

            // Perform the transfer
            $transaction = $this->transferService->transferFunds(
                $fromAccount,
                $toAccount,
                $amount,
                $description,
                $metadata
            );

            $this->logger->info('Transfer request processed successfully', [
                'transaction_id' => $transaction->getTransactionId(),
                'from_account' => $fromAccount,
                'to_account' => $toAccount,
                'amount' => $amount
            ]);

            return $this->createSuccessResponse(
                $this->serializer->serialize($transaction, 'json', ['groups' => ['transaction:read']]),
                'Transfer completed successfully',
                Response::HTTP_CREATED
            );

        } catch (TransferException $e) {
            $this->logger->warning('Transfer failed with business logic error', [
                'error' => $e->getMessage(),
                'from_account' => $data['from_account'] ?? 'unknown',
                'to_account' => $data['to_account'] ?? 'unknown',
                'amount' => $data['amount'] ?? 'unknown'
            ]);

            return $this->createErrorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);

        } catch (\Throwable $e) {
            $this->logger->error('Transfer failed with system error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->createErrorResponse(
                'Transfer failed due to system error. Please try again later.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get transfer statistics
     */
    #[Route('/stats', methods: ['GET'], name: 'stats')]
    public function getTransferStats(Request $request): JsonResponse
    {
        try {
            $startDate = $request->query->get('start_date', date('Y-m-d', strtotime('-7 days')));
            $endDate = $request->query->get('end_date', date('Y-m-d'));

            $startDateTime = new \DateTime($startDate . ' 00:00:00');
            $endDateTime = new \DateTime($endDate . ' 23:59:59');

            $stats = $this->transactionRepository->getTransactionStats($startDateTime, $endDateTime);
            
            // Format the statistics
            $formattedStats = [
                'period' => [
                    'start_date' => $startDateTime->format('Y-m-d'),
                    'end_date' => $endDateTime->format('Y-m-d')
                ],
                'totals' => [
                    'count' => 0,
                    'amount' => '0.00'
                ],
                'by_status' => []
            ];

            foreach ($stats as $stat) {
                $formattedStats['by_status'][$stat['status']] = [
                    'count' => (int) $stat['count'],
                    'total_amount' => $stat['totalAmount'] ?? '0.00'
                ];
                $formattedStats['totals']['count'] += (int) $stat['count'];
                $formattedStats['totals']['amount'] = bcadd(
                    $formattedStats['totals']['amount'],
                    $stat['totalAmount'] ?? '0.00',
                    2
                );
            }

            return $this->createSuccessResponse($formattedStats, 'Statistics retrieved successfully');

        } catch (\Throwable $e) {
            $this->logger->error('Failed to retrieve transfer statistics', [
                'error' => $e->getMessage()
            ]);

            return $this->createErrorResponse(
                'Failed to retrieve statistics',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get transaction by ID
     */
    #[Route('/{transactionId}', methods: ['GET'], name: 'get')]
    public function getTransaction(string $transactionId): JsonResponse
    {
        try {
            $transaction = $this->transferService->getTransaction($transactionId);
            
            if (!$transaction) {
                return $this->createErrorResponse('Transaction not found', Response::HTTP_NOT_FOUND);
            }

            return $this->createSuccessResponse(
                $this->serializer->serialize($transaction, 'json', ['groups' => ['transaction:read']]),
                'Transaction retrieved successfully'
            );

        } catch (\Throwable $e) {
            $this->logger->error('Failed to retrieve transaction', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return $this->createErrorResponse(
                'Failed to retrieve transaction',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get transaction status
     */
    #[Route('/{transactionId}/status', methods: ['GET'], name: 'status')]
    public function getTransactionStatus(string $transactionId): JsonResponse
    {
        try {
            $transaction = $this->transferService->getTransaction($transactionId);
            
            if (!$transaction) {
                return $this->createErrorResponse('Transaction not found', Response::HTTP_NOT_FOUND);
            }

            $status = [
                'transaction_id' => $transaction->getTransactionId(),
                'status' => $transaction->getStatus(),
                'amount' => $transaction->getAmount(),
                'currency' => $transaction->getCurrency(),
                'created_at' => $transaction->getCreatedAt()->format('c'),
                'updated_at' => $transaction->getUpdatedAt()->format('c'),
                'processed_at' => $transaction->getProcessedAt() ? $transaction->getProcessedAt()->format('c') : null
            ];

            if ($transaction->isFailed()) {
                $status['failure_reason'] = $transaction->getFailureReason();
            }

            return $this->createSuccessResponse($status, 'Transaction status retrieved successfully');

        } catch (\Throwable $e) {
            $this->logger->error('Failed to retrieve transaction status', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return $this->createErrorResponse(
                'Failed to retrieve transaction status',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * List transactions with filtering and pagination
     */
    #[Route('', methods: ['GET'], name: 'list')]
    public function listTransactions(Request $request): JsonResponse
    {
        try {
            $limit = min((int) $request->query->get('limit', 50), 100);
            $offset = max((int) $request->query->get('offset', 0), 0);
            $status = $request->query->get('status');

            $criteria = [];
            if ($status) {
                $criteria['status'] = $status;
            }

            $transactions = $this->transactionRepository->findBy(
                $criteria,
                ['createdAt' => 'DESC'],
                $limit,
                $offset
            );

            return $this->createSuccessResponse(
                $this->serializer->serialize($transactions, 'json', ['groups' => ['transaction:read']]),
                'Transactions retrieved successfully'
            );

        } catch (\Throwable $e) {
            $this->logger->error('Failed to list transactions', [
                'error' => $e->getMessage()
            ]);

            return $this->createErrorResponse(
                'Failed to list transactions',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Simulate transfer (for testing without actual fund movement)
     */
    #[Route('/simulate', methods: ['POST'], name: 'simulate')]
    public function simulateTransfer(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createErrorResponse('Invalid JSON format', Response::HTTP_BAD_REQUEST);
            }

            // Validate the transfer parameters without executing
            $fromAccount = $data['from_account'] ?? '';
            $toAccount = $data['to_account'] ?? '';
            $amount = $data['amount'] ?? '';

            // Basic validation
            if (empty($fromAccount) || empty($toAccount) || empty($amount)) {
                return $this->createErrorResponse('Missing required fields', Response::HTTP_BAD_REQUEST);
            }

            if ($fromAccount === $toAccount) {
                return $this->createErrorResponse('Cannot transfer to the same account', Response::HTTP_BAD_REQUEST);
            }

            if (!is_numeric($amount) || bccomp($amount, '0', 2) <= 0) {
                return $this->createErrorResponse('Invalid amount', Response::HTTP_BAD_REQUEST);
            }

            // Simulate the validation (without database transaction)
            $simulation = [
                'from_account' => $fromAccount,
                'to_account' => $toAccount,
                'amount' => $amount,
                'currency' => 'USD',
                'estimated_processing_time' => '1-3 seconds',
                'fees' => '0.00',
                'final_amount' => $amount,
                'validation_status' => 'passed'
            ];

            return $this->createSuccessResponse($simulation, 'Transfer simulation completed');

        } catch (\Throwable $e) {
            $this->logger->error('Transfer simulation failed', [
                'error' => $e->getMessage()
            ]);

            return $this->createErrorResponse(
                'Simulation failed',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
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
