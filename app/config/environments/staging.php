<?php
/**
 * Staging Environment Configuration
 * 
 * This file contains configuration overrides specific to the staging environment.
 * These settings balance production-like behavior with debugging capabilities.
 */

return [
    // Enable limited debug mode for staging
    'debug' => true,
    'maintenance_mode' => false,
    
    // Staging-specific features
    'features' => [
        'debug_toolbar' => true,
        'query_logging' => true,
        'profiling' => true,
        'mock_payments' => false, // Use real payment gateway in test mode
        'test_emails' => false
    ],
    
    // Moderate security for staging
    'security' => [
        'strict_transport_security' => true,
        'content_security_policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;",
        'force_https' => true,
        'basic_auth' => [ // Optional basic auth for staging
            'enabled' => false,
            'username' => 'staging',
            'password' => 'staging123'
        ]
    ],
    
    // Staging database settings
    'database' => [
        'log_queries' => true,
        'slow_query_threshold' => 500, // Log queries slower than 500ms
        'explain_queries' => true,
        'backup_enabled' => true
    ],
    
    // Staging email settings
    'email' => [
        'log_emails' => true,
        'catch_all_email' => 'staging@riyacollections.com',
        'disable_delivery' => false,
        'queue_emails' => true,
        'test_recipients' => ['staging@riyacollections.com', 'developer@riyacollections.com']
    ],
    
    // Staging payment settings
    'payment' => [
        'test_mode' => true, // Use test mode for Razorpay
        'mock_responses' => false,
        'log_transactions' => true,
        'webhook_verification' => true
    ],
    
    // Staging logging
    'logging' => [
        'level' => 'info',
        'include_stack_traces' => true,
        'log_sql_queries' => true,
        'log_api_requests' => true,
        'remote_logging' => false
    ],
    
    // Staging file upload settings
    'upload' => [
        'security_scanning' => true,
        'virus_scanning' => false, // Disable for staging to save resources
        'allow_test_files' => true,
        'quarantine_suspicious' => false
    ],
    
    // Staging API settings
    'api' => [
        'rate_limiting' => true,
        'cors_origins' => ['https://staging.riyacollections.com', 'http://localhost:3000'],
        'detailed_errors' => true,
        'request_logging' => true
    ],
    
    // Staging cache settings
    'cache' => [
        'enabled' => true,
        'ttl' => 300, // 5 minutes
        'compression' => false
    ],
    
    // Staging performance settings
    'performance' => [
        'gzip_compression' => true,
        'asset_minification' => false, // Keep unminified for debugging
        'cdn_enabled' => false,
        'image_optimization' => true
    ],
    
    // Staging monitoring
    'monitoring' => [
        'health_checks' => true,
        'performance_monitoring' => true,
        'error_tracking' => true,
        'uptime_monitoring' => false
    ],
    
    // Staging-specific URLs and endpoints
    'urls' => [
        'frontend' => 'https://staging.riyacollections.com',
        'api' => 'https://api-staging.riyacollections.com',
        'admin' => 'https://admin-staging.riyacollections.com'
    ]
];