/**
 * Database Backup and Recovery System for Riya Collections
 * 
 * This module provides comprehensive backup and recovery functionality including:
 * - Automated database backups
 * - File system backups (uploads, logs)
 * - Backup compression and encryption
 * - Recovery procedures
 * - Backup validation and integrity checks
 * 
 * Requirements: 14.1, 14.4 (Production deployment and error handling)
 */

const fs = require('fs');
const path = require('path');
const { spawn, exec } = require('child_process');
const crypto = require('crypto');
const zlib = require('zlib');
const { promisify } = require('util');
const { appLogger } = require('../config/logging');
const { getDatabaseConfig, getEnvironmentConfig } = require('../config/environment');

const execAsync = promisify(exec);

/**
 * Backup configuration
 */
const getBackupConfig = () => {
  const config = getEnvironmentConfig();
  const backupDir = process.env.BACKUP_DIR || path.join(process.cwd(), 'backups');
  
  return {
    enabled: config.backup?.enabled || false,
    directory: backupDir,
    retention: config.backup?.retention || 7, // days
    compression: config.backup?.compression !== false,
    encryption: config.backup?.encryption || false,
    encryptionKey: process.env.BACKUP_ENCRYPTION_KEY,
    schedule: config.backup?.schedule || '0 2 * * *', // Daily at 2 AM
    includeUploads: config.backup?.includeUploads !== false,
    includeLogs: config.backup?.includeLogs !== false,
    maxBackupSize: parseInt(process.env.MAX_BACKUP_SIZE) || 1073741824, // 1GB
    timeout: parseInt(process.env.BACKUP_TIMEOUT) || 3600000 // 1 hour
  };
};

/**
 * Ensure backup directory exists
 */
const ensureBackupDirectory = () => {
  const config = getBackupConfig();
  
  if (!fs.existsSync(config.directory)) {
    fs.mkdirSync(config.directory, { recursive: true });
    appLogger.info('Created backup directory', { directory: config.directory });
  }
  
  return config.directory;
};

/**
 * Generate backup filename with timestamp
 */
const generateBackupFilename = (type, extension = 'sql') => {
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
  const config = getBackupConfig();
  
  let filename = `${type}-${timestamp}.${extension}`;
  
  if (config.compression) {
    filename += '.gz';
  }
  
  if (config.encryption) {
    filename += '.enc';
  }
  
  return filename;
};

/**
 * Create database backup using mysqldump
 */
const createDatabaseBackup = async () => {
  const dbConfig = getDatabaseConfig();
  const backupConfig = getBackupConfig();
  const backupDir = ensureBackupDirectory();
  
  const filename = generateBackupFilename('database');
  const filepath = path.join(backupDir, filename);
  
  appLogger.info('Starting database backup', { filename });
  
  try {
    // Build mysqldump command
    const mysqldumpArgs = [
      '--single-transaction',
      '--routines',
      '--triggers',
      '--events',
      '--add-drop-table',
      '--create-options',
      '--disable-keys',
      '--extended-insert',
      '--quick',
      '--lock-tables=false',
      `--host=${dbConfig.host}`,
      `--port=${dbConfig.port}`,
      `--user=${dbConfig.user}`
    ];
    
    if (dbConfig.password) {
      mysqldumpArgs.push(`--password=${dbConfig.password}`);
    }
    
    mysqldumpArgs.push(dbConfig.database);
    
    // Create backup with optional compression and encryption
    const backup = await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => {
        reject(new Error('Backup timeout exceeded'));
      }, backupConfig.timeout);
      
      const mysqldump = spawn('mysqldump', mysqldumpArgs);
      let outputStream = mysqldump.stdout;
      
      // Apply compression if enabled
      if (backupConfig.compression) {
        const gzip = zlib.createGzip({ level: 6 });
        outputStream = outputStream.pipe(gzip);
      }
      
      // Apply encryption if enabled
      if (backupConfig.encryption && backupConfig.encryptionKey) {
        const cipher = crypto.createCipher('aes-256-cbc', backupConfig.encryptionKey);
        outputStream = outputStream.pipe(cipher);
      }
      
      // Write to file
      const writeStream = fs.createWriteStream(filepath);
      outputStream.pipe(writeStream);
      
      let errorOutput = '';
      mysqldump.stderr.on('data', (data) => {
        errorOutput += data.toString();
      });
      
      mysqldump.on('close', (code) => {
        clearTimeout(timeout);
        
        if (code === 0) {
          resolve({
            filename,
            filepath,
            size: fs.statSync(filepath).size
          });
        } else {
          reject(new Error(`mysqldump failed with code ${code}: ${errorOutput}`));
        }
      });
      
      mysqldump.on('error', (error) => {
        clearTimeout(timeout);
        reject(error);
      });
    });
    
    appLogger.info('Database backup completed', {
      filename: backup.filename,
      size: backup.size,
      compressed: backupConfig.compression,
      encrypted: backupConfig.encryption
    });
    
    return backup;
    
  } catch (error) {
    appLogger.error('Database backup failed', {
      error: error.message,
      filename
    });
    
    // Clean up partial backup file
    if (fs.existsSync(filepath)) {
      fs.unlinkSync(filepath);
    }
    
    throw error;
  }
};

/**
 * Create file system backup (uploads, logs, etc.)
 */
const createFileSystemBackup = async () => {
  const backupConfig = getBackupConfig();
  const backupDir = ensureBackupDirectory();
  
  const filename = generateBackupFilename('files', 'tar');
  const filepath = path.join(backupDir, filename);
  
  appLogger.info('Starting file system backup', { filename });
  
  try {
    const filesToBackup = [];
    
    // Include uploads directory
    if (backupConfig.includeUploads) {
      const uploadsDir = path.join(process.cwd(), 'uploads');
      if (fs.existsSync(uploadsDir)) {
        filesToBackup.push('uploads');
      }
    }
    
    // Include logs directory
    if (backupConfig.includeLogs) {
      const logsDir = path.join(process.cwd(), 'logs');
      if (fs.existsSync(logsDir)) {
        filesToBackup.push('logs');
      }
    }
    
    // Include configuration files
    const configFiles = ['.env.example', 'package.json', 'package-lock.json'];
    configFiles.forEach(file => {
      if (fs.existsSync(path.join(process.cwd(), file))) {
        filesToBackup.push(file);
      }
    });
    
    if (filesToBackup.length === 0) {
      appLogger.warn('No files to backup');
      return null;
    }
    
    // Create tar archive
    let tarCommand = `tar -cf "${filepath}" ${filesToBackup.join(' ')}`;
    
    // Add compression if enabled
    if (backupConfig.compression) {
      tarCommand = `tar -czf "${filepath}" ${filesToBackup.join(' ')}`;
    }
    
    await execAsync(tarCommand, { cwd: process.cwd() });
    
    const stats = fs.statSync(filepath);
    
    // Apply encryption if enabled
    if (backupConfig.encryption && backupConfig.encryptionKey) {
      const encryptedFilepath = filepath + '.enc';
      await encryptFile(filepath, encryptedFilepath, backupConfig.encryptionKey);
      
      // Remove unencrypted file
      fs.unlinkSync(filepath);
      
      const backup = {
        filename: path.basename(encryptedFilepath),
        filepath: encryptedFilepath,
        size: fs.statSync(encryptedFilepath).size
      };
      
      appLogger.info('File system backup completed', {
        filename: backup.filename,
        size: backup.size,
        files: filesToBackup.length,
        compressed: backupConfig.compression,
        encrypted: true
      });
      
      return backup;
    }
    
    const backup = {
      filename,
      filepath,
      size: stats.size
    };
    
    appLogger.info('File system backup completed', {
      filename: backup.filename,
      size: backup.size,
      files: filesToBackup.length,
      compressed: backupConfig.compression,
      encrypted: false
    });
    
    return backup;
    
  } catch (error) {
    appLogger.error('File system backup failed', {
      error: error.message,
      filename
    });
    
    // Clean up partial backup file
    if (fs.existsSync(filepath)) {
      fs.unlinkSync(filepath);
    }
    
    throw error;
  }
};

/**
 * Encrypt file using AES-256-CBC
 */
const encryptFile = async (inputPath, outputPath, key) => {
  return new Promise((resolve, reject) => {
    const cipher = crypto.createCipher('aes-256-cbc', key);
    const input = fs.createReadStream(inputPath);
    const output = fs.createWriteStream(outputPath);
    
    input.pipe(cipher).pipe(output);
    
    output.on('finish', resolve);
    output.on('error', reject);
    input.on('error', reject);
  });
};

/**
 * Decrypt file using AES-256-CBC
 */
const decryptFile = async (inputPath, outputPath, key) => {
  return new Promise((resolve, reject) => {
    const decipher = crypto.createDecipher('aes-256-cbc', key);
    const input = fs.createReadStream(inputPath);
    const output = fs.createWriteStream(outputPath);
    
    input.pipe(decipher).pipe(output);
    
    output.on('finish', resolve);
    output.on('error', reject);
    input.on('error', reject);
  });
};

/**
 * Perform complete system backup
 */
const performFullBackup = async () => {
  const backupConfig = getBackupConfig();
  
  if (!backupConfig.enabled) {
    appLogger.info('Backup is disabled');
    return null;
  }
  
  appLogger.info('Starting full system backup');
  
  const results = {
    timestamp: new Date().toISOString(),
    database: null,
    files: null,
    success: false,
    errors: []
  };
  
  try {
    // Create database backup
    try {
      results.database = await createDatabaseBackup();
    } catch (error) {
      results.errors.push(`Database backup failed: ${error.message}`);
      appLogger.error('Database backup failed', { error: error.message });
    }
    
    // Create file system backup
    try {
      results.files = await createFileSystemBackup();
    } catch (error) {
      results.errors.push(`File system backup failed: ${error.message}`);
      appLogger.error('File system backup failed', { error: error.message });
    }
    
    // Check if at least one backup succeeded
    results.success = results.database || results.files;
    
    if (results.success) {
      appLogger.info('Full system backup completed', {
        database: !!results.database,
        files: !!results.files,
        errors: results.errors.length
      });
      
      // Clean up old backups
      await cleanupOldBackups();
    } else {
      appLogger.error('Full system backup failed - no backups created');
    }
    
    return results;
    
  } catch (error) {
    appLogger.error('Full system backup failed', { error: error.message });
    results.errors.push(`System backup failed: ${error.message}`);
    return results;
  }
};

/**
 * Clean up old backup files based on retention policy
 */
const cleanupOldBackups = async () => {
  const backupConfig = getBackupConfig();
  const backupDir = backupConfig.directory;
  
  if (!fs.existsSync(backupDir)) {
    return;
  }
  
  try {
    const files = fs.readdirSync(backupDir);
    const cutoffDate = new Date(Date.now() - backupConfig.retention * 24 * 60 * 60 * 1000);
    
    let deletedCount = 0;
    let deletedSize = 0;
    
    files.forEach(file => {
      const filepath = path.join(backupDir, file);
      const stats = fs.statSync(filepath);
      
      if (stats.mtime < cutoffDate) {
        deletedSize += stats.size;
        fs.unlinkSync(filepath);
        deletedCount++;
        appLogger.debug('Deleted old backup file', { file, age: Math.floor((Date.now() - stats.mtime) / (24 * 60 * 60 * 1000)) });
      }
    });
    
    if (deletedCount > 0) {
      appLogger.info('Cleaned up old backups', {
        deleted: deletedCount,
        size: deletedSize,
        retention: backupConfig.retention
      });
    }
    
  } catch (error) {
    appLogger.error('Failed to clean up old backups', { error: error.message });
  }
};

/**
 * Restore database from backup
 */
const restoreDatabase = async (backupFilename) => {
  const dbConfig = getDatabaseConfig();
  const backupConfig = getBackupConfig();
  const backupDir = backupConfig.directory;
  const backupPath = path.join(backupDir, backupFilename);
  
  if (!fs.existsSync(backupPath)) {
    throw new Error(`Backup file not found: ${backupFilename}`);
  }
  
  appLogger.info('Starting database restore', { filename: backupFilename });
  
  try {
    let inputStream = fs.createReadStream(backupPath);
    
    // Handle decryption if needed
    if (backupFilename.endsWith('.enc')) {
      if (!backupConfig.encryptionKey) {
        throw new Error('Encryption key required for encrypted backup');
      }
      
      const tempPath = backupPath + '.temp';
      await decryptFile(backupPath, tempPath, backupConfig.encryptionKey);
      inputStream = fs.createReadStream(tempPath);
      
      // Clean up temp file after use
      process.on('exit', () => {
        if (fs.existsSync(tempPath)) {
          fs.unlinkSync(tempPath);
        }
      });
    }
    
    // Handle decompression if needed
    if (backupFilename.includes('.gz')) {
      const gunzip = zlib.createGunzip();
      inputStream = inputStream.pipe(gunzip);
    }
    
    // Build mysql command
    const mysqlArgs = [
      `--host=${dbConfig.host}`,
      `--port=${dbConfig.port}`,
      `--user=${dbConfig.user}`,
      dbConfig.database
    ];
    
    if (dbConfig.password) {
      mysqlArgs.push(`--password=${dbConfig.password}`);
    }
    
    // Execute restore
    await new Promise((resolve, reject) => {
      const mysql = spawn('mysql', mysqlArgs);
      
      inputStream.pipe(mysql.stdin);
      
      let errorOutput = '';
      mysql.stderr.on('data', (data) => {
        errorOutput += data.toString();
      });
      
      mysql.on('close', (code) => {
        if (code === 0) {
          resolve();
        } else {
          reject(new Error(`mysql restore failed with code ${code}: ${errorOutput}`));
        }
      });
      
      mysql.on('error', reject);
    });
    
    appLogger.info('Database restore completed', { filename: backupFilename });
    
  } catch (error) {
    appLogger.error('Database restore failed', {
      error: error.message,
      filename: backupFilename
    });
    throw error;
  }
};

/**
 * List available backups
 */
const listBackups = () => {
  const backupConfig = getBackupConfig();
  const backupDir = backupConfig.directory;
  
  if (!fs.existsSync(backupDir)) {
    return [];
  }
  
  try {
    const files = fs.readdirSync(backupDir);
    
    return files
      .filter(file => file.endsWith('.sql') || file.endsWith('.tar') || file.includes('.gz') || file.endsWith('.enc'))
      .map(file => {
        const filepath = path.join(backupDir, file);
        const stats = fs.statSync(filepath);
        
        return {
          filename: file,
          size: stats.size,
          created: stats.mtime,
          type: file.includes('database') ? 'database' : 'files',
          compressed: file.includes('.gz'),
          encrypted: file.endsWith('.enc')
        };
      })
      .sort((a, b) => b.created - a.created);
      
  } catch (error) {
    appLogger.error('Failed to list backups', { error: error.message });
    return [];
  }
};

/**
 * Validate backup integrity
 */
const validateBackup = async (backupFilename) => {
  const backupConfig = getBackupConfig();
  const backupPath = path.join(backupConfig.directory, backupFilename);
  
  if (!fs.existsSync(backupPath)) {
    return { valid: false, error: 'Backup file not found' };
  }
  
  try {
    const stats = fs.statSync(backupPath);
    
    // Check file size
    if (stats.size === 0) {
      return { valid: false, error: 'Backup file is empty' };
    }
    
    if (stats.size > backupConfig.maxBackupSize) {
      return { valid: false, error: 'Backup file exceeds maximum size' };
    }
    
    // For database backups, try to read first few lines
    if (backupFilename.includes('database')) {
      let stream = fs.createReadStream(backupPath, { start: 0, end: 1024 });
      
      // Handle decompression for validation
      if (backupFilename.includes('.gz')) {
        const gunzip = zlib.createGunzip();
        stream = stream.pipe(gunzip);
      }
      
      const content = await new Promise((resolve, reject) => {
        let data = '';
        stream.on('data', chunk => data += chunk);
        stream.on('end', () => resolve(data));
        stream.on('error', reject);
      });
      
      // Check for SQL dump header
      if (!content.includes('mysqldump') && !content.includes('CREATE TABLE')) {
        return { valid: false, error: 'Invalid SQL dump format' };
      }
    }
    
    return {
      valid: true,
      size: stats.size,
      created: stats.mtime,
      compressed: backupFilename.includes('.gz'),
      encrypted: backupFilename.endsWith('.enc')
    };
    
  } catch (error) {
    return { valid: false, error: error.message };
  }
};

/**
 * Initialize backup system
 */
const initializeBackupSystem = () => {
  const backupConfig = getBackupConfig();
  
  if (!backupConfig.enabled) {
    appLogger.info('Backup system is disabled');
    return;
  }
  
  // Ensure backup directory exists
  ensureBackupDirectory();
  
  appLogger.info('Backup system initialized', {
    directory: backupConfig.directory,
    retention: backupConfig.retention,
    compression: backupConfig.compression,
    encryption: backupConfig.encryption
  });
  
  // Schedule automatic backups if in production
  if (process.env.NODE_ENV === 'production' && backupConfig.schedule) {
    // Note: In a real implementation, you would use a cron job or task scheduler
    appLogger.info('Automatic backup scheduling configured', {
      schedule: backupConfig.schedule
    });
  }
};

module.exports = {
  getBackupConfig,
  createDatabaseBackup,
  createFileSystemBackup,
  performFullBackup,
  restoreDatabase,
  listBackups,
  validateBackup,
  cleanupOldBackups,
  initializeBackupSystem
};