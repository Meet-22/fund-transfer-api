<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for Transfer API endpoints
 * These test the complete HTTP request/response cycle
 */
class TransferControllerFunctionalTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        
        // Set up test database with sample data
        $this->setUpTestData();
    }

    private function setUpTestData(): void
    {
        // Create test accounts via API
        $this->client->request('POST', '/api/v1/accounts', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'account_number' => 'FUNC001TEST',
                'holder_name' => 'Functional Test Account 1',
                'balance' => '1000.00'
            ])
        );

        $this->client->request('POST', '/api/v1/accounts', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'account_number' => 'FUNC002TEST',
                'holder_name' => 'Functional Test Account 2',
                'balance' => '500.00'
            ])
        );
    }

    /**
     * Test: Can we create a transfer via API?
     * This tests the complete user journey
     */
    public function testCreateTransferEndpoint(): void
    {
        // ACT: Make HTTP POST request to create transfer
        $this->client->request('POST', '/api/v1/transfers', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'from_account' => 'FUNC001TEST',
                'to_account' => 'FUNC002TEST',
                'amount' => '250.00',
                'description' => 'Functional test transfer'
            ])
        );

        // ASSERT: Check HTTP response
        $this->assertResponseIsSuccessful(); // 200-299 status code
        $this->assertResponseHeaderSame('content-type', 'application/json');

        // Parse JSON response
        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        // Check response structure
        $this->assertArrayHasKey('success', $responseData);
        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Transfer completed successfully', $responseData['message']);
        
        // Check transaction data
        $this->assertArrayHasKey('data', $responseData);
        $transactionData = $responseData['data'];
        $this->assertEquals('250.00', $transactionData['amount']);
        $this->assertEquals('completed', $transactionData['status']);
    }

    /**
     * Test: What happens when we try invalid transfer?
     */
    public function testCreateTransferWithInvalidData(): void
    {
        // ACT: Try to create transfer with missing required field
        $this->client->request('POST', '/api/v1/transfers', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'from_account' => 'FUNC001TEST',
                // Missing 'to_account'
                'amount' => '250.00'
            ])
        );

        // ASSERT: Should return error
        $this->assertResponseStatusCodeSame(400); // Bad Request

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Missing required field', $responseData['message']);
    }

    /**
     * Test: Transfer with insufficient funds
     */
    public function testTransferInsufficientFunds(): void
    {
        // ACT: Try to transfer more than account balance
        $this->client->request('POST', '/api/v1/transfers', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'from_account' => 'FUNC001TEST',
                'to_account' => 'FUNC002TEST',
                'amount' => '2000.00', // More than $1000 balance
                'description' => 'Overdraft attempt'
            ])
        );

        // ASSERT: Should return error
        $this->assertResponseStatusCodeSame(422); // Unprocessable Entity

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        $this->assertFalse($responseData['success']);
        $this->assertStringContainsString('Insufficient funds', $responseData['message']);
    }

    /**
     * Test: Get transfer status endpoint
     */
    public function testGetTransferStatus(): void
    {
        // ARRANGE: Create a transfer first
        $this->client->request('POST', '/api/v1/transfers', [], [], 
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'from_account' => 'FUNC001TEST',
                'to_account' => 'FUNC002TEST',
                'amount' => '100.00',
                'description' => 'Test for status check'
            ])
        );

        $createResponse = json_decode($this->client->getResponse()->getContent(), true);
        $transactionId = $createResponse['data']['transaction_id'];

        // ACT: Get transfer status
        $this->client->request('GET', "/api/v1/transfers/{$transactionId}/status");

        // ASSERT: Should return status information
        $this->assertResponseIsSuccessful();

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals('completed', $responseData['data']['status']);
    }

    /**
     * Test: List transfers endpoint with pagination
     */
    public function testListTransfers(): void
    {
        // ARRANGE: Create multiple transfers
        for ($i = 1; $i <= 3; $i++) {
            $this->client->request('POST', '/api/v1/transfers', [], [], 
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'from_account' => 'FUNC001TEST',
                    'to_account' => 'FUNC002TEST',
                    'amount' => '10.00',
                    'description' => "Test transfer #{$i}"
                ])
            );
        }

        // ACT: Get list of transfers
        $this->client->request('GET', '/api/v1/transfers?page=1&limit=2');

        // ASSERT: Should return paginated list
        $this->assertResponseIsSuccessful();

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('pagination', $responseData);
        
        // Check pagination info
        $this->assertEquals(1, $responseData['pagination']['page']);
        $this->assertEquals(2, $responseData['pagination']['limit']);
        $this->assertGreaterThanOrEqual(3, $responseData['pagination']['total']);
    }

    /**
     * Test: Health check endpoint
     */
    public function testHealthCheckEndpoint(): void
    {
        // ACT: Check API health
        $this->client->request('GET', '/api/v1/health');

        // ASSERT: Should return healthy status
        $this->assertResponseIsSuccessful();

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        $this->assertEquals('healthy', $responseData['status']);
        $this->assertArrayHasKey('timestamp', $responseData);
        $this->assertArrayHasKey('version', $responseData);
    }

    /**
     * Test: Account balance endpoint
     */
    public function testAccountBalanceEndpoint(): void
    {
        // ACT: Get account balance
        $this->client->request('GET', '/api/v1/accounts/FUNC001TEST/balance');

        // ASSERT: Should return balance information
        $this->assertResponseIsSuccessful();

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        $this->assertTrue($responseData['success']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals('FUNC001TEST', $responseData['data']['account_number']);
        $this->assertArrayHasKey('balance', $responseData['data']);
        $this->assertEquals('USD', $responseData['data']['currency']);
    }

    /**
     * Test: Rate limiting (if implemented)
     */
    public function testRateLimiting(): void
    {
        // This would test if your API properly limits requests
        // For example, making too many transfer attempts
        
        // Make multiple rapid requests
        for ($i = 0; $i < 10; $i++) {
            $this->client->request('GET', '/api/v1/health');
        }

        // Should still be successful (adjust based on your rate limits)
        $this->assertResponseIsSuccessful();
    }

    /**
     * Test: Error response format consistency
     */
    public function testErrorResponseFormat(): void
    {
        // ACT: Make request that will fail
        $this->client->request('GET', '/api/v1/accounts/NONEXISTENT/balance');

        // ASSERT: Error response should have consistent format
        $this->assertResponseStatusCodeSame(404);

        $responseContent = $this->client->getResponse()->getContent();
        $responseData = json_decode($responseContent, true);

        // Check error response structure
        $this->assertArrayHasKey('success', $responseData);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('timestamp', $responseData);
    }

    /**
     * Test: Content-Type validation
     */
    public function testContentTypeValidation(): void
    {
        // ACT: Send request with wrong Content-Type
        $this->client->request('POST', '/api/v1/transfers', [], [], 
            ['CONTENT_TYPE' => 'text/plain'], // Wrong content type
            json_encode([
                'from_account' => 'FUNC001TEST',
                'to_account' => 'FUNC002TEST',
                'amount' => '100.00'
            ])
        );

        // ASSERT: Should handle gracefully (behavior depends on your implementation)
        $response = $this->client->getResponse();
        $this->assertLessThan(500, $response->getStatusCode()); // Not a server error
    }
}
