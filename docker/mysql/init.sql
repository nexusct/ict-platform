-- ICT Platform Database Initialization Script
-- This script runs when the MySQL container is first created

-- Set character set and collation
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Create test database for PHPUnit
CREATE DATABASE IF NOT EXISTS wordpress_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON wordpress_test.* TO 'wordpress'@'%';

-- Performance optimizations
SET GLOBAL innodb_buffer_pool_size = 268435456; -- 256MB
SET GLOBAL max_connections = 150;
SET GLOBAL query_cache_size = 0; -- Deprecated in MySQL 8
SET GLOBAL slow_query_log = 1;
SET GLOBAL long_query_time = 2;

-- Flush privileges
FLUSH PRIVILEGES;

-- Success message
SELECT 'ICT Platform database initialized successfully' AS status;
