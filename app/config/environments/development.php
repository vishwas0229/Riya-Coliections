<?php
/**
 * Development Environment Configuration
 * 
 * This file contains configuration overrides specific to the development environment.
 * These settings prioritize debugging, logging, and development convenience over performance.
 */

return [
    // Enable debug mode and detailed error reporting
    'debug' => true,
    'maintenance_mode' => false,
    
    // Development-specific features
    'features' => [
        'debug_toolbar' => true,
        'query_logging' => true,
        'profiling' => true,
        'mock_payments' => true,
        'test_emails' => true
    ],
    
    // Relaxed security for development
    'security' => [
        'strict_transport_security' => false,
        'content_security_policy' => "default-src 'self' 'unsafe-inline' 'unsafe-eval'; img-src 'self' data: *;",
        'force_https' => false
    ],
    
    // Development database settings
    'database' => [
        'log_queries' => true,
        'slow_query_threshold' => 100, // Log queries slower than 100ms
        'explain_queries' => true
    ],
    
    // Development email settings
    'email' => [
        'log_emails' => true,
        'catch_all_email' => 'developer@localhost',
        'disable_delivery' => false // Set to true to prevent actual email sending
    ],
    
    // Development payment settings
    'payment' => [
        'test_mode' => true,
        'mock_responses' => true,
        'log_transactions' => true
    ],
    
    // Development logging
    'logging' => [
        'level' => 'debug',
        'include_stack_traces' => true,
        'log_sql_queries' => true,
        'log_api_requests' => true
    ],
    
    // Development file upload settings
    'upload' => [
        'security_scanning' => false,
        'virus_scanning' => false,
        'allow_test_files' => true
    ],
    
    // Development API settings
    'api' => [
        'rate_limiting' => false,
        'cors_origins' => ['http://localhost:3000', 'http://localhost:8080', 'http://127.0.0.1:3000'],
        'detailed_errors' => true
    ],
    
    // Development cache settings
    'cache' => [
        'enabled' => false, // Disable caching for development
        'ttl' => 60 // Short TTL when enabled
    ]
];