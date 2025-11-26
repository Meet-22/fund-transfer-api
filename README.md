# Fund Transfer API

A secure, scalable, and reliable fund transfer system built with Symfony 5.4, demonstrating modern PHP development practices for financial applications.

## 🏗️ Architecture Overview

This application follows a clean architecture pattern with clear separation of concerns:

- **Entities**: Domain models (`Account`, `Transaction`)
- **Repositories**: Data access layer with optimized queries
- **Services**: Business logic (`FundTransferService`, `CacheService`)
- **Controllers**: HTTP API endpoints with proper validation
- **Event Listeners**: Cross-cutting concerns (error handling, rate limiting)

## ✨ Key Features

- **Secure Fund Transfers**: Atomic transactions with pessimistic locking
- **High Performance**: Redis caching and optimized database queries
- **Rate Limiting**: Prevent API abuse with configurable limits
- **Comprehensive Logging**: Structured logging with separate channels
- **Health Monitoring**: Built-in health checks and metrics
- **Input Validation**: Multi-layer validation with custom validators
- **Error Handling**: Standardized API responses with proper HTTP codes
- **Testing**: Unit, integration, and functional test coverage

## 🚀 Quick Start

### Prerequisites

- Docker and Docker Compose
- Make (optional, for convenience commands)

### Installation

1. **Clone and setup:**
   ```bash
   git clone <repository-url>
   ```

2. **Setup environment variables:**
   ```bash
   # Copy the environment template (REQUIRED)
   cp .env.example .env
   
   # Optional: Edit .env if you have port conflicts
   # Most developers can skip this step
   ```

3. **Start the services:**
   ```bash
   docker-compose up -d
   ```

4. **Install dependencies:**
   ```bash
   docker-compose exec app composer install
   ```

5. **Create database and run migrations:**
   ```bash
   docker-compose exec app php bin/console doctrine:database:create
   docker-compose exec app php bin/console doctrine:migrations:migrate
   ```

6. **Create test database:**
   ```bash
   docker-compose exec app php bin/console doctrine:database:create --env=test
   docker-compose exec app php bin/console doctrine:migrations:migrate --env=test
   ```

The API will be available at: http://localhost:8080

## 📋 API Documentation

### Base URL
```
http://localhost:8080/api/v1
```

### Authentication
Currently uses IP-based rate limiting. For production, implement JWT or API key authentication.

### Core Endpoints

#### 1. Create Account
```http
POST /accounts
Content-Type: application/json

{
    "account_number": "ACC123456789",
    "holder_name": "John Doe",
    "balance": "1000.00",
    "currency": "USD",
    "status": "active"
}
```

#### 2. Get Account
```http
GET /accounts/{accountNumber}
```

#### 3. Transfer Funds
```http
POST /transfers
Content-Type: application/json

{
    "from_account": "ACC123456789",
    "to_account": "ACC987654321",
    "amount": "250.00",
    "description": "Payment for invoice #12345"
}
```

#### 4. Get Transaction Status
```http
GET /transfers/{transactionId}/status
```

#### 5. Health Check
```http
GET /health
GET /health/detailed
```

### Response Format

All API responses follow this structure:

```json
{
    "success": true,
    "message": "Operation completed successfully",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "data": {
        // Response data here
    }
}
```

Error responses:

```json
{
    "success": false,
    "message": "Error description",
    "timestamp": "2024-11-24T10:30:00+00:00",
    "status_code": 400
}
```

## 🔒 Security Features

### Input Validation
- Multi-layer validation (entity-level, service-level, API-level)
- Custom validators for account numbers and amounts
- SQL injection prevention through Doctrine ORM
- XSS protection with proper output encoding

### Rate Limiting
- Global API limit: 100 requests/minute per IP
- Transfer endpoint: 20 requests/minute per IP
- Account creation: 10 requests/5 minutes per IP

### Transaction Security
- Pessimistic locking prevents race conditions
- Atomic database transactions
- Duplicate transaction detection
- Balance validation before transfer

### Error Handling
- Sanitized error messages (no sensitive data exposure)
- Comprehensive error logging
- Proper HTTP status codes

## 🚀 Performance Optimizations

### Caching Strategy
- Account balance caching (5-minute TTL)
- Recent transactions caching (3-minute TTL)
- Database query result caching

### Database Optimizations
- Proper indexing on frequently queried columns
- Pessimistic locking for critical sections
- Connection pooling via Doctrine
- Query optimization with proper joins

### High-Load Handling
- Horizontal scaling ready (stateless design)
- Redis for distributed caching
- Configurable connection limits
- Background job processing capability

## 🧪 Testing

### Running Tests

```bash
# All tests
docker-compose exec app php bin/phpunit

# Unit tests only
docker-compose exec app php bin/phpunit tests/Unit

# Integration tests
docker-compose exec app php bin/phpunit tests/Integration

# Functional tests
docker-compose exec app php bin/phpunit tests/Functional
```

### Test Coverage

- **Unit Tests**: Entity logic, service methods, validators
- **Integration Tests**: Service interactions, database operations
- **Functional Tests**: Complete API workflows, error scenarios

### Test Database

Tests use a separate database (`fund_transfer_test`) with automatic rollback after each test to ensure isolation.

## 📊 Monitoring & Logging

### Log Channels
- `app`: General application logs
- `fund_transfer`: Transfer-specific operations
- `security`: Security events and rate limiting
- `performance`: Performance metrics

### Health Checks
- `/api/v1/health`: Basic health status
- `/api/v1/health/detailed`: Comprehensive system status
- `/api/v1/health/database`: Database connectivity
- `/api/v1/health/redis`: Redis connectivity

### Metrics Available
- Transaction volume and success rate
- System resource usage
- Database and Redis performance
- Error rates and types

## 🐳 Docker Configuration

### Services
- **app**: PHP 8.3-FPM with Symfony application
- **nginx**: Web server and reverse proxy
- **db**: MySQL 8.0 database
- **redis**: Redis 7 for caching

### Environment Variables

Key environment variables in `.env`:

```bash
# Database
DATABASE_URL=mysql://root:password@db:3306/fund_transfer_db

# Redis
REDIS_URL=redis://redis:6379
REDIS_CACHE_PREFIX=fund_transfer_

# Application Limits
DAILY_TRANSFER_LIMIT=100000.00
SINGLE_TRANSFER_LIMIT=50000.00
MINIMUM_TRANSFER_AMOUNT=0.01

# Security
API_RATE_LIMIT_PER_MINUTE=100
TRANSACTION_TIMEOUT_MINUTES=5
```

## 🔧 Configuration

### Production Deployment

1. **Environment Setup:**
   ```bash
   APP_ENV=prod
   APP_DEBUG=false
   ```

2. **Database Optimization:**
   ```bash
   # In docker/mysql/init.sql, adjust for production:
   SET GLOBAL innodb_buffer_pool_size = 2147483648; # 2GB
   SET GLOBAL max_connections = 500;
   ```

3. **Redis Configuration:**
   ```bash
   # Use Redis Cluster for high availability
   REDIS_URL=redis://redis-cluster:6379
   ```

4. **Nginx Optimization:**
   ```nginx
   # Add to nginx config
   gzip on;
   gzip_types text/plain application/json;
   client_max_body_size 1M;
   ```

### Scaling Considerations

- **Horizontal Scaling**: Deploy multiple app containers behind a load balancer
- **Database**: Use MySQL master-slave replication for read scaling
- **Redis**: Implement Redis Cluster for distributed caching
- **Monitoring**: Add Prometheus metrics and Grafana dashboards

## 📈 Future Enhancements

### Planned Features
- [ ] JWT-based authentication system
- [ ] Webhook notifications for transaction events  
- [ ] Multi-currency support with real-time rates
- [ ] Transaction fee calculation engine
- [ ] Scheduled/recurring transfers
- [ ] Advanced fraud detection
- [ ] Admin dashboard with analytics
- [ ] Mobile SDK for easy integration

### Architecture Improvements
- [ ] Event-driven architecture with message queues
- [ ] CQRS pattern for read/write separation
- [ ] Microservices decomposition
- [ ] GraphQL API endpoint
- [ ] Real-time WebSocket notifications

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection Error:**
   ```bash
   # Check if database container is running
   docker-compose ps
   # Restart services
   docker-compose restart db app
   ```

2. **Redis Connection Error:**
   ```bash
   # Check Redis logs
   docker-compose logs redis
   # Test Redis connectivity
   docker-compose exec redis redis-cli ping
   ```

3. **Permission Issues:**
   ```bash
   # Fix file permissions
   docker-compose exec app chown -R www-data:www-data var/
   ```

4. **Cache Issues:**
   ```bash
   # Clear cache
   docker-compose exec app php bin/console cache:clear
   ```

### Debug Mode

Enable debug mode for development:
```bash
APP_ENV=dev
APP_DEBUG=true
```

## 📄 License

This project is for evaluation purposes. See company policies for usage rights.

## 👥 Contributing

1. Follow PSR-12 coding standards
2. Write tests for new features
3. Update documentation for API changes
4. Use meaningful commit messages
5. Ensure all tests pass before submitting

## 📞 Support

For technical questions or issues:
- Check the troubleshooting section
- Review application logs in `var/log/`
- Check Docker container logs: `docker-compose logs [service]`

---

**Built with ❤️ using Symfony, Docker, and modern PHP practices**
