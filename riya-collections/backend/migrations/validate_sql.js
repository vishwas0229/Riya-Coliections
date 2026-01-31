#!/usr/bin/env node

/**
 * SQL Syntax Validator for Migration Files
 * 
 * This script validates the syntax of SQL migration files without
 * requiring a database connection.
 */

const fs = require('fs').promises;
const path = require('path');

/**
 * Basic SQL syntax validation
 */
function validateSQLSyntax(sql, filename) {
    const errors = [];
    
    // Remove comments and normalize whitespace
    const cleanSQL = sql
        .split('\n')
        .filter(line => !line.trim().startsWith('--'))
        .join('\n')
        .replace(/\s+/g, ' ')
        .trim();
    
    // Basic syntax checks
    const checks = [
        {
            pattern: /CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+\w+/i,
            message: 'Should use CREATE TABLE IF NOT EXISTS'
        },
        {
            pattern: /ENGINE=InnoDB/i,
            message: 'Should specify ENGINE=InnoDB'
        },
        {
            pattern: /DEFAULT\s+CHARSET=utf8mb4/i,
            message: 'Should specify DEFAULT CHARSET=utf8mb4'
        },
        {
            pattern: /COLLATE=utf8mb4_unicode_ci/i,
            message: 'Should specify COLLATE=utf8mb4_unicode_ci'
        }
    ];
    
    checks.forEach(check => {
        if (!check.pattern.test(cleanSQL)) {
            errors.push(`Missing: ${check.message}`);
        }
    });
    
    // Check for common syntax errors
    if (cleanSQL.includes(';;')) {
        errors.push('Contains double semicolons');
    }
    
    if (!cleanSQL.endsWith(';')) {
        errors.push('Should end with semicolon');
    }
    
    return errors;
}

/**
 * Validate all migration files
 */
async function validateMigrations() {
    console.log('🔍 Validating SQL migration files...\n');
    
    try {
        const files = await fs.readdir(__dirname);
        const sqlFiles = files
            .filter(file => file.endsWith('.sql') && !file.includes('complete_schema'))
            .sort();
        
        let totalErrors = 0;
        
        for (const file of sqlFiles) {
            console.log(`📄 Validating: ${file}`);
            
            const filePath = path.join(__dirname, file);
            const sql = await fs.readFile(filePath, 'utf8');
            
            const errors = validateSQLSyntax(sql, file);
            
            if (errors.length === 0) {
                console.log('  ✅ Syntax OK');
            } else {
                console.log('  ❌ Issues found:');
                errors.forEach(error => {
                    console.log(`    - ${error}`);
                });
                totalErrors += errors.length;
            }
            console.log();
        }
        
        if (totalErrors === 0) {
            console.log('🎉 All migration files passed validation!');
        } else {
            console.log(`❌ Found ${totalErrors} issues across ${sqlFiles.length} files`);
            process.exit(1);
        }
        
    } catch (error) {
        console.error('❌ Validation failed:', error.message);
        process.exit(1);
    }
}

validateMigrations();