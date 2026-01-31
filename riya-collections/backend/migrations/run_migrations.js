#!/usr/bin/env node

/**
 * Database Migration Runner for Riya Collections
 * 
 * This script executes all database migration files in order to set up
 * the complete database schema for the e-commerce platform.
 * 
 * Usage:
 *   node run_migrations.js
 * 
 * Requirements: 10.1, 10.4, 14.5
 */

const fs = require('fs').promises;
const path = require('path');
const { pool, testConnection, executeQuery, closePool } = require('../config/database');

// Migration tracking table
const MIGRATION_TABLE_SQL = `
CREATE TABLE IF NOT EXISTS migrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_migrations_filename (filename),
    INDEX idx_migrations_executed_at (executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
`;

/**
 * Get list of migration files in order
 */
async function getMigrationFiles() {
    try {
        const files = await fs.readdir(__dirname);
        return files
            .filter(file => file.endsWith('.sql') && file !== 'run_migrations.js')
            .sort(); // Files are numbered, so alphabetical sort works
    } catch (error) {
        console.error('❌ Error reading migration directory:', error.message);
        throw error;
    }
}

/**
 * Get list of already executed migrations
 */
async function getExecutedMigrations() {
    try {
        const results = await executeQuery('SELECT filename FROM migrations ORDER BY executed_at');
        return results.map(row => row.filename);
    } catch (error) {
        // If migrations table doesn't exist, return empty array
        if (error.code === 'ER_NO_SUCH_TABLE') {
            return [];
        }
        throw error;
    }
}

/**
 * Execute a single migration file
 */
async function executeMigration(filename) {
    try {
        const filePath = path.join(__dirname, filename);
        const sql = await fs.readFile(filePath, 'utf8');
        
        // Remove comments and split by semicolons to handle multiple statements
        const statements = sql
            .split('\n')
            .filter(line => !line.trim().startsWith('--') && line.trim() !== '')
            .join('\n')
            .split(';')
            .map(stmt => stmt.trim())
            .filter(stmt => stmt !== '');
        
        console.log(`📄 Executing migration: ${filename}`);
        
        // Execute each statement
        for (const statement of statements) {
            if (statement.trim()) {
                await executeQuery(statement);
            }
        }
        
        // Record migration as executed
        await executeQuery(
            'INSERT INTO migrations (filename) VALUES (?)',
            [filename]
        );
        
        console.log(`✅ Migration completed: ${filename}`);
        
    } catch (error) {
        console.error(`❌ Migration failed: ${filename}`, error.message);
        throw error;
    }
}

/**
 * Main migration runner function
 */
async function runMigrations() {
    console.log('🚀 Starting database migrations for Riya Collections...\n');
    
    try {
        // Test database connection
        const connected = await testConnection();
        if (!connected) {
            throw new Error('Database connection failed');
        }
        
        // Create migrations tracking table
        console.log('📊 Setting up migration tracking...');
        await executeQuery(MIGRATION_TABLE_SQL);
        
        // Get migration files and executed migrations
        const migrationFiles = await getMigrationFiles();
        const executedMigrations = await getExecutedMigrations();
        
        console.log(`📋 Found ${migrationFiles.length} migration files`);
        console.log(`📋 ${executedMigrations.length} migrations already executed\n`);
        
        // Filter out already executed migrations
        const pendingMigrations = migrationFiles.filter(
            file => !executedMigrations.includes(file)
        );
        
        if (pendingMigrations.length === 0) {
            console.log('✅ All migrations are up to date!');
            return;
        }
        
        console.log(`🔄 Executing ${pendingMigrations.length} pending migrations:\n`);
        
        // Execute pending migrations in order
        for (const migration of pendingMigrations) {
            await executeMigration(migration);
        }
        
        console.log('\n🎉 All migrations completed successfully!');
        console.log('📊 Database schema is now ready for Riya Collections');
        
    } catch (error) {
        console.error('\n💥 Migration process failed:', error.message);
        console.error('🔧 Please check your database configuration and try again');
        process.exit(1);
    } finally {
        await closePool();
    }
}

/**
 * Show migration status without executing
 */
async function showMigrationStatus() {
    try {
        await testConnection();
        
        const migrationFiles = await getMigrationFiles();
        const executedMigrations = await getExecutedMigrations();
        
        console.log('📊 Migration Status:\n');
        
        migrationFiles.forEach(file => {
            const status = executedMigrations.includes(file) ? '✅ Executed' : '⏳ Pending';
            console.log(`  ${status} - ${file}`);
        });
        
        console.log(`\n📋 Total: ${migrationFiles.length} migrations`);
        console.log(`✅ Executed: ${executedMigrations.length}`);
        console.log(`⏳ Pending: ${migrationFiles.length - executedMigrations.length}`);
        
    } catch (error) {
        console.error('❌ Error checking migration status:', error.message);
        process.exit(1);
    } finally {
        await closePool();
    }
}

// Command line interface
const command = process.argv[2];

switch (command) {
    case 'status':
        showMigrationStatus();
        break;
    case 'run':
    default:
        runMigrations();
        break;
}

// Handle process termination
process.on('SIGINT', async () => {
    console.log('\n🔄 Migration interrupted, cleaning up...');
    await closePool();
    process.exit(0);
});

process.on('SIGTERM', async () => {
    console.log('\n🔄 Migration terminated, cleaning up...');
    await closePool();
    process.exit(0);
});