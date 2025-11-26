-- Initialize the database with optimal settings for high performance
SET GLOBAL innodb_buffer_pool_size = 268435456; -- 256MB
SET GLOBAL max_connections = 200;
SET GLOBAL innodb_log_file_size = 50331648; -- 48MB

-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS fund_transfer_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE fund_transfer_db;

-- Ensure proper privileges
GRANT ALL PRIVILEGES ON fund_transfer_db.* TO 'symfony'@'%';
