<?php
/**
 * JWT Configuration and Utilities
 * 
 * This module provides JWT token generation, validation, and management
 * compatible with the existing Node.js implementation while using PHP.
 * 
 * Requirements: 3.1, 3.2, 17.1
 */

require_once __DIR__ . '/environment.php';

/**
 * JWT Configuration Class
 */
class JWTConfig {
    private static $config = null;
    
    /**
     * Get JWT configuration
     */
    public static function getConfig() {
        if (self::$config === null) {
            self::$config = [
                'secret' => env('JWT_SECRET', 'fallback_secret_change_in_production'),
                'expires_in' => env('JWT_EXPIRES_IN', '24h'),
                'refresh_secret' => env('JWT_REFRESH_SECRET', 'fallback_refresh_secret'),
                'refresh_expires_in' => env('JWT_REFRESH_EXPIRES_IN', '7d'),
                'issuer' => env('JWT_ISSUER', 'riya-collections'),
                'audience' => env('JWT_AUDIENCE', 'riya-collections-users'),
                'algorithm' => 'HS256'
            ];
        }
        
        return self::$config;
    }
    
    /**
     * Convert time string to seconds
     */
    public static function parseTimeString($timeString) {
        $units = [
            's' => 1,
            'm' => 60,
            'h' => 3600,
            'd' => 86400,
            'w' => 604800,
            'y' => 31536000
        ];
        
        if (is_numeric($timeString)) {
            return (int) $timeString;
        }
        
        $matches = [];
        if (preg_match('/^(\d+)([smhdwy])$/', $timeString, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];
            
            return $value * ($units[$unit] ?? 1);
        }
        
        return 3600; // Default to 1 hour
    }
}

/**
 * Simple JWT Implementation
 * Compatible with the Node.js jsonwebtoken library
 */
class JWT {
    /**
     * Base64 URL encode
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     */
    private static function base64UrlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
    
    /**
     * Generate JWT token
     */
    public static function encode($payload, $secret, $algorithm = 'HS256') {
        $header = [
            'typ' => 'JWT',
            'alg' => $algorithm
        ];
        
        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));
        
        $signature = self::sign($headerEncoded . '.' . $payloadEncoded, $secret, $algorithm);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }
    
    /**
     * Decode and verify JWT token
     */
    public static function decode($token, $secret, $algorithm = 'HS256') {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }
        
        list($headerEncoded, $payloadEncoded, $signature) = $parts;
        
        // Verify signature
        $expectedSignature = self::sign($headerEncoded . '.' . $payloadEncoded, $secret, $algorithm);
        
        if (!hash_equals($signature, $expectedSignature)) {
            throw new Exception('Invalid token signature');
        }
        
        // Decode header and payload
        $header = json_decode(self::base64UrlDecode($headerEncoded), true);
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);
        
        if (!$header || !$payload) {
            throw new Exception('Invalid token data');
        }
        
        // Verify algorithm
        if ($header['alg'] !== $algorithm) {
            throw new Exception('Invalid token algorithm');
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new Exception('Token has expired');
        }
        
        // Check not before
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            throw new Exception('Token not yet valid');
        }
        
        return $payload;
    }
    
    /**
     * Sign data with secret
     */
    private static function sign($data, $secret, $algorithm) {
        switch ($algorithm) {
            case 'HS256':
                return self::base64UrlEncode(hash_hmac('sha256', $data, $secret, true));
            case 'HS384':
                return self::base64UrlEncode(hash_hmac('sha384', $data, $secret, true));
            case 'HS512':
                return self::base64UrlEncode(hash_hmac('sha512', $data, $secret, true));
            default:
                throw new Exception('Unsupported algorithm: ' . $algorithm);
        }
    }
}

/**
 * JWT Service Class
 */
class JWTService {
    private $config;
    
    public function __construct() {
        $this->config = JWTConfig::getConfig();
    }
    
    /**
     * Generate access token
     */
    public function generateAccessToken($payload) {
        $now = time();
        $expiresIn = JWTConfig::parseTimeString($this->config['expires_in']);
        
        $tokenPayload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $expiresIn,
            'iss' => $this->config['issuer'],
            'aud' => $this->config['audience']
        ]);
        
        return JWT::encode($tokenPayload, $this->config['secret'], $this->config['algorithm']);
    }
    
    /**
     * Generate refresh token
     */
    public function generateRefreshToken($payload) {
        $now = time();
        $expiresIn = JWTConfig::parseTimeString($this->config['refresh_expires_in']);
        
        $tokenPayload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + $expiresIn,
            'iss' => $this->config['issuer'],
            'aud' => $this->config['audience'],
            'type' => 'refresh'
        ]);
        
        return JWT::encode($tokenPayload, $this->config['refresh_secret'], $this->config['algorithm']);
    }
    
    /**
     * Verify access token
     */
    public function verifyAccessToken($token) {
        try {
            return JWT::decode($token, $this->config['secret'], $this->config['algorithm']);
        } catch (Exception $e) {
            throw new Exception('Invalid or expired access token: ' . $e->getMessage());
        }
    }
    
    /**
     * Verify refresh token
     */
    public function verifyRefreshToken($token) {
        try {
            $payload = JWT::decode($token, $this->config['refresh_secret'], $this->config['algorithm']);
            
            if (!isset($payload['type']) || $payload['type'] !== 'refresh') {
                throw new Exception('Invalid refresh token type');
            }
            
            return $payload;
        } catch (Exception $e) {
            throw new Exception('Invalid or expired refresh token: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate token pair (access + refresh)
     */
    public function generateTokenPair($payload) {
        // Add a unique identifier to prevent token reuse
        $jti = bin2hex(random_bytes(8));
        $payloadWithJti = array_merge($payload, ['jti' => $jti]);
        
        $accessToken = $this->generateAccessToken($payloadWithJti);
        $refreshToken = $this->generateRefreshToken($payloadWithJti);
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => JWTConfig::parseTimeString($this->config['expires_in'])
        ];
    }
    
    /**
     * Extract token from Authorization header
     */
    public function extractTokenFromHeader($authHeader = null) {
        if ($authHeader === null) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        }
        
        if (empty($authHeader)) {
            return null;
        }
        
        // Check for Bearer token format
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Get current user from token
     */
    public function getCurrentUser() {
        $token = $this->extractTokenFromHeader();
        
        if (!$token) {
            return null;
        }
        
        try {
            $payload = $this->verifyAccessToken($token);
            return $payload;
        } catch (Exception $e) {
            Logger::warning('Invalid token in request', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken($refreshToken) {
        try {
            $payload = $this->verifyRefreshToken($refreshToken);
            
            // Remove refresh-specific claims
            unset($payload['type'], $payload['iat'], $payload['exp']);
            
            // Generate new access token
            return $this->generateAccessToken($payload);
            
        } catch (Exception $e) {
            throw new Exception('Token refresh failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Validate token format
     */
    public function isValidTokenFormat($token) {
        if (empty($token) || !is_string($token)) {
            return false;
        }
        
        $parts = explode('.', $token);
        return count($parts) === 3;
    }
}

/**
 * Password hashing utilities (compatible with bcrypt)
 */
class PasswordHash {
    /**
     * Hash password using bcrypt
     */
    public static function hash($password) {
        $rounds = (int) env('BCRYPT_SALT_ROUNDS', 12);
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $rounds]);
    }
    
    /**
     * Verify password against hash
     */
    public static function verify($password, $hash) {
        if ($password === null) {
            return false;
        }
        return password_verify($password, $hash);
    }
    
    /**
     * Check if hash needs rehashing (if cost has changed)
     */
    public static function needsRehash($hash) {
        $rounds = (int) env('BCRYPT_SALT_ROUNDS', 12);
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => $rounds]);
    }
}

// Global helper functions
function getJWTService() {
    static $service = null;
    if ($service === null) {
        $service = new JWTService();
    }
    return $service;
}

function generateTokenPair($payload) {
    return getJWTService()->generateTokenPair($payload);
}

function verifyAccessToken($token) {
    return getJWTService()->verifyAccessToken($token);
}