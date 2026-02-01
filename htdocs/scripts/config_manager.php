#!/usr/bin/env php
<?php
/**
 * Configuration Management CLI Tool
 * 
 * This script provides command-line interface for managing configuration,
 * validating settings, and performing maintenance tasks.
 * 
 * Usage:
 *   php config_manager.php validate
 *   php config_manager.php show [key]
 *   php config_manager.php set <key> <value>
 *   php config_manager.php credentials list
 *   php config_manager.php credentials rotate <key>
 *   php config_manager.php init
 *   php config_manager.php cache clear
 * 
 * Requirements: 14.2, 14.4
 */

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

// Set up paths
define('CONFIG_INIT_ALLOWED', true);
define('CONFIG_SKIP_AUTO_INIT', true);

require_once __DIR__ . '/../config/ConfigManager.php';
require_once __DIR__ . '/../services/ConfigValidationService.php';
require_once __DIR__ . '/../services/CredentialManager.php';
require_once __DIR__ . '/../config/init.php';

/**
 * Configuration Management CLI Class
 */
class ConfigManagerCLI {
    private $configManager;
    private $validator;
    private $credentialManager;
    private $commands;
    
    public function __construct() {
        $this->configManager = ConfigManager::getInstance();
        $this->validator = new ConfigValidationService();
        $this->credentialManager = CredentialManager::getInstance();
        
        $this->commands = [
            'validate' => 'Validate all configuration settings',
            'show' => 'Show configuration value(s)',
            'set' => 'Set configuration value',
            'init' => 'Initialize configuration system',
            'cache' => 'Manage configuration cache',
            'credentials' => 'Manage credentials',
            'export' => 'Export configuration',
            'import' => 'Import configuration',
            'help' => 'Show this help message'
        ];
    }
    
    /**
     * Run CLI command
     */
    public function run($args) {
        if (empty($args) || !isset($args[1])) {
            $this->showHelp();
            return 1;
        }
        
        $command = $args[1];
        $subArgs = array_slice($args, 2);
        
        try {
            switch ($command) {
                case 'validate':
                    return $this->validateConfig();
                    
                case 'show':
                    return $this->showConfig($subArgs);
                    
                case 'set':
                    return $this->setConfig($subArgs);
                    
                case 'init':
                    return $this->initConfig();
                    
                case 'cache':
                    return $this->manageCache($subArgs);
                    
                case 'credentials':
                    return $this->manageCredentials($subArgs);
                    
                case 'export':
                    return $this->exportConfig($subArgs);
                    
                case 'import':
                    return $this->importConfig($subArgs);
                    
                case 'help':
                    $this->showHelp();
                    return 0;
                    
                default:
                    $this->error("Unknown command: {$command}");
                    $this->showHelp();
                    return 1;
            }
            
        } catch (Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Validate configuration
     */
    private function validateConfig() {
        $this->info("Validating configuration...");
        
        $isValid = $this->validator->validateAll();
        
        if ($isValid) {
            $this->success("✓ Configuration validation passed");
            
            $warnings = $this->validator->getWarnings();
            if (!empty($warnings)) {
                $this->warning("Warnings found:");
                foreach ($warnings as $warning) {
                    $this->warning("  - {$warning['message']}");
                }
            }
            
            return 0;
        } else {
            $this->error("✗ Configuration validation failed");
            
            $errors = $this->validator->getErrors();
            foreach ($errors as $error) {
                $this->error("  - {$error['message']}");
            }
            
            return 1;
        }
    }
    
    /**
     * Show configuration
     */
    private function showConfig($args) {
        if (empty($args)) {
            // Show all configuration
            $config = $this->configManager->export(false); // Without sensitive data
            $this->info("Configuration Summary:");
            $this->printArray($config);
        } else {
            // Show specific key
            $key = $args[0];
            $value = $this->configManager->get($key);
            
            if ($value === null) {
                $this->warning("Configuration key '{$key}' not found");
                return 1;
            }
            
            $this->info("Configuration for '{$key}':");
            if (is_array($value)) {
                $this->printArray($value);
            } else {
                echo $value . "\n";
            }
        }
        
        return 0;
    }
    
    /**
     * Set configuration value
     */
    private function setConfig($args) {
        if (count($args) < 2) {
            $this->error("Usage: set <key> <value>");
            return 1;
        }
        
        $key = $args[0];
        $value = $args[1];
        
        // Try to parse value as JSON first
        $jsonValue = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $value = $jsonValue;
        } else {
            // Convert string booleans
            if ($value === 'true') $value = true;
            elseif ($value === 'false') $value = false;
            elseif ($value === 'null') $value = null;
            elseif (is_numeric($value)) $value = is_float($value) ? (float)$value : (int)$value;
        }
        
        $this->configManager->set($key, $value);
        $this->success("✓ Configuration '{$key}' updated");
        
        return 0;
    }
    
    /**
     * Initialize configuration
     */
    private function initConfig() {
        $this->info("Initializing configuration system...");
        
        $initializer = new ConfigInitializer();
        $result = $initializer->initialize();
        
        if ($result['success']) {
            $this->success("✓ Configuration system initialized successfully");
            $this->info("Environment: " . $result['environment']);
            $this->info("Debug mode: " . ($result['debug_mode'] ? 'enabled' : 'disabled'));
        } else {
            $this->error("✗ Configuration initialization failed");
            return 1;
        }
        
        return 0;
    }
    
    /**
     * Manage cache
     */
    private function manageCache($args) {
        if (empty($args)) {
            $this->error("Usage: cache <clear|status>");
            return 1;
        }
        
        $action = $args[0];
        
        switch ($action) {
            case 'clear':
                $this->configManager->clearCache();
                $this->success("✓ Configuration cache cleared");
                break;
                
            case 'status':
                $cacheFile = __DIR__ . '/../cache/config.cache';
                if (file_exists($cacheFile)) {
                    $size = filesize($cacheFile);
                    $modified = date('Y-m-d H:i:s', filemtime($cacheFile));
                    $this->info("Cache file exists");
                    $this->info("Size: " . $this->formatBytes($size));
                    $this->info("Last modified: {$modified}");
                } else {
                    $this->info("No cache file found");
                }
                break;
                
            default:
                $this->error("Unknown cache action: {$action}");
                return 1;
        }
        
        return 0;
    }
    
    /**
     * Manage credentials
     */
    private function manageCredentials($args) {
        if (empty($args)) {
            $this->error("Usage: credentials <list|show|set|delete|rotate|cleanup>");
            return 1;
        }
        
        $action = $args[0];
        
        switch ($action) {
            case 'list':
                $keys = $this->credentialManager->listKeys();
                $this->info("Stored credentials:");
                foreach ($keys as $keyInfo) {
                    $status = '';
                    if ($keyInfo['expires_at']) {
                        $status .= " (expires: {$keyInfo['expires_at']})";
                    }
                    if ($keyInfo['has_rotation']) {
                        $status .= " (auto-rotate)";
                    }
                    $this->info("  - {$keyInfo['key']}{$status}");
                }
                break;
                
            case 'show':
                if (empty($args[1])) {
                    $this->error("Usage: credentials show <key>");
                    return 1;
                }
                $metadata = $this->credentialManager->getMetadata($args[1]);
                if ($metadata) {
                    $this->info("Credential metadata for '{$args[1]}':");
                    $this->printArray($metadata);
                } else {
                    $this->warning("Credential '{$args[1]}' not found");
                    return 1;
                }
                break;
                
            case 'set':
                if (count($args) < 3) {
                    $this->error("Usage: credentials set <key> <value>");
                    return 1;
                }
                $this->credentialManager->store($args[1], $args[2]);
                $this->success("✓ Credential '{$args[1]}' stored");
                break;
                
            case 'delete':
                if (empty($args[1])) {
                    $this->error("Usage: credentials delete <key>");
                    return 1;
                }
                if ($this->credentialManager->delete($args[1])) {
                    $this->success("✓ Credential '{$args[1]}' deleted");
                } else {
                    $this->warning("Credential '{$args[1]}' not found");
                    return 1;
                }
                break;
                
            case 'rotate':
                if (count($args) < 3) {
                    $this->error("Usage: credentials rotate <key> <new_value>");
                    return 1;
                }
                $this->credentialManager->rotate($args[1], $args[2], 'manual_cli');
                $this->success("✓ Credential '{$args[1]}' rotated");
                break;
                
            case 'cleanup':
                $cleaned = $this->credentialManager->cleanupExpired();
                $this->success("✓ Cleaned up {$cleaned} expired credentials");
                break;
                
            default:
                $this->error("Unknown credentials action: {$action}");
                return 1;
        }
        
        return 0;
    }
    
    /**
     * Export configuration
     */
    private function exportConfig($args) {
        $includeSensitive = in_array('--include-sensitive', $args);
        $format = 'json';
        
        // Check for format option
        foreach ($args as $arg) {
            if (strpos($arg, '--format=') === 0) {
                $format = substr($arg, 9);
            }
        }
        
        $config = $this->configManager->export($includeSensitive);
        
        switch ($format) {
            case 'json':
                echo json_encode($config, JSON_PRETTY_PRINT) . "\n";
                break;
                
            case 'env':
                $this->exportAsEnv($config);
                break;
                
            case 'yaml':
                $this->exportAsYaml($config);
                break;
                
            default:
                $this->error("Unsupported format: {$format}");
                return 1;
        }
        
        return 0;
    }
    
    /**
     * Import configuration
     */
    private function importConfig($args) {
        if (empty($args)) {
            $this->error("Usage: import <file>");
            return 1;
        }
        
        $file = $args[0];
        
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }
        
        $content = file_get_contents($file);
        $config = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Invalid JSON file");
            return 1;
        }
        
        $imported = 0;
        $this->importConfigRecursive($config, '', $imported);
        
        $this->success("✓ Imported {$imported} configuration values");
        
        return 0;
    }
    
    /**
     * Import configuration recursively
     */
    private function importConfigRecursive($config, $prefix, &$imported) {
        foreach ($config as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value)) {
                $this->importConfigRecursive($value, $fullKey, $imported);
            } else {
                $this->configManager->set($fullKey, $value);
                $imported++;
            }
        }
    }
    
    /**
     * Export configuration as environment variables
     */
    private function exportAsEnv($config, $prefix = '') {
        foreach ($config as $key => $value) {
            $envKey = $prefix ? $prefix . '_' . strtoupper($key) : strtoupper($key);
            
            if (is_array($value)) {
                $this->exportAsEnv($value, $envKey);
            } else {
                $envValue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
                echo "{$envKey}={$envValue}\n";
            }
        }
    }
    
    /**
     * Export configuration as YAML (simple implementation)
     */
    private function exportAsYaml($config, $indent = 0) {
        foreach ($config as $key => $value) {
            echo str_repeat('  ', $indent) . $key . ':';
            
            if (is_array($value)) {
                echo "\n";
                $this->exportAsYaml($value, $indent + 1);
            } else {
                $yamlValue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
                echo " {$yamlValue}\n";
            }
        }
    }
    
    /**
     * Print array in a readable format
     */
    private function printArray($array, $indent = 0) {
        foreach ($array as $key => $value) {
            echo str_repeat('  ', $indent) . $key . ': ';
            
            if (is_array($value)) {
                echo "\n";
                $this->printArray($value, $indent + 1);
            } else {
                $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
                echo $displayValue . "\n";
            }
        }
    }
    
    /**
     * Format bytes for display
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
    
    /**
     * Show help message
     */
    private function showHelp() {
        echo "Configuration Manager CLI Tool\n\n";
        echo "Usage: php config_manager.php <command> [options]\n\n";
        echo "Commands:\n";
        
        foreach ($this->commands as $command => $description) {
            echo sprintf("  %-12s %s\n", $command, $description);
        }
        
        echo "\nExamples:\n";
        echo "  php config_manager.php validate\n";
        echo "  php config_manager.php show app.env\n";
        echo "  php config_manager.php set app.debug true\n";
        echo "  php config_manager.php credentials list\n";
        echo "  php config_manager.php cache clear\n";
        echo "  php config_manager.php export --format=env\n";
    }
    
    /**
     * Output methods
     */
    private function info($message) {
        echo "[INFO] {$message}\n";
    }
    
    private function success($message) {
        echo "\033[32m{$message}\033[0m\n";
    }
    
    private function warning($message) {
        echo "\033[33m[WARNING] {$message}\033[0m\n";
    }
    
    private function error($message) {
        echo "\033[31m[ERROR] {$message}\033[0m\n";
    }
}

// Run the CLI tool
$cli = new ConfigManagerCLI();
exit($cli->run($argv));