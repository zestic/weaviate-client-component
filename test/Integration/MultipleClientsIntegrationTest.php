<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Integration;

use PHPUnit\Framework\TestCase;
use Laminas\ServiceManager\ServiceManager;
use Weaviate\WeaviateClient;
use Zestic\WeaviateClientComponent\ConfigProvider;

/**
 * Integration test for multiple named Weaviate clients.
 *
 * Tests that multiple clients can be configured and used simultaneously
 * without interfering with each other.
 */
class MultipleClientsIntegrationTest extends TestCase
{
    private ServiceManager $container;
    private string $weaviateUrl;

    protected function setUp(): void
    {
        // Get Weaviate URL from environment or use default
        $this->weaviateUrl = $_ENV['WEAVIATE_URL'] ?? 'http://localhost:18080';

        // Parse URL to get host and port
        $parsedUrl = parse_url($this->weaviateUrl);
        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? 18080;

        $config = [
            'weaviate' => [
                'clients' => [
                    'default' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => $host,
                            'port' => $port,
                            'secure' => false,
                        ],
                        'additional_headers' => ['X-Test-Client' => 'default'],
                    ],
                    'rag' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => $host,
                            'port' => $port,
                            'secure' => false,
                        ],
                        'additional_headers' => ['X-Test-Client' => 'rag'],
                        'enable_retry' => true,
                        'max_retries' => 3,
                    ],
                    'customer_data' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => $host,
                            'port' => $port,
                            'secure' => false,
                        ],
                        'additional_headers' => ['X-Test-Client' => 'customer'],
                        'enable_retry' => true,
                        'max_retries' => 5,
                    ],
                    'analytics' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => $host,
                            'port' => $port,
                            'secure' => false,
                        ],
                        'additional_headers' => ['X-Test-Client' => 'analytics'],
                    ],
                ],
            ],
        ];

        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        $this->container = new ServiceManager(array_merge($dependencies, [
            'services' => ['config' => $config]
        ]));
    }

    public function testMultipleClientsAreDistinct(): void
    {
        $defaultClient = $this->container->get('weaviate.client.default');
        $ragClient = $this->container->get('weaviate.client.rag');
        $customerClient = $this->container->get('weaviate.client.customer_data');
        $analyticsClient = $this->container->get('weaviate.client.analytics');

        $this->assertInstanceOf(WeaviateClient::class, $defaultClient);
        $this->assertInstanceOf(WeaviateClient::class, $ragClient);
        $this->assertInstanceOf(WeaviateClient::class, $customerClient);
        $this->assertInstanceOf(WeaviateClient::class, $analyticsClient);

        // Verify they are different instances
        $this->assertNotSame($defaultClient, $ragClient);
        $this->assertNotSame($defaultClient, $customerClient);
        $this->assertNotSame($defaultClient, $analyticsClient);
        $this->assertNotSame($ragClient, $customerClient);
        $this->assertNotSame($ragClient, $analyticsClient);
        $this->assertNotSame($customerClient, $analyticsClient);
    }

    public function testClientsCanConnectToWeaviate(): void
    {
        if (!$this->isWeaviateAvailable()) {
            $this->markTestSkipped('Weaviate is not available for integration testing');
        }

        $ragClient = $this->container->get('weaviate.client.rag');
        $customerClient = $this->container->get('weaviate.client.customer_data');

        // Test basic connectivity
        $ragSchema = $ragClient->schema()->get();
        $customerSchema = $customerClient->schema()->get();

        $this->assertIsArray($ragSchema);
        $this->assertIsArray($customerSchema);
        $this->assertArrayHasKey('classes', $ragSchema);
        $this->assertArrayHasKey('classes', $customerSchema);
    }

    public function testClientsCanCreateCollections(): void
    {
        if (!$this->isWeaviateAvailable()) {
            $this->markTestSkipped('Weaviate is not available for integration testing');
        }

        $ragClient = $this->container->get('weaviate.client.rag');
        $customerClient = $this->container->get('weaviate.client.customer_data');

        // Create collections with different names to avoid conflicts
        $ragCollectionName = 'TestRAGCollection_' . uniqid();
        $customerCollectionName = 'TestCustomerCollection_' . uniqid();

        try {
            // Create collections
            $ragClient->collections()->create($ragCollectionName, [
                'properties' => [
                    ['name' => 'content', 'dataType' => ['text']],
                    ['name' => 'embedding', 'dataType' => ['number[]']],
                ]
            ]);

            $customerClient->collections()->create($customerCollectionName, [
                'properties' => [
                    ['name' => 'name', 'dataType' => ['text']],
                    ['name' => 'email', 'dataType' => ['text']],
                ]
            ]);

            // Verify collections exist
            $this->assertTrue($ragClient->collections()->exists($ragCollectionName));
            $this->assertTrue($customerClient->collections()->exists($customerCollectionName));

            // Test data operations
            $ragCollection = $ragClient->collections()->get($ragCollectionName);
            $customerCollection = $customerClient->collections()->get($customerCollectionName);

            // Insert test data
            $ragResult = $ragCollection->data()->create([
                'content' => 'Test RAG content',
                'embedding' => [0.1, 0.2, 0.3],
            ]);

            $customerResult = $customerCollection->data()->create([
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ]);

            $this->assertIsArray($ragResult);
            $this->assertArrayHasKey('id', $ragResult);
            $this->assertIsArray($customerResult);
            $this->assertArrayHasKey('id', $customerResult);
        } finally {
            // Cleanup
            if ($ragClient->collections()->exists($ragCollectionName)) {
                $ragClient->schema()->delete($ragCollectionName);
            }
            if ($customerClient->collections()->exists($customerCollectionName)) {
                $customerClient->schema()->delete($customerCollectionName);
            }
        }
    }

    public function testClientIsolation(): void
    {
        if (!$this->isWeaviateAvailable()) {
            $this->markTestSkipped('Weaviate is not available for integration testing');
        }

        $ragClient = $this->container->get('weaviate.client.rag');
        $customerClient = $this->container->get('weaviate.client.customer_data');

        $ragCollectionName = 'TestRAGIsolation_' . uniqid();
        $customerCollectionName = 'TestCustomerIsolation_' . uniqid();

        try {
            // Create collection in RAG client
            $ragClient->collections()->create($ragCollectionName, [
                'properties' => [
                    ['name' => 'content', 'dataType' => ['text']],
                ]
            ]);

            // Create collection in customer client
            $customerClient->collections()->create($customerCollectionName, [
                'properties' => [
                    ['name' => 'name', 'dataType' => ['text']],
                ]
            ]);

            // Both clients should see both collections (they connect to the same Weaviate instance)
            // But this tests that they can operate independently
            $this->assertTrue($ragClient->collections()->exists($ragCollectionName));
            $this->assertTrue($ragClient->collections()->exists($customerCollectionName));
            $this->assertTrue($customerClient->collections()->exists($ragCollectionName));
            $this->assertTrue($customerClient->collections()->exists($customerCollectionName));

            // Test that each client can work with their respective collections
            $ragCollection = $ragClient->collections()->get($ragCollectionName);
            $customerCollection = $customerClient->collections()->get($customerCollectionName);

            $ragData = $ragCollection->data()->create(['content' => 'RAG test data']);
            $customerData = $customerCollection->data()->create(['name' => 'Customer test data']);

            $this->assertIsArray($ragData);
            $this->assertIsArray($customerData);
        } finally {
            // Cleanup
            if ($ragClient->collections()->exists($ragCollectionName)) {
                $ragClient->schema()->delete($ragCollectionName);
            }
            if ($customerClient->collections()->exists($customerCollectionName)) {
                $customerClient->schema()->delete($customerCollectionName);
            }
        }
    }

    public function testDefaultClientAlias(): void
    {
        $defaultClient1 = $this->container->get('weaviate.client.default');
        $defaultClient2 = $this->container->get('weaviate.client');
        $defaultClient3 = $this->container->get('WeaviateClient');

        $this->assertInstanceOf(WeaviateClient::class, $defaultClient1);
        $this->assertInstanceOf(WeaviateClient::class, $defaultClient2);
        $this->assertInstanceOf(WeaviateClient::class, $defaultClient3);

        // Note: These might not be the same instance due to how the service manager works
        // but they should all be valid WeaviateClient instances
    }

    public function testAbstractFactoryCanCreateClients(): void
    {
        $abstractFactory = $this->container->get(\Zestic\WeaviateClientComponent\Factory\WeaviateClientAbstractFactory::class);

        $this->assertTrue($abstractFactory->canCreate($this->container, 'weaviate.client.rag'));
        $this->assertTrue($abstractFactory->canCreate($this->container, 'weaviate.client.customer_data'));
        $this->assertFalse($abstractFactory->canCreate($this->container, 'weaviate.client.nonexistent'));
        $this->assertFalse($abstractFactory->canCreate($this->container, 'other.service.name'));

        $ragClient = $abstractFactory->__invoke($this->container, 'weaviate.client.rag');
        $this->assertInstanceOf(WeaviateClient::class, $ragClient);
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
