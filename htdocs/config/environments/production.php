<?php
/**
 * Production Environment Configuration
 * 
 * This file contains configuration overrides specific to the production environment.
 * These settings prioritize security, performance, and reliability.
 */

return [
    // Disable debug mode and minimize error exposure
    'debug' => false,
    'maintenance_mode' => false,
    
    // Production-specific features
    'features' => [
        'debug_toolbar' => false,
        'query_logging' => false,
        'profiling' => false,
        'mock_payments' => false,
        'test_emails' => false
    ],
    
    // Enhanced security for production
    'security' => [
        'strict_transport_security' => true,
        'content_security_policy' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https:; connect-src 'self' https:; frame-ancestors 'none';",
        'force_https' => true,
        'hsts_max_age' => 31536000, // 1 year
        'include_subdomains' => true
    ],
    
    // Production database settings
    'database' => [
        'log_queries' => false,
        'slow_query_threshold' => 1000, // Log queries slower than 1 second
        'explain_queries' => false,
        'connection_pooling' => true,
        'read_write_splitting' => true
    ],
    
    // Production email settings
    'email' => [
        'log_emails' => false,
        'catch_all_email' => null,
        'disable_delivery' => false,
        'queue_emails' => true,
        'retry_failed' => true
    ],
    
    // Production payment settings
    'payment' => [
        'test_mode' => false,
        'mock_responses' => false,
        'log_transactions' => true,
        'fraud_detection' => true,
        'webhook_verification' => true
    ],
    
    // Production logging
    'logging' => [
        'level' => 'warning',
        'include_stack_traces' => false,
        'log_sql_queries' => false,
        'log_api_requests' => false,
        'compress_logs' => true,
        'remote_logging' => false // Set to true if using external log service
    ],
    
    // Production file upload settings
    'upload' => [
        'security_scanning' => true,
        'virus_scanning' => true,
        'allow_test_files' => false,
        'quarantine_suspicious' => true
    ],
    
    // Production API settings
    'api' => [
        'rate_limiting' => true,
        'cors_origins' => ['https://riyacollections.com', 'https://www.riyacollections.com'],
        'detailed_errors' => false,
        'request_logging' => false
    ],
    
    // Production cache settings
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour
        'compression' => true,
        'distributed' => false // Set to true if using Redis/Memcached
    ],
    
    // Production performance settings
    'performance' => [
        'gzip_compression' => true,
        'asset_minification' => true,
        'cdn_enabled' => false, // Set to true if using CDN
        'image_optimization' => true
    ],
    
    // Production monitoring
    'monitoring' => [
        'health_checks' => true,
        'performance_monitoring' => true,
        'error_tracking' => true,
        'uptime_monitoring' => true
    ]
];