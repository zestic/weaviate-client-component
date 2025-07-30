<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Integration;

use PHPUnit\Framework\TestCase;
use Laminas\ServiceManager\ServiceManager;
use Weaviate\WeaviateClient;
use Zestic\WeaviateClientComponent\ConfigProvider;

/**
 * Integration test for real Weaviate connections.
 *
 * Tests that factory-created clients can perform actual operations
 * against a real Weaviate instance.
 */
class RealWeaviateConnectionTest extends TestCase
{
    private ServiceManager $container;
    private string $weaviateUrl;

    protected function setUp(): void
    {
        $this->weaviateUrl = $_ENV['WEAVIATE_URL'] ?? 'http://localhost:18080';

        // Parse URL to get host and port
        $parsedUrl = parse_url($this->weaviateUrl);
        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? 18080;

        $config = [
            'weaviate' => [
                'connection_method' => 'local',
                'connection' => [
                    'host' => $host,
                    'port' => $port,
                    'secure' => false,
                    'timeout' => 30,
                ],
                'enable_retry' => true,
                'max_retries' => 3,
                'additional_headers' => [
                    'X-Test-Suite' => 'RealWeaviateConnectionTest',
                ],
            ],
        ];

        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        $this->container = new ServiceManager(array_merge($dependencies, [
            'services' => ['config' => $config]
        ]));
    }

    public function testFactoryCreatesWorkingClient(): void
    {
        if (!$this->isWeaviateAvailable()) {
            $this->markTestSkipped('Weaviate is not available for integration testing');
        }

        $client = $this->container->get(WeaviateClient::class);

        $this->assertInstanceOf(WeaviateClient::class, $client);

        // Test actual connection
        $schema = $client->schema()->get();
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('classes', $schema);
    }

    public function testClientCanPerformCRUDOperations(): void
    {
        if (!$this->isWeaviateAvailable()) {
            $this->markTestSkipped('Weaviate is not available for integration testing');
        }

        $client = $this->container->get(WeaviateClient::class);
        $collectionName = 'TestIntegrationCollection_' . uniqid();

        try {
            // Create collection
            $client->collections()->create($collectionName, [
                'properties' => [
                    ['name' => 'title', 'dataType' => ['text']],
                    ['name' => 'content', 'dataType' => ['text']],
                    ['name' => 'score', 'dataType' => ['number']],
                ]
            ]);

            $this->assertTrue($client->collections()->exists($collectionName));

            // Get collection object
            $collection = $client->collections()->get($collectionName);
            $this->assertInstanceOf(\Weaviate\Collections\Collection::class, $collection);

            // Create data
            $result = $collection->data()->create([
                'title' => 'Test Article',
                'content' => 'This is test content for integration testing',
                'score' => 95.5,
            ]);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('id', $result);
            $objectId = $result['id'];

            // Read data
            $retrieved = $collection->data()->get($objectId);
            $this->assertIsArray($retrieved);
            $this->assertEquals('Test Article', $retrieved['title']);
            $this->assertEquals('This is test content for integration testing', $retrieved['content']);
            $this->assertEquals(95.5, $retrieved['score']);

            // Update data
            $collection->data()->update($objectId, [
                'title' => 'Updated Test Article',
                'score' => 98.0,
            ]);

            $updated = $collection->data()->get($objectId);
            $this->assertEquals('Updated Test Article', $updated['title']);
            $this->assertEquals(98.0, $updated['score']);
            // Content should remain unchanged
            $this->assertEquals('This is test content for integration testing', $updated['content']);

            // Delete data
            $collection->data()->delete($objectId);

            // Verify deletion
            $this->expectException(\Exception::class);
            $collection->data()->get($objectId);
        } finally {
            // Cleanup collection
            if ($client->collections()->exists($collectionName)) {
                $client->schema()->delete($collectionName);
            }
        }
    }

    public function testClientCanPerformBatchOperations(): void
    {
        if (!$this->isWeaviateAvailable()) {
            $this->markTestSkipped('Weaviate is not available for integration testing');
        }

        $client = $this->container->get(WeaviateClient::class);
        $collectionName = 'TestBatchCollection_' . uniqid();

        try {
            // Create collection
            $client->collections()->create($collectionName, [
                'properties' => [
                    ['name' => 'title', 'dataType' => ['text']],
                    ['name' => 'category', 'dataType' => ['text']],
                ]
            ]);

            $collection = $client->collections()->get($collectionName);

            // Batch create multiple objects
            $batchData = [
                ['title' => 'Article 1', 'category' => 'Technology'],
                ['title' => 'Article 2', 'category' => 'Science'],
                ['title' => 'Article 3', 'category' => 'Technology'],
                ['title' => 'Article 4', 'category' => 'Health'],
                ['title' => 'Article 5', 'category' => 'Science'],
            ];

            $batchResult = $collection->data()->createBatch($batchData);
            $this->assertIsArray($batchResult);
            $this->assertCount(5, $batchResult);

            // Verify all objects were created
            foreach ($batchResult as $result) {
                $this->assertArrayHasKey('id', $result);
                $this->assertNotEmpty($result['id']);
            }

            // Query objects
            $queryResult = $collection->data()->query([
                'where' => [
                    'path' => ['category'],
                    'operator' => 'Equal',
                    'valueText' => 'Technology',
                ]
            ]);

            $this->assertIsArray($queryResult);
            $this->assertArrayHasKey('data', $queryResult);
            $this->assertCount(2, $queryResult['data']); // Should find 2 Technology articles
        } finally {
            // Cleanup
            if ($client->collections()->exists($collectionName)) {
                $client->schema()->delete($collectionName);
            }
        }
    }

    public function testClientCanHandleSchemaOperations(): void
    {
        if (!$this->isWeaviateAvailable()) {
            $this->markTestSkipped('Weaviate is not available for integration testing');
        }

        $client = $this->container->get(WeaviateClient::class);
        $collectionName = 'TestSchemaCollection_' . uniqid();

        try {
            // Get initial schema
            $initialSchema = $client->schema()->get();
            $this->assertIsArray($initialSchema);
            $this->assertArrayHasKey('classes', $initialSchema);

            $initialClassCount = count($initialSchema['classes']);

            // Create a new collection
            $client->collections()->create($collectionName, [
                'description' => 'Test collection for schema operations',
                'properties' => [
                    [
                        'name' => 'title',
                        'dataType' => ['text'],
                        'description' => 'The title of the item',
                    ],
                    [
                        'name' => 'tags',
                        'dataType' => ['text[]'],
                        'description' => 'Tags associated with the item',
                    ],
                ]
            ]);

            // Verify collection was added to schema
            $updatedSchema = $client->schema()->get();
            $this->assertCount($initialClassCount + 1, $updatedSchema['classes']);

            // Find our collection in the schema
            $ourCollection = null;
            foreach ($updatedSchema['classes'] as $class) {
                if ($class['class'] === $collectionName) {
                    $ourCollection = $class;
                    break;
                }
            }

            $this->assertNotNull($ourCollection);
            $this->assertEquals('Test collection for schema operations', $ourCollection['description']);
            $this->assertCount(2, $ourCollection['properties']);

            // Verify properties
            $propertyNames = array_column($ourCollection['properties'], 'name');
            $this->assertContains('title', $propertyNames);
            $this->assertContains('tags', $propertyNames);
        } finally {
            // Cleanup
            if ($client->collections()->exists($collectionName)) {
                $client->schema()->delete($collectionName);
            }
        }
    }

    public function testClientConnectionWithRetry(): void
    {
        if (!$this->isWeaviateAvailable()) {
            $this->markTestSkipped('Weaviate is not available for integration testing');
        }

        // Create a client with retry configuration
        $configWithRetry = [
            'weaviate' => [
                'connection_method' => 'local',
                'connection' => [
                    'host' => parse_url($this->weaviateUrl)['host'] ?? 'localhost',
                    'port' => parse_url($this->weaviateUrl)['port'] ?? 18080,
                    'secure' => false,
                    'timeout' => 5, // Short timeout to test retry
                ],
                'enable_retry' => true,
                'max_retries' => 2,
            ],
        ];

        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();
        $retryContainer = new ServiceManager(array_merge($dependencies, [
            'services' => ['config' => $configWithRetry]
        ]));

        $client = $retryContainer->get(WeaviateClient::class);

        // Test that the client works even with retry configuration
        $schema = $client->schema()->get();
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('classes', $schema);
    }

    public function testClientWithCustomHeaders(): void
    {
        if (!$this->isWeaviateAvailable()) {
            $this->markTestSkipped('Weaviate is not available for integration testing');
        }

        $configWithHeaders = [
            'weaviate' => [
                'connection_method' => 'local',
                'connection' => [
                    'host' => parse_url($this->weaviateUrl)['host'] ?? 'localhost',
                    'port' => parse_url($this->weaviateUrl)['port'] ?? 18080,
                ],
                'additional_headers' => [
                    'X-Custom-Header' => 'test-value',
                    'X-Client-Version' => '1.0.0',
                ],
            ],
        ];

        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();
        $headerContainer = new ServiceManager(array_merge($dependencies, [
            'services' => ['config' => $configWithHeaders]
        ]));

        $client = $headerContainer->get(WeaviateClient::class);

        // Test that the client works with custom headers
        $schema = $client->schema()->get();
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('classes', $schema);
    }

    public function testMultipleConnectionMethods(): void
    {
        if (!$this->isWeaviateAvailable()) {
            $this->markTestSkipped('Weaviate is not available for integration testing');
        }

        $parsedUrl = parse_url($this->weaviateUrl);
        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? 18080;

        // Test custom connection method (which should work the same as local for our test setup)
        $customConfig = [
            'weaviate' => [
                'connection_method' => 'custom',
                'connection' => [
                    'host' => $host,
                    'port' => $port,
                    'secure' => false,
                ],
            ],
        ];

        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();
        $customContainer = new ServiceManager(array_merge($dependencies, [
            'services' => ['config' => $customConfig]
        ]));

        $customClient = $customContainer->get(WeaviateClient::class);
        $schema = $customClient->schema()->get();
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('classes', $schema);
    }

    /**
     * Check if Weaviate is available for testing.
     */
    private function isWeaviateAvailable(): bool
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2,
                    'method' => 'GET',
                ]
            ]);

            $result = @file_get_contents($this->weaviateUrl . '/v1/meta', false, $context);
            return $result !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
