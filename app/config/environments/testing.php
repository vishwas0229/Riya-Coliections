<?php
/**
 * Testing Environment Configuration
 * 
 * This file contains configuration overrides specific to the testing environment.
 * These settings optimize for test execution speed and isolation.
 */

return [
    // Enable debug mode for testing
    'debug' => true,
    'maintenance_mode' => false,
    
    // Testing-specific features
    'features' => [
        'debug_toolbar' => false,
        'query_logging' => false,
        'profiling' => false,
        'mock_payments' => true,
        'test_emails' => true,
        'fake_external_apis' => true
    ],
    
    // Relaxed security for testing
    'security' => [
        'strict_transport_security' => false,
        'content_security_policy' => "default-src 'self' 'unsafe-inline' 'unsafe-eval';",
        'force_https' => false,
        'rate_limiting' => false
    ],
    
    // Testing database settings
    'database' => [
        'default' => 'testing',
        'connections' => [
            'testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:', // In-memory database for speed
                'prefix' => '',
                'foreign_key_constraints' => true
            ]
        ],
        'log_queries' => false,
        'slow_query_threshold' => 5000, // Very high threshold for tests
        'explain_queries' => false
    ],
    
    // Testing email settings
    'email' => [
        'default' => 'array', // Store emails in array instead of sending
        'log_emails' => true,
        'catch_all_email' => 'test@example.com',
        'disable_delivery' => true,
        'queue_emails' => false
    ],
    
    // Testing payment settings
    'payment' => [
        'test_mode' => true,
        'mock_responses' => true,
        'log_transactions' => false,
        'fake_gateway' => true,
        'auto_success' => true // Automatically succeed all test payments
    ],
    
    // Testing logging
    'logging' => [
        'level' => 'error', // Only log errors during tests
        'include_stack_traces' => true,
        'log_sql_queries' => false,
        'log_api_requests' => false,
        'channels' => [
            'file' => [
                'path' => __DIR__ . '/../../logs/test.log'
            ]
        ]
    ],
    
    // Testing file upload settings
    'upload' => [
        'disk' => 'testing',
        'disks' => [
            'testing' => [
                'driver' => 'local',
                'root' => sys_get_temp_dir() . '/riya_collections_test',
                'visibility' => 'public'
            ]
        ],
        'security_scanning' => false,
        'virus_scanning' => false,
        'allow_test_files' => true
    ],
    
    // Testing API settings
    'api' => [
        'rate_limiting' => false,
        'cors_origins' => ['*'],
        'detailed_errors' => true,
        'request_logging' => false,
        'authentication' => [
            'bypass_for_tests' => true,
            'test_tokens' => [
                'valid_user_token' => 'test_user_token_123',
                'admin_token' => 'test_admin_token_456'
            ]
        ]
    ],
    
    // Testing cache settings
    'cache' => [
        'enabled' => false, // Disable caching for consistent test results
        'ttl' => 1, // Very short TTL when enabled
        'driver' => 'array' // In-memory cache
    ],
    
    // Testing session settings
    'session' => [
        'driver' => 'array', // In-memory sessions
        'lifetime' => 120,
        'expire_on_close' => true
    ],
    
    // Testing queue settings
    'queue' => [
        'default' => 'sync', // Process jobs synchronously in tests
        'connections' => [
            'sync' => [
                'driver' => 'sync'
            ]
        ]
    ],
    
    // Testing external services
    'external_services' => [
        'mock_all' => true,
        'razorpay' => [
            'mock' => true,
            'auto_success' => true
        ],
        'email_service' => [
            'mock' => true,
            'capture_emails' => true
        ]
    ]
];