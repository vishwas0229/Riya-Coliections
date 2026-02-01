<?php
/**
 * Database Model Class
 * 
 * This class provides a high-level interface for database operations,
 * building on top of the core Database connection class. It includes
 * additional features like query building, caching, and ORM-like functionality.
 * 
 * Requirements: 2.1, 2.2, 12.1
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';

/**
 * Database Model Class
 */
class DatabaseModel {
    private $db;
    private $table;
    private $primaryKey = 'id';
    private $timestamps = true;
    private $queryBuilder;
    
    /**
     * Constructor
     */
    public function __construct($table = null) {
        $this->db = Database::getInstance();
        $this->table = $table;
        $this->queryBuilder = new QueryBuilder();
    }
    
    /**
     * Set table name
     */
    public function setTable($table) {
        $this->table = $table;
        return $this;
    }
    
    /**
     * Set primary key
     */
    public function setPrimaryKey($key) {
        $this->primaryKey = $key;
        return $this;
    }
    
    /**
     * Find record by ID
     */
    public function find($id) {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Find records with conditions
     */
    public function where($conditions = [], $limit = null, $offset = null, $orderBy = null) {
        $sql = "SELECT * FROM `{$this->table}`";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                if (is_array($value)) {
                    // Handle IN clauses
                    $placeholders = str_repeat('?,', count($value) - 1) . '?';
                    $whereClauses[] = "`{$field}` IN ({$placeholders})";
                    $params = array_merge($params, $value);
                } else {
                    $whereClauses[] = "`{$field}` = ?";
                    $params[] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $sql .= " LIMIT ?";
            $params[] = (int)$limit;
            
            if ($offset) {
                $sql .= " OFFSET ?";
                $params[] = (int)$offset;
            }
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Find first record with conditions
     */
    public function first($conditions = []) {
        $results = $this->where($conditions, 1);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Get all records
     */
    public function all($limit = null, $offset = null, $orderBy = null) {
        return $this->where([], $limit, $offset, $orderBy);
    }
    
    /**
     * Count records
     */
    public function count($conditions = []) {
        $sql = "SELECT COUNT(*) FROM `{$this->table}`";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                $whereClauses[] = "`{$field}` = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        return (int)$this->db->fetchColumn($sql, $params);
    }
    
    /**
     * Insert new record
     */
    public function insert($data) {
        // Add timestamps if enabled
        if ($this->timestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $fields) . "`) VALUES ({$placeholders})";
        
        $this->db->executeQuery($sql, array_values($data));
        return $this->db->getLastInsertId();
    }
    
    /**
     * Update records
     */
    public function update($conditions, $data) {
        // Add updated timestamp if enabled
        if ($this->timestamps && !isset($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $setClauses = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            $setClauses[] = "`{$field}` = ?";
            $params[] = $value;
        }
        
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $setClauses);
        
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                $whereClauses[] = "`{$field}` = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Update record by ID
     */
    public function updateById($id, $data) {
        return $this->update([$this->primaryKey => $id], $data);
    }
    
    /**
     * Delete records
     */
    public function delete($conditions) {
        $sql = "DELETE FROM `{$this->table}`";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                $whereClauses[] = "`{$field}` = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        } else {
            throw new Exception("Delete operation requires conditions to prevent accidental data loss");
        }
        
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Delete record by ID
     */
    public function deleteById($id) {
        return $this->delete([$this->primaryKey => $id]);
    }
    
    /**
     * Soft delete (mark as deleted)
     */
    public function softDelete($conditions) {
        return $this->update($conditions, ['deleted_at' => date('Y-m-d H:i:s')]);
    }
    
    /**
     * Execute raw SQL query
     */
    public function raw($sql, $params = []) {
        return $this->db->executeQuery($sql, $params);
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->db->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->db->rollback();
    }
    
    /**
     * Execute multiple operations in a transaction
     */
    public function transaction($callback) {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Check if record exists
     */
    public function exists($conditions) {
        return $this->count($conditions) > 0;
    }
    
    /**
     * Get paginated results
     */
    public function paginate($page = 1, $perPage = 20, $conditions = [], $orderBy = null) {
        $offset = ($page - 1) * $perPage;
        
        $data = $this->where($conditions, $perPage, $offset, $orderBy);
        $total = $this->count($conditions);
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage),
                'has_next' => $page < ceil($total / $perPage),
                'has_prev' => $page > 1
            ]
        ];
    }
    
    /**
     * Bulk insert records
     */
    public function bulkInsert($records) {
        if (empty($records)) {
            return 0;
        }
        
        $this->beginTransaction();
        
        try {
            $insertedCount = 0;
            
            foreach ($records as $record) {
                $this->insert($record);
                $insertedCount++;
            }
            
            $this->commit();
            
            Logger::info('Bulk insert completed', [
                'table' => $this->table,
                'records_inserted' => $insertedCount
            ]);
            
            return $insertedCount;
            
        } catch (Exception $e) {
            $this->rollback();
            Logger::error('Bulk insert failed', [
                'table' => $this->table,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get table schema information
     */
    public function getSchema() {
        $sql = "DESCRIBE `{$this->table}`";
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Truncate table
     */
    public function truncate() {
        $sql = "TRUNCATE TABLE `{$this->table}`";
        return $this->db->executeQuery($sql);
    }
}

/**
 * Simple Query Builder Class
 */
class QueryBuilder {
    private $select = '*';
    private $from = '';
    private $joins = [];
    private $where = [];
    private $orderBy = [];
    private $groupBy = [];
    private $having = [];
    private $limit = null;
    private $offset = null;
    private $params = [];
    
    /**
     * Set SELECT clause
     */
    public function select($fields = '*') {
        $this->select = is_array($fields) ? implode(', ', $fields) : $fields;
        return $this;
    }
    
    /**
     * Set FROM clause
     */
    public function from($table) {
        $this->from = $table;
        return $this;
    }
    
    /**
     * Add JOIN clause
     */
    public function join($table, $condition, $type = 'INNER') {
        $this->joins[] = "{$type} JOIN {$table} ON {$condition}";
        return $this;
    }
    
    /**
     * Add LEFT JOIN clause
     */
    public function leftJoin($table, $condition) {
        return $this->join($table, $condition, 'LEFT');
    }
    
    /**
     * Add RIGHT JOIN clause
     */
    public function rightJoin($table, $condition) {
        return $this->join($table, $condition, 'RIGHT');
    }
    
    /**
     * Add WHERE clause
     */
    public function where($field, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->where[] = "{$field} {$operator} ?";
        $this->params[] = $value;
        return $this;
    }
    
    /**
     * Add WHERE IN clause
     */
    public function whereIn($field, $values) {
        $placeholders = str_repeat('?,', count($values) - 1) . '?';
        $this->where[] = "{$field} IN ({$placeholders})";
        $this->params = array_merge($this->params, $values);
        return $this;
    }
    
    /**
     * Add ORDER BY clause
     */
    public function orderBy($field, $direction = 'ASC') {
        $this->orderBy[] = "{$field} {$direction}";
        return $this;
    }
    
    /**
     * Add GROUP BY clause
     */
    public function groupBy($field) {
        $this->groupBy[] = $field;
        return $this;
    }
    
    /**
     * Add HAVING clause
     */
    public function having($condition) {
        $this->having[] = $condition;
        return $this;
    }
    
    /**
     * Set LIMIT clause
     */
    public function limit($limit) {
        $this->limit = $limit;
        return $this;
    }
    
    /**
     * Set OFFSET clause
     */
    public function offset($offset) {
        $this->offset = $offset;
        return $this;
    }
    
    /**
     * Build the SQL query
     */
    public function build() {
        $sql = "SELECT {$this->select}";
        
        if ($this->from) {
            $sql .= " FROM {$this->from}";
        }
        
        if (!empty($this->joins)) {
            $sql .= " " . implode(' ', $this->joins);
        }
        
        if (!empty($this->where)) {
            $sql .= " WHERE " . implode(' AND ', $this->where);
        }
        
        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . implode(', ', $this->groupBy);
        }
        
        if (!empty($this->having)) {
            $sql .= " HAVING " . implode(' AND ', $this->having);
        }
        
        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . implode(', ', $this->orderBy);
        }
        
        if ($this->limit) {
            $sql .= " LIMIT {$this->limit}";
        }
        
        if ($this->offset) {
            $sql .= " OFFSET {$this->offset}";
        }
        
        return $sql;
    }
    
    /**
     * Get query parameters
     */
    public function getParams() {
        return $this->params;
    }
    
    /**
     * Reset query builder
     */
    public function reset() {
        $this->select = '*';
        $this->from = '';
        $this->joins = [];
        $this->where = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->having = [];
        $this->limit = null;
        $this->offset = null;
        $this->params = [];
        return $this;
    }
}