<?php
/**
 * Address Model Class
 * 
 * Comprehensive Address model that provides complete address management functionality
 * for shipping and billing addresses. Handles address CRUD operations, validation,
 * formatting, and default address management. Maintains API compatibility with the
 * existing Node.js backend and integrates with the Order model.
 * 
 * Requirements: 6.1
 */

require_once __DIR__ . '/Database.php';

// Conditionally load utilities if they exist
if (file_exists(__DIR__ . '/../utils/Logger.php')) {
    require_once __DIR__ . '/../utils/Logger.php';
}
if (file_exists(__DIR__ . '/../utils/Response.php')) {
    require_once __DIR__ . '/../utils/Response.php';
}
if (file_exists(__DIR__ . '/../utils/InputValidator.php')) {
    require_once __DIR__ . '/../utils/InputValidator.php';
}

class Address extends DatabaseModel {
    protected $db;
    
    // Address type constants
    const TYPE_HOME = 'home';
    const TYPE_WORK = 'work';
    const TYPE_OTHER = 'other';
    
    // Validation rules
    private $validationRules = [
        'user_id' => ['required', 'integer'],
        'type' => ['required', 'in:home,work,other'],
        'first_name' => ['required', 'string', 'max:100'],
        'last_name' => ['required', 'string', 'max:100'],
        'address_line1' => ['required', 'string', 'max:255'],
        'address_line2' => ['string', 'max:255'],
        'city' => ['required', 'string', 'max:100'],
        'state' => ['required', 'string', 'max:100'],
        'postal_code' => ['required', 'string', 'max:20'],
        'country' => ['string', 'max:100'],
        'phone' => ['string', 'max:20'],
        'is_default' => ['boolean']
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct('addresses');
        $this->setPrimaryKey('id');
        $this->db = Database::getInstance();
    }
    
    /**
     * Safe logging method that handles missing Logger class
     */
    private function safeLog($level, $message, $context = []) {
        if (class_exists('Logger')) {
            switch ($level) {
                case 'info':
                    Logger::info($message, $context);
                    break;
                case 'error':
                    Logger::error($message, $context);
                    break;
                case 'warning':
                    Logger::warning($message, $context);
                    break;
                default:
                    Logger::info($message, $context);
            }
        }
        // If Logger doesn't exist, silently continue
    }
    
    /**
     * Create a new address
     * 
     * @param array $addressData Address data
     * @return array Created address data
     * @throws Exception If creation fails
     */
    public function createAddress($addressData) {
        try {
            $this->beginTransaction();
            
            // Validate address data
            $this->validateAddressData($addressData);
            
            // Set default values
            $addressData['country'] = $addressData['country'] ?? 'India';
            $addressData['is_default'] = $addressData['is_default'] ?? false;
            
            // Handle default address logic
            if ($addressData['is_default']) {
                $this->clearDefaultAddress($addressData['user_id']);
            }
            
            // Format address data
            $formattedData = $this->formatAddressData($addressData);
            
            // Insert address
            $addressId = $this->insert($formattedData);
            
            // Get created address
            $address = $this->getAddressById($addressId);
            
            $this->commit();
            
            $this->safeLog('info', 'Address created successfully', [
                'address_id' => $addressId,
                'user_id' => $addressData['user_id'],
                'type' => $addressData['type'],
                'is_default' => $addressData['is_default']
            ]);
            
            return $address;
            
        } catch (Exception $e) {
            $this->rollback();
            $this->safeLog('error', 'Address creation failed', [
                'user_id' => $addressData['user_id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get address by ID
     * 
     * @param int $addressId Address ID
     * @return array|null Address data or null if not found
     */
    public function getAddressById($addressId) {
        try {
            $address = $this->find($addressId);
            
            if (!$address) {
                return null;
            }
            
            return $this->sanitizeAddressData($address);
            
        } catch (Exception $e) {
            $this->safeLog('error', 'Failed to get address by ID', [
                'address_id' => $addressId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get addresses by user ID
     * 
     * @param int $userId User ID
     * @param array $filters Filter criteria
     * @return array User addresses
     */
    public function getAddressesByUser($userId, $filters = []) {
        try {
            $conditions = ['user_id' => $userId];
            
            // Add type filter
            if (!empty($filters['type'])) {
                $conditions['type'] = $filters['type'];
            }
            
            // Add default filter
            if (isset($filters['is_default'])) {
                $conditions['is_default'] = $filters['is_default'] ? 1 : 0;
            }
            
            $addresses = $this->where($conditions, null, null, 'is_default DESC, created_at DESC');
            
            // Sanitize address data
            $sanitizedAddresses = array_map([$this, 'sanitizeAddressData'], $addresses);
            
            return $sanitizedAddresses;
            
        } catch (Exception $e) {
            $this->safeLog('error', 'Failed to get addresses by user', [
                'user_id' => $userId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get user's default address
     * 
     * @param int $userId User ID
     * @return array|null Default address or null if not found
     */
    public function getDefaultAddress($userId) {
        try {
            $address = $this->first([
                'user_id' => $userId,
                'is_default' => 1
            ]);
            
            if (!$address) {
                return null;
            }
            
            return $this->sanitizeAddressData($address);
            
        } catch (Exception $e) {
            $this->safeLog('error', 'Failed to get default address', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Update address
     * 
     * @param int $addressId Address ID
     * @param array $addressData Updated address data
     * @return array Updated address data
     * @throws Exception If update fails
     */
    public function updateAddress($addressId, $addressData) {
        try {
            $this->beginTransaction();
            
            // Get existing address
            $existingAddress = $this->find($addressId);
            if (!$existingAddress) {
                throw new Exception('Address not found', 404);
            }
            
            // Validate address data (excluding user_id for updates)
            $updateRules = $this->validationRules;
            unset($updateRules['user_id']); // Don't allow changing user_id
            $this->validateAddressData($addressData, $updateRules);
            
            // Handle default address logic
            if (isset($addressData['is_default']) && $addressData['is_default']) {
                $this->clearDefaultAddress($existingAddress['user_id']);
            }
            
            // Format address data
            $formattedData = $this->formatAddressData($addressData);
            
            // Update address
            $updated = $this->updateById($addressId, $formattedData);
            
            if (!$updated) {
                throw new Exception('Failed to update address', 500);
            }
            
            // Get updated address
            $address = $this->getAddressById($addressId);
            
            $this->commit();
            
            $this->safeLog('info', 'Address updated successfully', [
                'address_id' => $addressId,
                'user_id' => $existingAddress['user_id']
            ]);
            
            return $address;
            
        } catch (Exception $e) {
            $this->rollback();
            $this->safeLog('error', 'Address update failed', [
                'address_id' => $addressId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Delete address
     * 
     * @param int $addressId Address ID
     * @param int $userId User ID (for authorization)
     * @return bool Success status
     * @throws Exception If deletion fails
     */
    public function deleteAddress($addressId, $userId) {
        try {
            $this->beginTransaction();
            
            // Get existing address
            $existingAddress = $this->find($addressId);
            if (!$existingAddress) {
                throw new Exception('Address not found', 404);
            }
            
            // Check ownership
            if ($existingAddress['user_id'] != $userId) {
                throw new Exception('Unauthorized to delete this address', 403);
            }
            
            // Check if address is being used in orders
            if ($this->isAddressInUse($addressId)) {
                throw new Exception('Cannot delete address that is used in orders', 400);
            }
            
            // Delete address
            $deleted = $this->deleteById($addressId);
            
            if (!$deleted) {
                throw new Exception('Failed to delete address', 500);
            }
            
            $this->commit();
            
            $this->safeLog('info', 'Address deleted successfully', [
                'address_id' => $addressId,
                'user_id' => $userId
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->rollback();
            $this->safeLog('error', 'Address deletion failed', [
                'address_id' => $addressId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Set address as default
     * 
     * @param int $addressId Address ID
     * @param int $userId User ID (for authorization)
     * @return array Updated address data
     * @throws Exception If update fails
     */
    public function setDefaultAddress($addressId, $userId) {
        try {
            $this->beginTransaction();
            
            // Get existing address
            $existingAddress = $this->find($addressId);
            if (!$existingAddress) {
                throw new Exception('Address not found', 404);
            }
            
            // Check ownership
            if ($existingAddress['user_id'] != $userId) {
                throw new Exception('Unauthorized to modify this address', 403);
            }
            
            // Clear current default
            $this->clearDefaultAddress($userId);
            
            // Set new default
            $this->updateById($addressId, ['is_default' => 1]);
            
            // Get updated address
            $address = $this->getAddressById($addressId);
            
            $this->commit();
            
            $this->safeLog('info', 'Default address updated', [
                'address_id' => $addressId,
                'user_id' => $userId
            ]);
            
            return $address;
            
        } catch (Exception $e) {
            $this->rollback();
            $this->safeLog('error', 'Failed to set default address', [
                'address_id' => $addressId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Get formatted address string
     * 
     * @param array $address Address data
     * @return string Formatted address
     */
    public function getFormattedAddress($address) {
        $parts = [];
        
        // Name
        $name = trim($address['first_name'] . ' ' . $address['last_name']);
        if ($name) {
            $parts[] = $name;
        }
        
        // Address lines
        if (!empty($address['address_line1'])) {
            $parts[] = $address['address_line1'];
        }
        
        if (!empty($address['address_line2'])) {
            $parts[] = $address['address_line2'];
        }
        
        // City, State, Postal Code
        $cityStateParts = [];
        if (!empty($address['city'])) {
            $cityStateParts[] = $address['city'];
        }
        if (!empty($address['state'])) {
            $cityStateParts[] = $address['state'];
        }
        if (!empty($address['postal_code'])) {
            $cityStateParts[] = $address['postal_code'];
        }
        
        if (!empty($cityStateParts)) {
            $parts[] = implode(', ', $cityStateParts);
        }
        
        // Country
        if (!empty($address['country']) && $address['country'] !== 'India') {
            $parts[] = $address['country'];
        }
        
        // Phone
        if (!empty($address['phone'])) {
            $parts[] = 'Phone: ' . $address['phone'];
        }
        
        return implode("\n", $parts);
    }
    
    /**
     * Validate postal code format
     * 
     * @param string $postalCode Postal code
     * @param string $country Country
     * @return bool True if valid
     */
    public function validatePostalCode($postalCode, $country = 'India') {
        switch (strtolower($country)) {
            case 'india':
                // Indian postal code: 6 digits
                return preg_match('/^\d{6}$/', $postalCode);
            
            case 'usa':
            case 'united states':
                // US ZIP code: 5 digits or 5+4 format
                return preg_match('/^\d{5}(-\d{4})?$/', $postalCode);
            
            case 'uk':
            case 'united kingdom':
                // UK postal code format
                return preg_match('/^[A-Z]{1,2}\d[A-Z\d]?\s?\d[A-Z]{2}$/i', $postalCode);
            
            default:
                // Generic validation: alphanumeric with spaces and hyphens
                return preg_match('/^[A-Z0-9\s\-]{3,20}$/i', $postalCode);
        }
    }
    
    /**
     * Validate phone number format
     * 
     * @param string $phone Phone number
     * @return bool True if valid
     */
    public function validatePhoneNumber($phone) {
        // Remove all non-digit characters
        $cleanPhone = preg_replace('/\D/', '', $phone);
        
        // Indian mobile number: 10 digits starting with 6-9
        if (preg_match('/^[6-9]\d{9}$/', $cleanPhone)) {
            return true;
        }
        
        // Indian landline with STD code: 10-11 digits
        if (preg_match('/^\d{10,11}$/', $cleanPhone)) {
            return true;
        }
        
        // International format: 7-15 digits
        if (preg_match('/^\d{7,15}$/', $cleanPhone)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Clear default address for user
     * 
     * @param int $userId User ID
     */
    private function clearDefaultAddress($userId) {
        $this->update(['user_id' => $userId, 'is_default' => 1], ['is_default' => 0]);
    }
    
    /**
     * Check if address is being used in orders
     * 
     * @param int $addressId Address ID
     * @return bool True if in use
     */
    private function isAddressInUse($addressId) {
        $sql = "SELECT COUNT(*) FROM orders 
                WHERE shipping_address_id = ? OR billing_address_id = ?";
        
        $count = (int)$this->db->fetchColumn($sql, [$addressId, $addressId]);
        
        return $count > 0;
    }
    
    /**
     * Validate address data
     * 
     * @param array $addressData Address data to validate
     * @param array $rules Validation rules (optional)
     * @throws Exception If validation fails
     */
    private function validateAddressData($addressData, $rules = null) {
        $rules = $rules ?? $this->validationRules;
        $errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $addressData[$field] ?? null;
            
            foreach ($fieldRules as $rule) {
                if ($rule === 'required' && empty($value)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
                    continue;
                }
                
                if (empty($value)) {
                    continue; // Skip other validations if value is empty and not required
                }
                
                if ($rule === 'integer' && !is_numeric($value)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be a number';
                }
                
                if ($rule === 'string' && !is_string($value)) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be a string';
                }
                
                if ($rule === 'boolean' && !is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false])) {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be true or false';
                }
                
                if (strpos($rule, 'max:') === 0) {
                    $maxLength = (int)substr($rule, 4);
                    if (strlen($value) > $maxLength) {
                        $errors[] = ucfirst(str_replace('_', ' ', $field)) . " cannot exceed {$maxLength} characters";
                    }
                }
                
                if (strpos($rule, 'in:') === 0) {
                    $allowedValues = explode(',', substr($rule, 3));
                    if (!in_array($value, $allowedValues)) {
                        $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be one of: ' . implode(', ', $allowedValues);
                    }
                }
            }
        }
        
        // Custom validations
        if (!empty($addressData['postal_code']) && !empty($addressData['country'])) {
            if (!$this->validatePostalCode($addressData['postal_code'], $addressData['country'])) {
                $errors[] = 'Invalid postal code format for ' . $addressData['country'];
            }
        }
        
        if (!empty($addressData['phone'])) {
            if (!$this->validatePhoneNumber($addressData['phone'])) {
                $errors[] = 'Invalid phone number format';
            }
        }
        
        if (!empty($errors)) {
            throw new Exception('Validation failed: ' . implode(', ', $errors), 400);
        }
    }
    
    /**
     * Format address data for storage
     * 
     * @param array $addressData Raw address data
     * @return array Formatted address data
     */
    private function formatAddressData($addressData) {
        $formatted = [];
        
        // Copy allowed fields
        $allowedFields = [
            'user_id', 'type', 'first_name', 'last_name', 'address_line1', 
            'address_line2', 'city', 'state', 'postal_code', 'country', 
            'phone', 'is_default'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($addressData[$field])) {
                $formatted[$field] = $addressData[$field];
            }
        }
        
        // Format names
        if (isset($formatted['first_name'])) {
            $formatted['first_name'] = trim(ucwords(strtolower($formatted['first_name'])));
        }
        
        if (isset($formatted['last_name'])) {
            $formatted['last_name'] = trim(ucwords(strtolower($formatted['last_name'])));
        }
        
        // Format city and state
        if (isset($formatted['city'])) {
            $formatted['city'] = trim(ucwords(strtolower($formatted['city'])));
        }
        
        if (isset($formatted['state'])) {
            $formatted['state'] = trim(ucwords(strtolower($formatted['state'])));
        }
        
        // Format country
        if (isset($formatted['country'])) {
            $formatted['country'] = trim(ucwords(strtolower($formatted['country'])));
        }
        
        // Format postal code
        if (isset($formatted['postal_code'])) {
            $formatted['postal_code'] = strtoupper(trim($formatted['postal_code']));
        }
        
        // Format phone number
        if (isset($formatted['phone'])) {
            $formatted['phone'] = preg_replace('/\D/', '', $formatted['phone']);
            if (strlen($formatted['phone']) === 10 && !str_starts_with($formatted['phone'], '+')) {
                $formatted['phone'] = '+91' . $formatted['phone']; // Add India country code
            }
        }
        
        // Convert boolean
        if (isset($formatted['is_default'])) {
            $formatted['is_default'] = $formatted['is_default'] ? 1 : 0;
        }
        
        return $formatted;
    }
    
    /**
     * Sanitize address data for API response
     * 
     * @param array $address Address data
     * @return array Sanitized address data
     */
    private function sanitizeAddressData($address) {
        return [
            'id' => (int)$address['id'],
            'user_id' => (int)$address['user_id'],
            'type' => $address['type'],
            'first_name' => $address['first_name'],
            'last_name' => $address['last_name'],
            'address_line1' => $address['address_line1'],
            'address_line2' => $address['address_line2'],
            'city' => $address['city'],
            'state' => $address['state'],
            'postal_code' => $address['postal_code'],
            'country' => $address['country'],
            'phone' => $address['phone'],
            'is_default' => (bool)$address['is_default'],
            'formatted_address' => $this->getFormattedAddress($address),
            'created_at' => $address['created_at'],
            'updated_at' => $address['updated_at']
        ];
    }
}