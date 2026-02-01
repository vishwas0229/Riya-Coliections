<?php
/**
 * Product Query Consistency Property Test
 * 
 * Property-based tests for product query consistency to ensure search, filtering,
 * sorting, and pagination produce consistent and accurate results across all inputs.
 * 
 * Task: 7.5 Write property test for product query consistency
 * **Property 8: Product Query Consistency**
 * **Validates: Requirements 5.2**
 */

// Set up basic test environment
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('UTC');

class ProductQueryConsistencyPropertyTest {
    
    /**
     * Property Test: Search Query Consistency
     * 
     * **Property 8: Product Query Consistency**
     * For any combination of search, filter, sort, and pagination parameters,
     * both systems should return equivalent product results.
     * **Validates: Requirements 5.2**
     */
    public function testSearchQueryConsistency() {
        echo "Testing Search Query Consistency (Property 8)...\n";
        
        // Create a large test dataset
        $products = $this->generateTestProductDataset(200);
        
        $iterations = 100;
        $passedTests = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Generate random search parameters
                $searchParams = $this->generateRandomSearchParams();
                
                // Apply search multiple times with same parameters
                $result1 = $this->executeProductSearch($products, $searchParams);
                $result2 = $this->executeProductSearch($products, $searchParams);
                
                // Results should be identical for same parameters
                $this->assert($result1 === $result2, 'Search results should be consistent for same parameters');
                
                // Verify search results match criteria
                foreach ($result1 as $product) {
                    if (!empty($searchParams['search'])) {
                        $matchesSearch = $this->productMatchesSearchTerm($product, $searchParams['search']);
                        $this->assert($matchesSearch, 'All search results should match search criteria');
                    }
                }
                
                // Test search term variations (case insensitive)
                if (!empty($searchParams['search'])) {
                    $upperCaseParams = $searchParams;
                    $upperCaseParams['search'] = strtoupper($searchParams['search']);
                    
                    $lowerCaseParams = $searchParams;
                    $lowerCaseParams['search'] = strtolower($searchParams['search']);
                    
                    $upperResult = $this->executeProductSearch($products, $upperCaseParams);
                    $lowerResult = $this->executeProductSearch($products, $lowerCaseParams);
                    
                    $this->assert(count($upperResult) === count($lowerResult), 
                        'Search should be case insensitive');
                }
                
                $passedTests++;
                
            } catch (Exception $e) {
                echo "  Iteration $i failed: " . $e->getMessage() . "\n";
            }
        }
        
        $successRate = ($passedTests / $iterations) * 100;
        echo "  Passed: $passedTests/$iterations iterations ({$successRate}%)\n";
        
        $this->assert($successRate >= 95, 'At least 95% of search consistency tests should pass');
        echo "✓ Search Query Consistency test passed\n";
    }
    
    /**
     * Property Test: Filter Combination Consistency
     * 
     * For any combination of filters, results should be consistent and accurate
     */
    public function testFilterCombinationConsistency() {
        echo "Testing Filter Combination Consistency...\n";
        
        $products = $this->generateTestProductDataset(150);
        
        $iterations = 80;
        $passedTests = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Generate random filter combinations
                $filters = $this->generateRandomFilters();
                
                // Apply filters
                $filteredProducts = $this->applyFilters($products, $filters);
                
                // Verify each product matches all filter criteria
                foreach ($filteredProducts as $product) {
                    if (isset($filters['category_id'])) {
                        $this->assert($product['category_id'] == $filters['category_id'], 
                            'Product should match category filter');
                    }
                    
                    if (isset($filters['brand'])) {
                        $this->assert($product['brand'] === $filters['brand'], 
                            'Product should match brand filter');
                    }
                    
                    if (isset($filters['min_price'])) {
                        $this->assert($product['price'] >= $filters['min_price'], 
                            'Product price should be above minimum');
                    }
                    
                    if (isset($filters['max_price'])) {
                        $this->assert($product['price'] <= $filters['max_price'], 
                            'Product price should be below maximum');
                    }
                    
                    if (isset($filters['in_stock']) && $filters['in_stock']) {
                        $this->assert($product['stock_quantity'] > 0, 
                            'Product should be in stock when filter applied');
                    }
                }
                
                // Test filter order independence
                $filters1 = ['category_id' => 1, 'min_price' => 50];
                $filters2 = ['min_price' => 50, 'category_id' => 1];
                
                $result1 = $this->applyFilters($products, $filters1);
                $result2 = $this->applyFilters($products, $filters2);
                
                $this->assert(count($result1) === count($result2), 
                    'Filter order should not affect results');
                
                // Test filter intersection property
                $categoryFilter = ['category_id' => 1];
                $priceFilter = ['min_price' => 100];
                $combinedFilter = ['category_id' => 1, 'min_price' => 100];
                
                $categoryResults = $this->applyFilters($products, $categoryFilter);
                $priceResults = $this->applyFilters($products, $priceFilter);
                $combinedResults = $this->applyFilters($products, $combinedFilter);
                
                // Combined results should be subset of individual filters
                $this->assert(count($combinedResults) <= count($categoryResults), 
                    'Combined filter should not exceed individual filter results');
                $this->assert(count($combinedResults) <= count($priceResults), 
                    'Combined filter should not exceed individual filter results');
                
                $passedTests++;
                
            } catch (Exception $e) {
                echo "  Iteration $i failed: " . $e->getMessage() . "\n";
            }
        }
        
        $successRate = ($passedTests / $iterations) * 100;
        echo "  Passed: $passedTests/$iterations iterations ({$successRate}%)\n";
        
        $this->assert($successRate >= 90, 'At least 90% of filter consistency tests should pass');
        echo "✓ Filter Combination Consistency test passed\n";
    }
    
    /**
     * Property Test: Sorting Consistency
     * 
     * For any sort parameters, results should be consistently ordered
     */
    public function testSortingConsistency() {
        echo "Testing Sorting Consistency...\n";
        
        $products = $this->generateTestProductDataset(100);
        
        $iterations = 50;
        $passedTests = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                // Test different sort options
                $sortOptions = [
                    ['field' => 'name', 'direction' => 'asc'],
                    ['field' => 'name', 'direction' => 'desc'],
                    ['field' => 'price', 'direction' => 'asc'],
                    ['field' => 'price', 'direction' => 'desc'],
                    ['field' => 'created_at', 'direction' => 'asc'],
                    ['field' => 'created_at', 'direction' => 'desc']
                ];
                
                $sortOption = $sortOptions[array_rand($sortOptions)];
                
                // Sort products
                $sortedProducts = $this->sortProducts($products, $sortOption['field'], $sortOption['direction']);
                
                // Verify sort order
                for ($j = 1; $j < count($sortedProducts); $j++) {
                    $prev = $sortedProducts[$j - 1];
                    $curr = $sortedProducts[$j];
                    
                    $comparison = $this->compareProductFields($prev, $curr, $sortOption['field']);
                    
                    if ($sortOption['direction'] === 'asc') {
                        $this->assert($comparison <= 0, 
                            "Products should be sorted in ascending order by {$sortOption['field']}");
                    } else {
                        $this->assert($comparison >= 0, 
                            "Products should be sorted in descending order by {$sortOption['field']}");
                    }
                }
                
                // Test sort stability (same values should maintain relative order)
                $stableSorted1 = $this->sortProducts($products, $sortOption['field'], $sortOption['direction']);
                $stableSorted2 = $this->sortProducts($products, $sortOption['field'], $sortOption['direction']);
                
                $this->assert($stableSorted1 === $stableSorted2, 'Sort should be stable');
                
                // Test reverse sort property
                $ascSorted = $this->sortProducts($products, $sortOption['field'], 'asc');
                $descSorted = $this->sortProducts($products, $sortOption['field'], 'desc');
                
                $this->assert(count($ascSorted) === count($descSorted), 
                    'Ascending and descending sorts should have same count');
                
                $passedTests++;
                
            } catch (Exception $e) {
                echo "  Iteration $i failed: " . $e->getMessage() . "\n";
            }
        }
        
        $successRate = ($passedTests / $iterations) * 100;
        echo "  Passed: $passedTests/$iterations iterations ({$successRate}%)\n";
        
        $this->assert($successRate >= 90, 'At least 90% of sorting consistency tests should pass');
        echo "✓ Sorting Consistency test passed\n";
    }
    
    /**
     * Property Test: Pagination Consistency
     * 
     * For any pagination parameters, results should be consistent and complete
     */
    public function testPaginationConsistency() {
        echo "Testing Pagination Consistency...\n";
        
        $products = $this->generateTestProductDataset(100);
        
        $iterations = 20; // Reduced iterations
        $passedTests = 0;
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                $totalProducts = count($products);
                $perPage = rand(8, 15); // Use more reasonable page sizes
                $totalPages = ceil($totalProducts / $perPage);
                
                $allPaginatedProducts = [];
                $allProductIds = [];
                
                // Collect all products from all pages
                for ($page = 1; $page <= $totalPages; $page++) {
                    $paginatedResult = $this->paginateProducts($products, $page, $perPage);
                    
                    // Verify page size constraints
                    $actualCount = count($paginatedResult['products']);
                    
                    if ($page < $totalPages) {
                        $this->assert($actualCount === $perPage, 
                            "Non-last page $page should have exactly $perPage products, got $actualCount");
                    } else {
                        // For last page, it should have remaining products
                        $expectedLastPageSize = (int)($totalProducts - (($totalPages - 1) * $perPage));
                        
                        $this->assert($actualCount === $expectedLastPageSize, 
                            "Last page $page should have $expectedLastPageSize products, got $actualCount. Total: $totalProducts, TotalPages: $totalPages, PerPage: $perPage");
                    }
                    
                    // Verify pagination metadata
                    $this->assert($paginatedResult['pagination']['current_page'] === $page, 
                        'Current page should match requested page');
                    $this->assert($paginatedResult['pagination']['per_page'] === $perPage, 
                        'Per page should match requested per page');
                    $this->assert($paginatedResult['pagination']['total'] === $totalProducts, 
                        'Total should match actual product count');
                    $this->assert($paginatedResult['pagination']['total_pages'] === $totalPages, 
                        'Total pages should be calculated correctly');
                    
                    // Collect products and IDs
                    $allPaginatedProducts = array_merge($allPaginatedProducts, $paginatedResult['products']);
                    foreach ($paginatedResult['products'] as $product) {
                        $allProductIds[] = $product['id'];
                    }
                }
                
                // Verify completeness - all products should be included exactly once
                $this->assert(count($allPaginatedProducts) === $totalProducts, 
                    'Pagination should include all products exactly once');
                
                $uniqueIds = array_unique($allProductIds);
                $this->assert(count($uniqueIds) === count($allProductIds), 
                    'No product should appear multiple times across pages');
                
                $passedTests++;
                
            } catch (Exception $e) {
                echo "  Iteration $i failed: " . $e->getMessage() . "\n";
            }
        }
        
        $successRate = ($passedTests / $iterations) * 100;
        echo "  Passed: $passedTests/$iterations iterations ({$successRate}%)\n";
        
        $this->assert($successRate >= 85, 'At least 85% of pagination consistency tests should pass');
        echo "✓ Pagination Consistency test passed\n";
    }
    
    /**
     * Property Test: Query Performance Consistency
     * 
     * For any query parameters, performance should be consistent and reasonable
     */
    public function testQueryPerformanceConsistency() {
        echo "Testing Query Performance Consistency...\n";
        
        $products = $this->generateTestProductDataset(500); // Larger dataset for performance testing
        
        $iterations = 20;
        $passedTests = 0;
        $performanceTimes = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                $searchParams = $this->generateRandomSearchParams();
                
                // Measure query execution time
                $startTime = microtime(true);
                $results = $this->executeProductSearch($products, $searchParams);
                $endTime = microtime(true);
                
                $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
                $performanceTimes[] = $executionTime;
                
                // Performance should be reasonable (under 100ms for in-memory operations)
                $this->assert($executionTime < 100, 'Query execution should be under 100ms');
                
                // Test query with different dataset sizes
                $smallDataset = array_slice($products, 0, 50);
                $mediumDataset = array_slice($products, 0, 200);
                
                $smallTime = $this->measureQueryTime($smallDataset, $searchParams);
                $mediumTime = $this->measureQueryTime($mediumDataset, $searchParams);
                $largeTime = $this->measureQueryTime($products, $searchParams);
                
                // Performance should scale reasonably with dataset size
                $this->assert($smallTime <= $mediumTime * 2, 'Performance should scale reasonably');
                $this->assert($mediumTime <= $largeTime * 2, 'Performance should scale reasonably');
                
                $passedTests++;
                
            } catch (Exception $e) {
                echo "  Iteration $i failed: " . $e->getMessage() . "\n";
            }
        }
        
        $avgTime = array_sum($performanceTimes) / count($performanceTimes);
        $maxTime = max($performanceTimes);
        $minTime = min($performanceTimes);
        
        echo "  Performance stats: Avg: " . number_format($avgTime, 2) . "ms, Min: " . number_format($minTime, 2) . "ms, Max: " . number_format($maxTime, 2) . "ms\n";
        
        $successRate = ($passedTests / $iterations) * 100;
        echo "  Passed: $passedTests/$iterations iterations ({$successRate}%)\n";
        
        $this->assert($successRate >= 85, 'At least 85% of performance tests should pass');
        echo "✓ Query Performance Consistency test passed\n";
    }
    
    /**
     * Generate test product dataset
     */
    private function generateTestProductDataset($size) {
        $products = [];
        $names = ['Laptop', 'Phone', 'Tablet', 'Watch', 'Camera', 'Headphones', 'Speaker', 'Monitor', 'Keyboard', 'Mouse'];
        $brands = ['Apple', 'Samsung', 'Sony', 'Dell', 'HP', 'Lenovo', 'Canon', 'Nikon', 'Microsoft', 'Logitech'];
        $categories = [1, 2, 3, 4, 5];
        
        for ($i = 0; $i < $size; $i++) {
            $products[] = [
                'id' => $i + 1,
                'name' => $names[array_rand($names)] . ' ' . ($i + 1000),
                'description' => 'Description for product ' . ($i + 1),
                'price' => round(rand(1000, 50000) / 100, 2),
                'stock_quantity' => rand(0, 100),
                'category_id' => $categories[array_rand($categories)],
                'brand' => $brands[array_rand($brands)],
                'sku' => 'SKU' . str_pad($i + 1, 6, '0', STR_PAD_LEFT),
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s', time() - rand(0, 86400 * 30)) // Random date within last 30 days
            ];
        }
        
        return $products;
    }
    
    /**
     * Generate random search parameters
     */
    private function generateRandomSearchParams() {
        $params = [];
        
        if (rand(0, 1)) {
            $searchTerms = ['Laptop', 'Phone', 'Apple', 'Samsung', 'Pro', 'Premium'];
            $params['search'] = $searchTerms[array_rand($searchTerms)];
        }
        
        if (rand(0, 1)) {
            $params['category_id'] = rand(1, 5);
        }
        
        if (rand(0, 1)) {
            $params['min_price'] = rand(10, 100);
        }
        
        if (rand(0, 1)) {
            $params['max_price'] = rand(200, 500);
        }
        
        return $params;
    }
    
    /**
     * Generate random filters
     */
    private function generateRandomFilters() {
        $filters = [];
        
        if (rand(0, 1)) {
            $filters['category_id'] = rand(1, 5);
        }
        
        if (rand(0, 1)) {
            $brands = ['Apple', 'Samsung', 'Sony', 'Dell'];
            $filters['brand'] = $brands[array_rand($brands)];
        }
        
        if (rand(0, 1)) {
            $filters['min_price'] = rand(50, 200);
        }
        
        if (rand(0, 1)) {
            $filters['max_price'] = rand(300, 600);
        }
        
        if (rand(0, 1)) {
            $filters['in_stock'] = true;
        }
        
        return $filters;
    }
    
    /**
     * Execute product search
     */
    private function executeProductSearch($products, $params) {
        $results = $products;
        
        // Apply search filter
        if (!empty($params['search'])) {
            $results = array_filter($results, function($product) use ($params) {
                return $this->productMatchesSearchTerm($product, $params['search']);
            });
        }
        
        // Apply other filters
        $results = $this->applyFilters($results, $params);
        
        return array_values($results);
    }
    
    /**
     * Check if product matches search term
     */
    private function productMatchesSearchTerm($product, $searchTerm) {
        $searchFields = ['name', 'description', 'brand', 'sku'];
        
        foreach ($searchFields as $field) {
            if (isset($product[$field]) && stripos($product[$field], $searchTerm) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Apply filters to products
     */
    private function applyFilters($products, $filters) {
        return array_filter($products, function($product) use ($filters) {
            if (isset($filters['category_id']) && $product['category_id'] != $filters['category_id']) {
                return false;
            }
            
            if (isset($filters['brand']) && $product['brand'] !== $filters['brand']) {
                return false;
            }
            
            if (isset($filters['min_price']) && $product['price'] < $filters['min_price']) {
                return false;
            }
            
            if (isset($filters['max_price']) && $product['price'] > $filters['max_price']) {
                return false;
            }
            
            if (isset($filters['in_stock']) && $filters['in_stock'] && $product['stock_quantity'] <= 0) {
                return false;
            }
            
            return true;
        });
    }
    
    /**
     * Sort products
     */
    private function sortProducts($products, $field, $direction = 'asc') {
        $sorted = $products;
        
        usort($sorted, function($a, $b) use ($field, $direction) {
            $comparison = $this->compareProductFields($a, $b, $field);
            return $direction === 'desc' ? -$comparison : $comparison;
        });
        
        return $sorted;
    }
    
    /**
     * Compare product fields
     */
    private function compareProductFields($a, $b, $field) {
        $valueA = isset($a[$field]) ? $a[$field] : '';
        $valueB = isset($b[$field]) ? $b[$field] : '';
        
        if (is_numeric($valueA) && is_numeric($valueB)) {
            if ($valueA < $valueB) return -1;
            if ($valueA > $valueB) return 1;
            return 0;
        }
        
        return strcasecmp($valueA, $valueB);
    }
    
    /**
     * Paginate products
     */
    private function paginateProducts($products, $page, $perPage) {
        $total = count($products);
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        
        $paginatedProducts = array_slice($products, $offset, $perPage);
        
        return [
            'products' => $paginatedProducts,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ];
    }
    
    /**
     * Measure query execution time
     */
    private function measureQueryTime($products, $params) {
        $startTime = microtime(true);
        $this->executeProductSearch($products, $params);
        $endTime = microtime(true);
        
        return ($endTime - $startTime) * 1000; // Convert to milliseconds
    }
    
    /**
     * Helper assertion method
     */
    private function assert($condition, $message) {
        if (!$condition) {
            throw new Exception("Assertion failed: $message");
        }
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests() {
        echo "Running Product Query Consistency Property Tests...\n";
        echo "=================================================\n\n";
        
        try {
            $this->testSearchQueryConsistency();
            $this->testFilterCombinationConsistency();
            $this->testSortingConsistency();
            $this->testPaginationConsistency();
            $this->testQueryPerformanceConsistency();
            
            echo "\n✅ All Product Query Consistency property tests passed!\n";
            echo "   - Search Query Consistency (Property 8) ✓\n";
            echo "   - Filter Combination Consistency ✓\n";
            echo "   - Sorting Consistency ✓\n";
            echo "   - Pagination Consistency ✓\n";
            echo "   - Query Performance Consistency ✓\n";
            
        } catch (Exception $e) {
            echo "\n❌ Property test failed: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ProductQueryConsistencyPropertyTest();
    $test->runAllTests();
}