# 🚀 Fund Transfer API - First-Tim### Step 4: ### Step 5: Set Up Database
```bash
# Run database migrations
docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# Create test database
docker-compose exec db mysql -u root -ppassword -e "CREATE DATABASE IF NOT EXISTS fund_transfer_db_test;"
docker-compose exec app php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

### Step 6: Verify Setupendencies
```bash
# Install PHP dependencies
docker-compose exec app composer install
```

### Step 5: Set Up Database Guide

## Prerequisites ✅

Before starting, ensure you have:
- **Docker** and **Docker Compose** installed ([Download Docker](https://www.docker.com/get-started))
- **Git** installed
- **Postman** (optional, for API testing)

---

## Quick Start (5 Minutes) ⚡

### Step 1: Clone and Navigate
```bash
git clone <repository-url>
cd fund-transfer-api
```

### Step 2: Setup Environment Variables
```bash
# Copy the environment template (REQUIRED)
cp .env.example .env

# Optional: Edit .env if you have port conflicts
# Most developers can skip this step
```

### Step 3: Start the Application
```bash
# Start all services (MySQL, Redis, PHP, Nginx)
docker-compose up -d
```

### Step 4: Install Dependencies
```bash
# Install PHP dependencies
docker-compose exec app composer install
```

### Step 4: Set Up Database
```bash
# Run database migrations
docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# Create test database
docker-compose exec db mysql -u root -ppassword -e "CREATE DATABASE IF NOT EXISTS fund_transfer_db_test;"
docker-compose exec app php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

### Step 5: Verify Setup
```bash
# Check if all services are running
docker-compose ps

# Test the API
curl http://localhost:8080/api/v1/health
```

**Expected Response:**
```json
{
  "success": true,
  "message": "API is healthy",
  "timestamp": "2024-11-25T10:30:00+00:00",
  "data": {
    "status": "healthy",
    "database": "connected",
    "redis": "connected"
  }
}
```

🎉 **Congratulations! Your API is running at http://localhost:8080**

---

## Port Configuration (If Needed) 🔧

If you have port conflicts, modify `docker-compose.yml`:

```yaml
nginx:
  ports:
    - "8080:80"    # Change 8080 to any free port (e.g., 3000:80)

db:
  ports:
    - "3307:3306"  # Change 3307 to any free port (e.g., 3306:3306)

redis:
  ports:
    - "6379:6379"  # Change first 6379 to any free port (e.g., 6380:6379)
```

---

## Environment Configuration (Easy Customization!) 🌍

The project uses a flexible `.env` file for all configuration. **No changes needed for basic setup**, but easy to customize!

### Key Configuration Options:

#### **🔌 Port Configuration (Change if you have conflicts):**
```bash
APP_PORT=8080                    # Web app port (change to 3000, 9000, etc.)
DB_EXTERNAL_PORT=3307            # MySQL admin port (change to 3306, 3308, etc.)  
REDIS_EXTERNAL_PORT=6379         # Redis admin port (change to 6380, 6381, etc.)
```

#### **🗄️ Database Settings:**
```bash
DB_HOST=db                       # Use 'db' for Docker, 'localhost' for local
DB_PORT=3306                     # Internal port (don't change for Docker)
DB_NAME=fund_transfer_db         # Database name
DB_USER=root                     # Database username  
DB_PASSWORD=password             # Database password
```

#### **🚀 Redis Settings:**
```bash
REDIS_HOST=redis                 # Use 'redis' for Docker, 'localhost' for local
REDIS_PORT=6379                  # Internal port (don't change for Docker)
REDIS_PASSWORD=                  # Leave empty for no authentication
```

#### **💼 Business Rules:**
```bash
DAILY_TRANSFER_LIMIT=100000.00   # Maximum daily transfer per account
SINGLE_TRANSFER_LIMIT=50000.00   # Maximum single transfer
MINIMUM_TRANSFER_AMOUNT=0.01     # Minimum transfer amount
API_RATE_LIMIT_PER_MINUTE=100    # API requests per minute per IP
```

### **🎯 Common Customizations:**

#### **Port Conflicts Fix:**
```bash
# Edit .env file:
APP_PORT=3000              # Use port 3000 instead of 8080
DB_EXTERNAL_PORT=3306      # Use standard MySQL port
```

#### **Local Development (without Docker):**
```bash  
# Edit .env file:
DB_HOST=localhost
REDIS_HOST=localhost
```

#### **Production Setup:**
```bash
# Edit .env file:
APP_ENV=prod
DB_PASSWORD=your-secure-password
APP_SECRET=your-secure-random-secret
```

### **📋 Configuration Template:**
- See `.env.example` for all available options with explanations
- Copy and customize: `cp .env.example .env.local`

---

## Testing Your Setup 🧪

### 1. Run Automated Tests
```bash
# Run all tests
docker-compose exec -e APP_ENV=test app ./vendor/bin/phpunit --testdox

# Run specific test types
docker-compose exec -e APP_ENV=test app ./vendor/bin/phpunit tests/Unit --testdox
```

### 2. Test API Endpoints
```bash
# Health check
curl http://localhost:8080/api/v1/health

# Create account
curl -X POST http://localhost:8080/api/v1/accounts \
  -H "Content-Type: application/json" \
  -d '{
    "account_number": "ACC123456789",
    "holder_name": "John Doe", 
    "balance": "1000.00",
    "currency": "USD"
  }'

# Get account
curl http://localhost:8080/api/v1/accounts/ACC123456789
```

### 3. Import Postman Collection
1. Open Postman
2. Import `Fund_Transfer_API.postman_collection.json`  
3. Follow the testing guide in `postman/POSTMAN_TESTING_GUIDE.md`

---

## Troubleshooting 🛠️

### Common Issues and Solutions:

#### 1. **Port Already in Use**
```
Error: bind: address already in use
```
**Solution:** Change ports in `docker-compose.yml` (see Port Configuration above)

#### 2. **Docker Services Not Starting**
```bash
# Check service status
docker-compose ps

# View logs
docker-compose logs app
docker-compose logs db  
docker-compose logs redis
```

#### 3. **Database Connection Failed**
```bash
# Restart database service
docker-compose restart db

# Check database logs
docker-compose logs db

# Verify database is created
docker-compose exec db mysql -u root -ppassword -e "SHOW DATABASES;"
```

#### 4. **Composer Install Fails**
```bash
# Fix permissions
docker-compose exec app chown -R www-data:www-data /var/www/html

# Retry installation
docker-compose exec app composer install
```

#### 5. **Tests Fail**
```bash
# Ensure test database exists
docker-compose exec db mysql -u root -ppassword -e "CREATE DATABASE IF NOT EXISTS fund_transfer_db_test;"

# Run migrations for test environment
docker-compose exec app php bin/console doctrine:migrations:migrate --env=test --no-interaction

# Run tests again
docker-compose exec -e APP_ENV=test app ./vendor/bin/phpunit --testdox
```

---

## Development Workflow 💻

### Daily Development:
```bash
# Start services (if not running)
docker-compose up -d

# View logs (optional)
docker-compose logs -f app

# Run tests after changes
docker-compose exec -e APP_ENV=test app ./vendor/bin/phpunit tests/Unit --testdox

# Stop services when done
docker-compose down
```

### Making Changes:
1. **Edit code** - Changes reflect immediately (volume mounting)
2. **Add dependencies** - Run `docker-compose exec app composer require package-name`
3. **Database changes** - Create migrations: `docker-compose exec app php bin/console make:migration`
4. **Clear cache** - Run `docker-compose exec app php bin/console cache:clear`

---

## Project Structure 📁

```
fund-transfer-api/
├── src/                          # Application source code
│   ├── Controller/              # API endpoints
│   ├── Entity/                  # Database models  
│   ├── Repository/              # Data access layer
│   ├── Service/                 # Business logic
│   └── Exception/               # Custom exceptions
├── tests/                       # Automated tests
│   ├── Unit/                    # Unit tests
│   ├── Integration/             # Integration tests
│   └── Functional/              # API tests
├── docker/                      # Docker configuration
│   ├── mysql/init.sql          # Database initialization
│   └── nginx/default.conf      # Web server configuration
├── postman/                     # API testing collection
├── config/                      # Symfony configuration
├── public/                      # Web server document root
├── docker-compose.yml           # Docker services definition
├── .env                         # Environment variables
└── README.md                    # Project documentation
```

---

## API Documentation 📖

- **Base URL:** `http://localhost:8080/api/v1`
- **API Documentation:** See `API_DOCUMENTATION.md`
- **Postman Guide:** See `postman/POSTMAN_TESTING_GUIDE.md`
- **Testing Guide:** See `TESTING_GUIDE.md`

### Key Endpoints:
- `GET /health` - Health check
- `POST /accounts` - Create account
- `GET /accounts/{accountNumber}` - Get account details
- `POST /transfers` - Create fund transfer
- `GET /transfers/{transactionId}/status` - Check transfer status

---

## Production Deployment 🚀

For production deployment:

1. **Change environment:**
   ```bash
   APP_ENV=prod
   APP_DEBUG=false
   ```

2. **Generate new secret:**
   ```bash
   APP_SECRET=$(openssl rand -hex 32)
   ```

3. **Use secure database credentials:**
   ```bash
   DATABASE_URL="mysql://secure_user:secure_password@db_host:3306/fund_transfer_db_prod"
   ```

4. **Enable HTTPS in Nginx configuration**

5. **Set up monitoring and logging**

---

## Support 💬

- **Issues:** Check troubleshooting section above
- **Documentation:** All guides are in the project root
- **Logs:** Use `docker-compose logs [service-name]`
- **Database Access:** `docker-compose exec db mysql -u root -ppassword`

---

**🎉 Happy coding! Your Fund Transfer API is ready for development!**
