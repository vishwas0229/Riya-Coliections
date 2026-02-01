<?php
/**
 * Database Migration Script for Riya Collections
 * 
 * This script handles database migrations for different environments
 * and ensures the database schema is up to date.
 * 
 * Usage:
 * php migrate.php [environment] [action]
 * 
 * Examples:
 * php migrate.php development setup
 * php migrate.php production migrate
 * php migrate.php staging rollback
 */

// Load environment configuration
require_once __DIR__ . '/../environment.php';

class DatabaseMigrator {
    private $pdo;
    private $environment;
    private $migrationsPath;
    
    public function __construct($environment = 'development') {
        $this->environment = $environment;
        $this->migrationsPath = __DIR__ . '/../../../database/migrations/';
        $this->loadEnvironment();
        $this->connect();
        $this->createMigrationsTable();
    }
    
    /**
     * Load environment-specific configuration
     */
    private function loadEnvironment() {
        $envFile = __DIR__ . "/../environments/{$this->environment}.env";
        
        if (!file_exists($envFile)) {
            throw new Exception("Environment file not found: {$envFile}");
        }
        
        // Load environment variables
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
                continue;
            }
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, '"\'');
                
                if (!getenv($key)) {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                }
            }
        }
    }
    
    /**
     * Connect to database
     */
    private function connect() {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'riya_collections';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';
        $charset = getenv('DB_CHARSET') ?: 'utf8mb4';
        
        $dsn = "mysql:host={$host};port={$port};charset={$charset}";
        
        try {
            // First connect without database to create it if needed
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}"
            ]);
            
            // Create database if it doesn't exist
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET {$charset} COLLATE utf8mb4_unicode_ci");
            $this->pdo->exec("USE `{$dbname}`");
            
            echo "Connected to database: {$dbname}\n";
            
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Create migrations tracking table
     */
    private function createMigrationsTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS `migrations` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `migration` varchar(255) NOT NULL,
                `batch` int(11) NOT NULL,
                `executed_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $this->pdo->exec($sql);
    }
    
    /**
     * Run database setup for environment
     */
    public function setup() {
        echo "Setting up database for environment: {$this->environment}\n";
        
        $setupFile = __DIR__ . "/setup-{$this->environment}.sql";
        
        if (!file_exists($setupFile)) {
            throw new Exception("Setup file not found: {$setupFile}");
        }
        
        $sql = file_get_contents($setupFile);
        
        // Split SQL into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^--/', $stmt);
            }
        );
        
        $this->pdo->beginTransaction();
        
        try {
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->pdo->exec($statement);
                }
            }
            
            $this->pdo->commit();
            echo "Database setup completed successfully!\n";
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Setup failed: " . $e->getMessage());
        }
    }
    
    /**
     * Run pending migrations
     */
    public function migrate() {
        echo "Running migrations for environment: {$this->environment}\n";
        
        $executedMigrations = $this->getExecutedMigrations();
        $migrationFiles = $this->getMigrationFiles();
        
        $pendingMigrations = array_diff($migrationFiles, $executedMigrations);
        
        if (empty($pendingMigrations)) {
            echo "No pending migrations found.\n";
            return;
        }
        
        $batch = $this->getNextBatchNumber();
        
        foreach ($pendingMigrations as $migration) {
            echo "Executing migration: {$migration}\n";
            
            $this->executeMigration($migration, $batch);
            $this->recordMigration($migration, $batch);
            
            echo "Migration completed: {$migration}\n";
        }
        
        echo "All migrations completed successfully!\n";
    }
    
    /**
     * Rollback last batch of migrations
     */
    public function rollback() {
        echo "Rolling back last batch of migrations...\n";
        
        $lastBatch = $this->getLastBatchNumber();
        
        if (!$lastBatch) {
            echo "No migrations to rollback.\n";
            return;
        }
        
        $migrations = $this->getMigrationsInBatch($lastBatch);
        
        foreach (array_reverse($migrations) as $migration) {
            echo "Rolling back migration: {$migration}\n";
            
            $this->rollbackMigration($migration);
            $this->removeMigrationRecord($migration);
            
            echo "Rollback completed: {$migration}\n";
        }
        
        echo "Rollback completed successfully!\n";
    }
    
    /**
     * Get list of executed migrations
     */
    private function getExecutedMigrations() {
        $stmt = $this->pdo->query("SELECT migration FROM migrations ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Get list of migration files
     */
    private function getMigrationFiles() {
        $files = glob($this->migrationsPath . '*.sql');
        return array_map('basename', $files);
    }
    
    /**
     * Execute a migration file
     */
    private function executeMigration($migration, $batch) {
        $migrationFile = $this->migrationsPath . $migration;
        
        if (!file_exists($migrationFile)) {
            throw new Exception("Migration file not found: {$migrationFile}");
        }
        
        $sql = file_get_contents($migrationFile);
        
        // Split SQL into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                return !empty($stmt) && !preg_match('/^--/', $stmt);
            }
        );
        
        $this->pdo->beginTransaction();
        
        try {
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->pdo->exec($statement);
                }
            }
            
            $this->pdo->commit();
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Migration failed ({$migration}): " . $e->getMessage());
        }
    }
    
    /**
     * Record migration execution
     */
    private function recordMigration($migration, $batch) {
        $stmt = $this->pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
        $stmt->execute([$migration, $batch]);
    }
    
    /**
     * Get next batch number
     */
    private function getNextBatchNumber() {
        $stmt = $this->pdo->query("SELECT MAX(batch) as max_batch FROM migrations");
        $result = $stmt->fetch();
        return ($result['max_batch'] ?? 0) + 1;
    }
    
    /**
     * Get last batch number
     */
    private function getLastBatchNumber() {
        $stmt = $this->pdo->query("SELECT MAX(batch) as max_batch FROM migrations");
        $result = $stmt->fetch();
        return $result['max_batch'] ?? null;
    }
    
    /**
     * Get migrations in specific batch
     */
    private function getMigrationsInBatch($batch) {
        $stmt = $this->pdo->prepare("SELECT migration FROM migrations WHERE batch = ? ORDER BY id");
        $stmt->execute([$batch]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Rollback a migration (basic implementation)
     */
    private function rollbackMigration($migration) {
        // For now, this is a placeholder
        // In a full implementation, you would have separate rollback SQL files
        echo "Warning: Rollback for {$migration} not implemented. Manual intervention may be required.\n";
    }
    
    /**
     * Remove migration record
     */
    private function removeMigrationRecord($migration) {
        $stmt = $this->pdo->prepare("DELETE FROM migrations WHERE migration = ?");
        $stmt->execute([$migration]);
    }
    
    /**
     * Show migration status
     */
    public function status() {
        echo "Migration status for environment: {$this->environment}\n";
        echo str_repeat('-', 50) . "\n";
        
        $executedMigrations = $this->getExecutedMigrations();
        $migrationFiles = $this->getMigrationFiles();
        
        foreach ($migrationFiles as $migration) {
            $status = in_array($migration, $executedMigrations) ? 'EXECUTED' : 'PENDING';
            echo sprintf("%-40s %s\n", $migration, $status);
        }
        
        echo str_repeat('-', 50) . "\n";
        echo "Total migrations: " . count($migrationFiles) . "\n";
        echo "Executed: " . count($executedMigrations) . "\n";
        echo "Pending: " . (count($migrationFiles) - count($executedMigrations)) . "\n";
    }
}

// Command line interface
if (php_sapi_name() === 'cli') {
    $environment = $argv[1] ?? 'development';
    $action = $argv[2] ?? 'status';
    
    try {
        $migrator = new DatabaseMigrator($environment);
        
        switch ($action) {
            case 'setup':
                $migrator->setup();
                break;
                
            case 'migrate':
                $migrator->migrate();
                break;
                
            case 'rollback':
                $migrator->rollback();
                break;
                
            case 'status':
                $migrator->status();
                break;
                
            default:
                echo "Usage: php migrate.php [environment] [action]\n";
                echo "Environments: development, staging, production\n";
                echo "Actions: setup, migrate, rollback, status\n";
                exit(1);
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}