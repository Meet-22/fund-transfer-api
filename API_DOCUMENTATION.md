# API Documentation

## Overview

The Fund Transfer API provides secure endpoints for managing financial accounts and processing fund transfers between them. The API follows RESTful principles and returns JSON responses.

## Base URL

```
http://localhost:8080/api/v1
```

## Authentication

Currently, the API uses IP-based rate limiting for security. For production deployment, implement JWT tokens or API keys.

## Rate Limits

- **Global API Limit**: 100 requests per minute per IP address
- **Transfer Endpoints**: 20 requests per minute per IP address  
- **Account Creation**: 10 requests per 5 minutes per IP address

Rate limit headers are included in responses:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Window`: Time window in seconds
- `Retry-After`: Seconds to wait when rate limited

## Response Format

### Success Response
```json
{
    "success": true,
    "message": "Operation completed successfully",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "data": {
        // Response data
    }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error description",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "status_code": 400,
    "details": [
        // Additional error details when available
    ]
}
```

## Account Management Endpoints

### Create Account

Create a new financial account.

**Endpoint:** `POST /accounts`

**Request Body:**
```json
{
    "account_number": "ACC123456789",
    "holder_name": "John Doe", 
    "balance": "1000.00",
    "currency": "USD",
    "status": "active"
}
```

**Field Validation:**
- `account_number`: 10-50 alphanumeric characters, must be unique
- `holder_name`: 2-100 characters, required
- `balance`: Decimal value ≥ 0, defaults to "0.00"
- `currency`: 3-character currency code, defaults to "USD"
- `status`: One of "active", "inactive", "frozen", defaults to "active"

**Response (201 Created):**
```json
{
    "success": true,
    "message": "Account created successfully",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "data": {
        "id": 1,
        "account_number": "ACC123456789",
        "holder_name": "John Doe",
        "balance": "1000.00",
        "currency": "USD",
        "status": "active",
        "created_at": "2024-11-24T10:30:00+00:00",
        "updated_at": "2024-11-24T10:30:00+00:00"
    }
}
```

**Error Responses:**
- `400 Bad Request`: Invalid input data
- `409 Conflict`: Account number already exists

---

### Get Account

Retrieve account details by account number.

**Endpoint:** `GET /accounts/{accountNumber}`

**Response (200 OK):**
```json
{
    "success": true,
    "message": "Account retrieved successfully",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "data": {
        "id": 1,
        "account_number": "ACC123456789",
        "holder_name": "John Doe",
        "balance": "1000.00",
        "currency": "USD",
        "status": "active",
        "created_at": "2024-11-24T10:30:00+00:00",
        "updated_at": "2024-11-24T10:30:00+00:00"
    }
}
```

**Error Responses:**
- `404 Not Found`: Account does not exist

---

### Get Account Balance

Retrieve current account balance (cached for performance).

**Endpoint:** `GET /accounts/{accountNumber}/balance`

**Response (200 OK):**
```json
{
    "success": true,
    "message": "Balance retrieved successfully",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "data": {
        "account_number": "ACC123456789",
        "balance": "1000.00",
        "currency": "USD"
    }
}
```

---

### Get Account Transactions

Retrieve transaction history for an account.

**Endpoint:** `GET /accounts/{accountNumber}/transactions`

**Query Parameters:**
- `limit`: Maximum number of transactions (1-100, default: 50)
- `offset`: Number of transactions to skip (default: 0)

**Response (200 OK):**
```json
{
    "success": true,
    "message": "Transactions retrieved successfully",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "data": [
        {
            "id": 1,
            "transaction_id": "TXN-ABC123-20241124103000",
            "type": "transfer",
            "amount": "250.00",
            "currency": "USD",
            "status": "completed",
            "description": "Payment for invoice",
            "created_at": "2024-11-24T10:30:00+00:00",
            "updated_at": "2024-11-24T10:30:00+00:00",
            "processed_at": "2024-11-24T10:30:05+00:00",
            "from_account": {
                "account_number": "ACC123456789",
                "holder_name": "John Doe"
            },
            "to_account": {
                "account_number": "ACC987654321", 
                "holder_name": "Jane Smith"
            }
        }
    ]
}
```

---

### Update Account Status

Change the status of an account (active, inactive, frozen).

**Endpoint:** `PUT /accounts/{accountNumber}/status`

**Request Body:**
```json
{
    "status": "inactive"
}
```

**Response (200 OK):**
```json
{
    "success": true,
    "message": "Account status updated successfully",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "data": {
        // Updated account object
    }
}
```

---

### List Accounts

Retrieve paginated list of accounts with filtering.

**Endpoint:** `GET /accounts`

**Query Parameters:**
- `limit`: Maximum accounts to return (1-100, default: 20)
- `offset`: Number of accounts to skip (default: 0)
- `status`: Filter by status ("active", "inactive", "frozen")

**Response (200 OK):**
```json
{
    "success": true,
    "message": "Accounts retrieved successfully",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "data": [
        {
            // Account objects
        }
    ]
}
```

## Fund Transfer Endpoints

### Create Transfer

Transfer funds between two accounts.

**Endpoint:** `POST /transfers`

**Request Body:**
```json
{
    "from_account": "ACC123456789",
    "to_account": "ACC987654321",
    "amount": "250.00",
    "description": "Payment for invoice #12345",
    "metadata": {
        "invoice_id": "INV-12345",
        "category": "payment"
    }
}
```

**Field Validation:**
- `from_account`: Valid account number, must exist and be active
- `to_account`: Valid account number, must exist and be active, cannot be same as from_account
- `amount`: Positive decimal, between min/max transfer limits
- `description`: Optional string description
- `metadata`: Optional object for additional data

**Response (201 Created):**
```json
{
    "success": true,
    "message": "Transfer completed successfully",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "data": {
        "id": 1,
        "transaction_id": "TXN-ABC123-20241124103000",
        "type": "transfer",
        "amount": "250.00",
        "currency": "USD",
        "status": "completed",
        "description": "Payment for invoice #12345",
        "metadata": {
            "invoice_id": "INV-12345",
            "category": "payment",
            "client_ip": "192.168.1.100",
            "user_agent": "Mozilla/5.0...",
            "request_id": "req_12345"
        },
        "created_at": "2024-11-24T10:30:00+00:00",
        "updated_at": "2024-11-24T10:30:05+00:00",
        "processed_at": "2024-11-24T10:30:05+00:00",
        "from_account": {
            "account_number": "ACC123456789",
            "holder_name": "John Doe"
        },
        "to_account": {
            "account_number": "ACC987654321",
            "holder_name": "Jane Smith"
        }
    }
}
```

**Error Responses:**
- `400 Bad Request`: Invalid input data, insufficient funds, same account transfer
- `404 Not Found`: Account not found
- `409 Conflict`: Duplicate transaction detected
- `429 Too Many Requests`: Rate limit exceeded

---

### Get Transaction

Retrieve transaction details by transaction ID.

**Endpoint:** `GET /transfers/{transactionId}`

**Response (200 OK):**
```json
{
    "success": true,
    "message": "Transaction retrieved successfully",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "data": {
        // Complete transaction object
    }
}
```

---

### Get Transaction Status

Get current status of a transaction.

**Endpoint:** `GET /transfers/{transactionId}/status`

**Response (200 OK):**
```json
{
    "success": true,
    "message": "Transaction status retrieved successfully",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "data": {
        "transaction_id": "TXN-ABC123-20241124103000",
        "status": "completed",
        "amount": "250.00",
        "currency": "USD",
        "created_at": "2024-11-24T10:30:00+00:00",
        "updated_at": "2024-11-24T10:30:05+00:00",
        "processed_at": "2024-11-24T10:30:05+00:00"
    }
}
```

**Transaction Statuses:**
- `pending`: Transaction created, awaiting processing
- `processing`: Transaction currently being processed
- `completed`: Transaction successfully completed
- `failed`: Transaction failed (includes failure_reason)
- `cancelled`: Transaction was cancelled

---

### List Transfers

Retrieve paginated list of transfers with filtering.

**Endpoint:** `GET /transfers`

**Query Parameters:**
- `limit`: Maximum transfers to return (1-100, default: 50)
- `offset`: Number of transfers to skip (default: 0)
- `status`: Filter by status

**Response (200 OK):**
```json
{
    "success": true,
    "message": "Transactions retrieved successfully",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "data": [
        {
            // Transaction objects
        }
    ]
}
```

---

### Get Transfer Statistics

Retrieve transfer statistics for a date range.

**Endpoint:** `GET /transfers/stats`

**Query Parameters:**
- `start_date`: Start date (YYYY-MM-DD, default: 7 days ago)
- `end_date`: End date (YYYY-MM-DD, default: today)

**Response (200 OK):**
```json
{
    "success": true,
    "message": "Statistics retrieved successfully",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "data": {
        "period": {
            "start_date": "2024-11-17",
            "end_date": "2024-11-24"
        },
        "totals": {
            "count": 1250,
            "amount": "125000.50"
        },
        "by_status": {
            "completed": {
                "count": 1200,
                "total_amount": "120000.00"
            },
            "failed": {
                "count": 45,
                "total_amount": "4500.25"
            },
            "pending": {
                "count": 5,
                "total_amount": "500.25"
            }
        }
    }
}
```

---

### Simulate Transfer

Test transfer validation without executing the actual transfer.

**Endpoint:** `POST /transfers/simulate`

**Request Body:**
```json
{
    "from_account": "ACC123456789",
    "to_account": "ACC987654321",
    "amount": "250.00"
}
```

**Response (200 OK):**
```json
{
    "success": true,
    "message": "Transfer simulation completed",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "data": {
        "from_account": "ACC123456789",
        "to_account": "ACC987654321",
        "amount": "250.00",
        "currency": "USD",
        "estimated_processing_time": "1-3 seconds",
        "fees": "0.00",
        "final_amount": "250.00",
        "validation_status": "passed"
    }
}
```

## Health Check Endpoints

### Basic Health Check

**Endpoint:** `GET /health`

**Response (200 OK):**
```json
{
    "status": "healthy",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "version": "1.0.0",
    "environment": "dev"
}
```

---

### Detailed Health Check

**Endpoint:** `GET /health/detailed`

**Response (200 OK):**
```json
{
    "overall": "healthy",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "services": {
        "database": {
            "status": "healthy",
            "response_time": 5.23
        },
        "redis": {
            "status": "healthy",
            "response_time": 1.45,
            "stats": {
                "connected_clients": "2",
                "used_memory_human": "1.2M",
                "keyspace_hits": "12450",
                "keyspace_misses": "234"
            }
        },
        "system": {
            "status": "healthy",
            "memory_usage": 52428800,
            "memory_peak": 62914560,
            "disk_space": 85493760000,
            "load_average": [0.45, 0.52, 0.48]
        }
    }
}
```

---

### Database Health Check

**Endpoint:** `GET /health/database`

**Response (200 OK):**
```json
{
    "status": "healthy",
    "response_time_ms": 5.23,
    "account_count": 1250,
    "timestamp": "2024-11-24T10:30:00+00:00"
}
```

---

### Redis Health Check

**Endpoint:** `GET /health/redis`

**Response (200 OK):**
```json
{
    "status": "healthy",
    "response_time_ms": 1.45,
    "stats": {
        "connected_clients": "2",
        "used_memory_human": "1.2M",
        "keyspace_hits": "12450",
        "keyspace_misses": "234"
    },
    "timestamp": "2024-11-24T10:30:00+00:00"
}
```

## Error Codes

### HTTP Status Codes

- `200 OK`: Successful request
- `201 Created`: Resource created successfully
- `400 Bad Request`: Invalid request data
- `404 Not Found`: Resource not found
- `409 Conflict`: Resource conflict (duplicate account number)
- `429 Too Many Requests`: Rate limit exceeded
- `500 Internal Server Error`: Server error

### Business Logic Error Codes

Common error messages returned in API responses:

**Validation Errors:**
- "Missing required fields: field1, field2"
- "Invalid JSON format"
- "Account number already exists"
- "Invalid account number format"
- "Amount must be a positive number"
- "Amount exceeds maximum transfer limit"

**Business Logic Errors:**
- "Account not found"
- "Cannot transfer to the same account"
- "Insufficient funds in source account"
- "Source account is not active"
- "Destination account is not active"
- "Currency mismatch between accounts"
- "Duplicate transaction detected"

**System Errors:**
- "Transfer failed due to system error. Please try again later."
- "Rate limit exceeded. Please try again later."

## Examples

### Complete Transfer Flow

1. **Create Source Account:**
```bash
curl -X POST http://localhost:8080/api/v1/accounts \
  -H "Content-Type: application/json" \
  -d '{
    "account_number": "ACC123456789",
    "holder_name": "John Doe",
    "balance": "1000.00"
  }'
```

2. **Create Destination Account:**
```bash
curl -X POST http://localhost:8080/api/v1/accounts \
  -H "Content-Type: application/json" \
  -d '{
    "account_number": "ACC987654321", 
    "holder_name": "Jane Smith",
    "balance": "500.00"
  }'
```

3. **Simulate Transfer (Optional):**
```bash
curl -X POST http://localhost:8080/api/v1/transfers/simulate \
  -H "Content-Type: application/json" \
  -d '{
    "from_account": "ACC123456789",
    "to_account": "ACC987654321",
    "amount": "250.00"
  }'
```

4. **Execute Transfer:**
```bash
curl -X POST http://localhost:8080/api/v1/transfers \
  -H "Content-Type: application/json" \
  -d '{
    "from_account": "ACC123456789",
    "to_account": "ACC987654321",
    "amount": "250.00",
    "description": "Payment for services"
  }'
```

5. **Check Transaction Status:**
```bash
curl http://localhost:8080/api/v1/transfers/TXN-ABC123-20241124103000/status
```

6. **Verify Balances:**
```bash
curl http://localhost:8080/api/v1/accounts/ACC123456789/balance
curl http://localhost:8080/api/v1/accounts/ACC987654321/balance
```

## SDK Integration Examples

### PHP (using Guzzle)
```php
<?php
$client = new \GuzzleHttp\Client(['base_uri' => 'http://localhost:8080/api/v1/']);

// Create transfer
$response = $client->post('transfers', [
    'json' => [
        'from_account' => 'ACC123456789',
        'to_account' => 'ACC987654321', 
        'amount' => '250.00',
        'description' => 'API payment'
    ]
]);

$result = json_decode($response->getBody(), true);
```

### JavaScript (using fetch)
```javascript
const response = await fetch('http://localhost:8080/api/v1/transfers', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        from_account: 'ACC123456789',
        to_account: 'ACC987654321',
        amount: '250.00',
        description: 'API payment'
    })
});

const result = await response.json();
```

### Python (using requests)
```python
import requests

response = requests.post('http://localhost:8080/api/v1/transfers', json={
    'from_account': 'ACC123456789',
    'to_account': 'ACC987654321',
    'amount': '250.00',
    'description': 'API payment'
})

result = response.json()
```
