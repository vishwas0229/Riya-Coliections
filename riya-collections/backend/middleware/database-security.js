const { executeQuery, executeTransaction, getConnection } = require('../config/database');
const { containsSQLInjection } = require('./validation');

/**
 * Secure Database Wrapper for SQL Injection Prevention
 * 
 * This module provides additional security layers on top of the existing
 * database configuration to ensure all queries are properly parameterized
 * and validated against SQL injection attempts.
 * 
 * Requirements: 9.1, 9.2
 */

/**
 * Validate query structure to ensure it's safe for parameterized execution
 * @param {string} query - SQL query string
 * @param {Array} params - Query parameters
 * @returns {boolean} - True if query structure is valid
 */
const validateQueryStructure = (query, params = []) => {
  if (typeof query !== 'string') {
    throw new Error('Query must be a string');
  }
  
  if (!Array.isArray(params)) {
    throw new Error('Parameters must be an array');
  }
  
  // Check for potential SQL injection in the query template itself
  // This shouldn't happen in properly written code, but adds extra protection
  const suspiciousPatterns = [
    /\$\{.*?\}/g, // Template literals
    /\+.*?['"`]/g, // String concatenation with quotes
    /concat\s*\(/gi, // CONCAT function usage (potential injection)
  ];
  
  for (const pattern of suspiciousPatterns) {
    if (pattern.test(query)) {
      console.warn('Suspicious query pattern detected:', query);
      return false;
    }
  }
  
  // Count parameter placeholders vs provided parameters
  const placeholderCount = (query.match(/\?/g) || []).length;
  if (placeholderCount !== params.length) {
    throw new Error(`Parameter count mismatch: expected ${placeholderCount}, got ${params.length}`);
  }
  
  return true;
};

/**
 * Validate parameters for potential SQL injection content
 * @param {Array} params - Query parameters to validate
 * @returns {boolean} - True if parameters are safe
 */
const validateParameters = (params) => {
  for (let i = 0; i < params.length; i++) {
    const param = params[i];
    
    // Skip null, undefined, numbers, booleans, and dates
    if (param === null || param === undefined || 
        typeof param === 'number' || typeof param === 'boolean' ||
        param instanceof Date) {
      continue;
    }
    
    // Check string parameters for SQL injection patterns
    if (typeof param === 'string' && containsSQLInjection(param)) {
      console.warn(`Potential SQL injection in parameter ${i}:`, param);
      return false;
    }
    
    // Check for objects that might contain malicious content
    if (typeof param === 'object' && param !== null) {
      console.warn(`Object parameter detected at index ${i}. Objects should be serialized before database operations.`);
      return false;
    }
  }
  
  return true;
};

/**
 * Secure wrapper for executeQuery with additional validation
 * @param {string} query - SQL query with parameter placeholders
 * @param {Array} params - Query parameters
 * @returns {Promise} - Query results
 */
const secureExecuteQuery = async (query, params = []) => {
  try {
    // Log query and params length for debugging mock ordering issues
    try {
      const placeholderCount = (query.match(/\?/g) || []).length;
      console.debug('[secureExecuteQuery] query=', query.substring(0, 200), '...');
      console.debug('[secureExecuteQuery] placeholderCount=', placeholderCount, 'params.length=', params.length);
      // Capture caller stack (skip this function frame)
      const stack = new Error().stack.split('\n').slice(2, 6).join('\n');
      console.debug('[secureExecuteQuery] caller stack:\n', stack);
    } catch (logErr) {
      // ignore logging errors
    }

    // Validate query structure
    if (!validateQueryStructure(query, params)) {
      throw new Error('Invalid query structure detected');
    }
    
    // Validate parameters
    if (!validateParameters(params)) {
      throw new Error('Invalid parameters detected');
    }
    
    // Log query for security monitoring (in development)
    if (process.env.NODE_ENV === 'development' && process.env.LOG_QUERIES === 'true') {
      console.log('Executing query:', query);
      console.log('With parameters:', params.map(p => typeof p === 'string' && p.length > 50 ? p.substring(0, 50) + '...' : p));
    }
    
    // Execute the query using the existing secure method
    const result = await executeQuery(query, params);
    console.debug('[secureExecuteQuery] execution result length/type:', Array.isArray(result) ? `array(${result.length})` : typeof result);
    return result;
    
  } catch (error) {
    // Log security-related errors
    if (error.message.includes('Invalid') || error.message.includes('injection')) {
      console.error('Security validation failed:', {
        error: error.message,
        query: query.substring(0, 100) + (query.length > 100 ? '...' : ''),
        paramCount: params.length,
        paramsPreview: params.map(p => (typeof p === 'string' && p.length > 100 ? p.substring(0, 100) + '...' : p)),
        timestamp: new Date().toISOString()
      });
    }
    
    throw error;
  }
};

/**
 * Secure wrapper for executeTransaction with additional validation
 * @param {Array} queries - Array of {query, params} objects
 * @returns {Promise} - Transaction results
 */
const secureExecuteTransaction = async (queries) => {
  try {
    // Validate all queries in the transaction
    for (let i = 0; i < queries.length; i++) {
      const { query, params = [] } = queries[i];
      
      if (!validateQueryStructure(query, params)) {
        throw new Error(`Invalid query structure in transaction at index ${i}`);
      }
      
      if (!validateParameters(params)) {
        throw new Error(`Invalid parameters in transaction at index ${i}`);
      }
    }
    
    // Log transaction for security monitoring (in development)
    if (process.env.NODE_ENV === 'development' && process.env.LOG_QUERIES === 'true') {
      console.log(`Executing transaction with ${queries.length} queries`);
    }
    
    // Execute the transaction using the existing secure method
    return await executeTransaction(queries);
    
  } catch (error) {
    // Log security-related errors
    if (error.message.includes('Invalid') || error.message.includes('injection')) {
      console.error('Transaction security validation failed:', {
        error: error.message,
        queryCount: queries.length,
        timestamp: new Date().toISOString()
      });
    }
    
    throw error;
  }
};

/**
 * Secure connection wrapper with monitoring
 * @returns {Promise} - Database connection
 */
const secureGetConnection = async () => {
  try {
    const connection = await getConnection();
    
    // Add security monitoring to the connection
    const originalExecute = connection.execute.bind(connection);
    connection.execute = async (query, params = []) => {
      // Validate before execution
      if (!validateQueryStructure(query, params)) {
        throw new Error('Invalid query structure detected');
      }
      
      if (!validateParameters(params)) {
        throw new Error('Invalid parameters detected');
      }
      
      return await originalExecute(query, params);
    };
    
    return connection;
    
  } catch (error) {
    console.error('Secure connection creation failed:', error.message);
    throw error;
  }
};

/**
 * Query builder helpers for common operations
 * These helpers ensure queries are constructed safely
 */
const queryBuilders = {
  /**
   * Build a SELECT query with WHERE conditions
   * @param {string} table - Table name
   * @param {Array} columns - Columns to select
   * @param {Object} conditions - WHERE conditions
   * @param {Object} options - Additional options (ORDER BY, LIMIT, etc.)
   * @returns {Object} - {query, params}
   */
  select: (table, columns = ['*'], conditions = {}, options = {}) => {
    // Validate table name (should be alphanumeric with underscores)
    if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(table)) {
      throw new Error('Invalid table name');
    }
    
    // Validate column names
    const validColumns = columns.map(col => {
      if (col === '*') return col;
      if (!/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/.test(col)) {
        throw new Error(`Invalid column name: ${col}`);
      }
      return col;
    });
    
    let query = `SELECT ${validColumns.join(', ')} FROM ${table}`;
    const params = [];
    
    // Add WHERE conditions
    if (Object.keys(conditions).length > 0) {
      const whereClause = [];
      for (const [column, value] of Object.entries(conditions)) {
        // Validate column name
        if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(column)) {
          throw new Error(`Invalid column name in WHERE: ${column}`);
        }
        whereClause.push(`${column} = ?`);
        params.push(value);
      }
      query += ` WHERE ${whereClause.join(' AND ')}`;
    }
    
    // Add ORDER BY
    if (options.orderBy) {
      const { column, direction = 'ASC' } = options.orderBy;
      if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(column)) {
        throw new Error(`Invalid ORDER BY column: ${column}`);
      }
      if (!['ASC', 'DESC'].includes(direction.toUpperCase())) {
        throw new Error(`Invalid ORDER BY direction: ${direction}`);
      }
      query += ` ORDER BY ${column} ${direction.toUpperCase()}`;
    }
    
    // Add LIMIT
    if (options.limit) {
      if (!Number.isInteger(options.limit) || options.limit <= 0) {
        throw new Error('LIMIT must be a positive integer');
      }
      query += ` LIMIT ?`;
      params.push(options.limit);
    }
    
    // Add OFFSET
    if (options.offset) {
      if (!Number.isInteger(options.offset) || options.offset < 0) {
        throw new Error('OFFSET must be a non-negative integer');
      }
      query += ` OFFSET ?`;
      params.push(options.offset);
    }
    
    return { query, params };
  },
  
  /**
   * Build an INSERT query
   * @param {string} table - Table name
   * @param {Object} data - Data to insert
   * @returns {Object} - {query, params}
   */
  insert: (table, data) => {
    // Validate table name
    if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(table)) {
      throw new Error('Invalid table name');
    }
    
    const columns = Object.keys(data);
    const params = Object.values(data);
    
    // Validate column names
    for (const column of columns) {
      if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(column)) {
        throw new Error(`Invalid column name: ${column}`);
      }
    }
    
    const placeholders = columns.map(() => '?').join(', ');
    const query = `INSERT INTO ${table} (${columns.join(', ')}) VALUES (${placeholders})`;
    
    return { query, params };
  },
  
  /**
   * Build an UPDATE query
   * @param {string} table - Table name
   * @param {Object} data - Data to update
   * @param {Object} conditions - WHERE conditions
   * @returns {Object} - {query, params}
   */
  update: (table, data, conditions) => {
    // Validate table name
    if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(table)) {
      throw new Error('Invalid table name');
    }
    
    if (Object.keys(conditions).length === 0) {
      throw new Error('UPDATE queries must have WHERE conditions');
    }
    
    const setClause = [];
    const params = [];
    
    // Build SET clause
    for (const [column, value] of Object.entries(data)) {
      if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(column)) {
        throw new Error(`Invalid column name: ${column}`);
      }
      setClause.push(`${column} = ?`);
      params.push(value);
    }
    
    // Build WHERE clause
    const whereClause = [];
    for (const [column, value] of Object.entries(conditions)) {
      if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(column)) {
        throw new Error(`Invalid column name in WHERE: ${column}`);
      }
      whereClause.push(`${column} = ?`);
      params.push(value);
    }
    
    const query = `UPDATE ${table} SET ${setClause.join(', ')} WHERE ${whereClause.join(' AND ')}`;
    
    return { query, params };
  },
  
  /**
   * Build a DELETE query
   * @param {string} table - Table name
   * @param {Object} conditions - WHERE conditions
   * @returns {Object} - {query, params}
   */
  delete: (table, conditions) => {
    // Validate table name
    if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(table)) {
      throw new Error('Invalid table name');
    }
    
    if (Object.keys(conditions).length === 0) {
      throw new Error('DELETE queries must have WHERE conditions');
    }
    
    const whereClause = [];
    const params = [];
    
    // Build WHERE clause
    for (const [column, value] of Object.entries(conditions)) {
      if (!/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(column)) {
        throw new Error(`Invalid column name in WHERE: ${column}`);
      }
      whereClause.push(`${column} = ?`);
      params.push(value);
    }
    
    const query = `DELETE FROM ${table} WHERE ${whereClause.join(' AND ')}`;
    
    return { query, params };
  }
};

/**
 * Secure wrapper for callback-based transactions
 * @param {Function} callback - Async function that receives a connection
 * @returns {Promise} - Transaction result
 */
const secureExecuteTransactionCallback = async (callback) => {
  const connection = await secureGetConnection();
  
  try {
    await connection.beginTransaction();
    
    const result = await callback(connection);
    
    await connection.commit();
    return result;
  } catch (error) {
    await connection.rollback();
    throw error;
  } finally {
    connection.release();
  }
};

module.exports = {
  secureExecuteQuery,
  secureExecuteTransaction,
  secureExecuteTransactionCallback,
  secureGetConnection,
  validateQueryStructure,
  validateParameters,
  queryBuilders
};