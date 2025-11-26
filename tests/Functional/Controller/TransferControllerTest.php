<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class TransferControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()
            ->get('doctrine')
            ->getManager();

        // Start transaction for test isolation
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback transaction to keep tests isolated
        $this->entityManager->rollback();
        parent::tearDown();
    }

    public function testCreateTransferSuccess(): void
    {
        // Create test accounts
        $this->createAccount('FROM_API_001', 'API Source Account', '1000.00');
        $this->createAccount('TO_API_001', 'API Destination Account', '500.00');

        // Prepare transfer data
        $transferData = [
            'from_account' => 'FROM_API_001',
            'to_account' => 'TO_API_001',
            'amount' => '250.00',
            'description' => 'API test transfer'
        ];

        // Make transfer request
        $this->client->request(
            'POST',
            '/api/v1/transfers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($transferData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals('250.00', $responseData['data']['amount']);
        $this->assertEquals('completed', $responseData['data']['status']);
    }

    public function testCreateTransferInvalidData(): void
    {
        // Invalid transfer data (missing required fields)
        $transferData = [
            'from_account' => 'FROM_API_002',
            // Missing to_account and amount
        ];

        $this->client->request(
            'POST',
            '/api/v1/transfers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($transferData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContains('Missing required fields', $responseData['message']);
    }

    public function testCreateTransferInsufficientFunds(): void
    {
        // Create test accounts with insufficient balance
        $this->createAccount('FROM_API_003', 'API Source Account', '50.00');
        $this->createAccount('TO_API_003', 'API Destination Account', '0.00');

        $transferData = [
            'from_account' => 'FROM_API_003',
            'to_account' => 'TO_API_003',
            'amount' => '100.00',
            'description' => 'Insufficient funds test'
        ];

        $this->client->request(
            'POST',
            '/api/v1/transfers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($transferData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertStringContains('Insufficient funds', $responseData['message']);
    }

    public function testGetTransactionById(): void
    {
        // Create test accounts and perform transfer
        $this->createAccount('FROM_API_004', 'API Source Account', '1000.00');
        $this->createAccount('TO_API_004', 'API Destination Account', '0.00');

        // Create transfer
        $transferData = [
            'from_account' => 'FROM_API_004',
            'to_account' => 'TO_API_004',
            'amount' => '100.00',
            'description' => 'Get transaction test'
        ];

        $this->client->request(
            'POST',
            '/api/v1/transfers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($transferData)
        );

        $createResponse = $this->client->getResponse();
        $createData = json_decode($createResponse->getContent(), true);
        $transactionId = $createData['data']['transaction_id'];

        // Get transaction by ID
        $this->client->request('GET', '/api/v1/transfers/' . $transactionId);

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($transactionId, $responseData['data']['transaction_id']);
        $this->assertEquals('100.00', $responseData['data']['amount']);
    }

    public function testGetTransactionStatus(): void
    {
        // Create test accounts and perform transfer
        $this->createAccount('FROM_API_005', 'API Source Account', '1000.00');
        $this->createAccount('TO_API_005', 'API Destination Account', '0.00');

        // Create transfer
        $transferData = [
            'from_account' => 'FROM_API_005',
            'to_account' => 'TO_API_005',
            'amount' => '150.00',
            'description' => 'Status test'
        ];

        $this->client->request(
            'POST',
            '/api/v1/transfers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($transferData)
        );

        $createResponse = $this->client->getResponse();
        $createData = json_decode($createResponse->getContent(), true);
        $transactionId = $createData['data']['transaction_id'];

        // Get transaction status
        $this->client->request('GET', '/api/v1/transfers/' . $transactionId . '/status');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertEquals($transactionId, $responseData['data']['transaction_id']);
        $this->assertEquals('completed', $responseData['data']['status']);
        $this->assertEquals('150.00', $responseData['data']['amount']);
        $this->assertArrayHasKey('created_at', $responseData['data']);
        $this->assertArrayHasKey('updated_at', $responseData['data']);
    }

    public function testListTransactions(): void
    {
        // Create test accounts and perform multiple transfers
        $this->createAccount('FROM_API_006', 'API Source Account', '2000.00');
        $this->createAccount('TO_API_006', 'API Destination Account', '0.00');

        // Create multiple transfers
        for ($i = 1; $i <= 3; $i++) {
            $transferData = [
                'from_account' => 'FROM_API_006',
                'to_account' => 'TO_API_006',
                'amount' => '100.00',
                'description' => "Transfer $i"
            ];

            $this->client->request(
                'POST',
                '/api/v1/transfers',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($transferData)
            );
        }

        // List transactions
        $this->client->request('GET', '/api/v1/transfers?limit=5&offset=0');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertIsArray($responseData['data']);
        $this->assertGreaterThanOrEqual(3, count($responseData['data']));
    }

    public function testSimulateTransfer(): void
    {
        $simulationData = [
            'from_account' => 'FROM_SIM_001',
            'to_account' => 'TO_SIM_001',
            'amount' => '500.00'
        ];

        $this->client->request(
            'POST',
            '/api/v1/transfers/simulate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($simulationData)
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals('FROM_SIM_001', $responseData['data']['from_account']);
        $this->assertEquals('TO_SIM_001', $responseData['data']['to_account']);
        $this->assertEquals('500.00', $responseData['data']['amount']);
        $this->assertEquals('passed', $responseData['data']['validation_status']);
    }

    public function testInvalidJsonRequest(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/transfers',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid json'
        );

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertFalse($responseData['success']);
        $this->assertEquals('Invalid JSON format', $responseData['message']);
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
