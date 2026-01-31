<?php
/**
 * Input Validator Utility
 * 
 * Provides input validation and sanitization utilities for the application.
 * Helps prevent XSS, SQL injection, and other security vulnerabilities.
 * 
 * Requirements: 10.1, 16.1
 */

class InputValidator {
    /**
     * Sanitize input data recursively
     * 
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    public function sanitize($data) {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $sanitizedKey = $this->sanitizeString($key);
                $sanitized[$sanitizedKey] = $this->sanitize($value);
            }
            return $sanitized;
        } elseif (is_string($data)) {
            return $this->sanitizeString($data);
        } else {
            return $data;
        }
    }
    
    /**
     * Sanitize string input
     * 
     * @param string $input Input string
     * @return string Sanitized string
     */
    private function sanitizeString($input) {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        // Convert special characters to HTML entities
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $input;
    }
    
    /**
     * Validate email address
     * 
     * @param string $email Email to validate
     * @return bool True if valid
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate URL
     * 
     * @param string $url URL to validate
     * @return bool True if valid
     */
    public function validateUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Validate integer
     * 
     * @param mixed $value Value to validate
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @return bool True if valid
     */
    public function validateInteger($value, $min = null, $max = null) {
        if (!is_numeric($value)) {
            return false;
        }
        
        $intValue = (int)$value;
        
        if ($min !== null && $intValue < $min) {
            return false;
        }
        
        if ($max !== null && $intValue > $max) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate float
     * 
     * @param mixed $value Value to validate
     * @param float $min Minimum value
     * @param float $max Maximum value
     * @return bool True if valid
     */
    public function validateFloat($value, $min = null, $max = null) {
        if (!is_numeric($value)) {
            return false;
        }
        
        $floatValue = (float)$value;
        
        if ($min !== null && $floatValue < $min) {
            return false;
        }
        
        if ($max !== null && $floatValue > $max) {
            return false;
        }
        
        return true;
    }
}

/**
 * Get input validator instance
 * 
 * @return InputValidator
 */
function getInputValidator() {
    static $validator = null;
    if ($validator === null) {
        $validator = new InputValidator();
    }
    return $validator;
}