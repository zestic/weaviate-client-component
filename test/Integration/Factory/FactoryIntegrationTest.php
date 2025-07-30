<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Integration\Factory;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Weaviate\WeaviateClient;
use Weaviate\Auth\ApiKey;
use Zestic\WeaviateClientComponent\Factory\AuthFactory;
use Zestic\WeaviateClientComponent\Factory\ConnectionFactory;
use Zestic\WeaviateClientComponent\Factory\WeaviateClientAbstractFactory;
use Zestic\WeaviateClientComponent\Factory\WeaviateClientFactory;

class FactoryIntegrationTest extends TestCase
{
    private AuthFactory $authFactory;
    private ConnectionFactory $connectionFactory;
    private WeaviateClientFactory $clientFactory;
    private WeaviateClientAbstractFactory $abstractFactory;
    private string $weaviateUrl;

    protected function setUp(): void
    {
        $this->authFactory = new AuthFactory();
        $this->connectionFactory = new ConnectionFactory();
        $this->clientFactory = new WeaviateClientFactory($this->connectionFactory, $this->authFactory);
        $this->abstractFactory = new WeaviateClientAbstractFactory($this->clientFactory);

        // Get Weaviate URL from environment or use Docker default
        $this->weaviateUrl = $_ENV['WEAVIATE_URL'] ?? 'http://localhost:18080';

        // Skip tests if Weaviate is not available
        if (!$this->isWeaviateAvailable()) {
            $this->markTestSkipped('Weaviate instance not available at ' . $this->weaviateUrl);
        }
    }

    public function testCompleteLocalClientCreation(): void
    {
        // Parse the Docker port from the URL (18080 for Docker, 8080 for direct)
        $parsedUrl = parse_url($this->weaviateUrl);
        $port = $parsedUrl['port'] ?? 8080;

        $config = [
            'weaviate' => [
                'connection_method' => 'local',
                'connection' => [
                    'host' => 'localhost',
                    'port' => $port,
                    'secure' => false,
                    'timeout' => 30,
                ],
                // No auth needed for Docker container with anonymous access
            ],
        ];

        $container = $this->createContainer($config);
        $client = $this->clientFactory->createClient($container, 'default');

        // Test that we get a proper WeaviateClient instance
        $this->assertInstanceOf(WeaviateClient::class, $client);

        // Test that the client has the expected Weaviate API methods
        $this->assertTrue(method_exists($client, 'collections'));
        $this->assertTrue(method_exists($client, 'schema'));
        $this->assertTrue(method_exists($client, 'getAuth'));

        // Test that we can get API objects without errors
        $collections = $client->collections();
        $schema = $client->schema();
        $auth = $client->getAuth();

        $this->assertInstanceOf(\Weaviate\Collections\Collections::class, $collections);
        $this->assertInstanceOf(\Weaviate\Schema\Schema::class, $schema);
        // Auth should be null since we didn't configure any
        $this->assertNull($auth);

        // MOST IMPORTANT: Test that we can actually make requests to Weaviate
        try {
            // Try to get schema - this will make an actual HTTP request
            $schemaResult = $schema->get();
            $this->assertIsArray($schemaResult);
        } catch (\Exception $e) {
            $this->fail('Failed to connect to Weaviate: ' . $e->getMessage());
        }
    }

    public function testCloudClientCreation(): void
    {
        // This test verifies that cloud clients can be created properly
        // Note: This won't actually connect to Weaviate Cloud, just tests object creation
        $config = [
            'weaviate' => [
                'connection_method' => 'cloud',
                'connection' => [
                    'cluster_url' => 'my-test-cluster.weaviate.network',
                ],
                'auth' => [
                    'type' => 'api_key',
                    'api_key' => 'wcd-api-key-12345',
                ],
            ],
        ];

        $container = $this->createContainer($config);
        $client = $this->clientFactory->createClient($container, 'default');

        // Test that we get a proper WeaviateClient instance for cloud
        $this->assertInstanceOf(WeaviateClient::class, $client);

        // Test that the client has the expected Weaviate API methods
        $this->assertTrue(method_exists($client, 'collections'));
        $this->assertTrue(method_exists($client, 'schema'));
        $this->assertTrue(method_exists($client, 'getAuth'));

        // Test that auth is properly configured for cloud
        $auth = $client->getAuth();
        $this->assertInstanceOf(ApiKey::class, $auth);
        $this->assertNotNull($auth);
    }

    public function testCustomClientCreation(): void
    {
        // Test custom client creation (using localhost for testing)
        $parsedUrl = parse_url($this->weaviateUrl);
        $port = $parsedUrl['port'] ?? 8080;

        $config = [
            'weaviate' => [
                'connection_method' => 'custom',
                'connection' => [
                    'host' => 'localhost',
                    'port' => $port,
                    'secure' => false,
                    'timeout' => 30,
                ],
                'auth' => [
                    'type' => 'bearer_token',
                    'bearer_token' => 'test-bearer-token',
                ],
            ],
        ];

        $container = $this->createContainer($config);
        $client = $this->clientFactory->createClient($container, 'default');

        // Test that we get a proper WeaviateClient instance
        $this->assertInstanceOf(WeaviateClient::class, $client);

        // Test that auth is properly configured
        $auth = $client->getAuth();
        $this->assertInstanceOf(ApiKey::class, $auth); // Bearer token uses ApiKey class
        $this->assertNotNull($auth);

        // Test that we can get API objects
        $collections = $client->collections();
        $schema = $client->schema();

        $this->assertInstanceOf(\Weaviate\Collections\Collections::class, $collections);
        $this->assertInstanceOf(\Weaviate\Schema\Schema::class, $schema);
    }

    public function testMultipleNamedClients(): void
    {
        $parsedUrl = parse_url($this->weaviateUrl);
        $port = $parsedUrl['port'] ?? 8080;

        $config = [
            'weaviate' => [
                'clients' => [
                    'local-dev' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => 'localhost',
                            'port' => $port,
                        ],
                    ],
                    'local-test' => [
                        'connection_method' => 'custom',
                        'connection' => [
                            'host' => 'localhost',
                            'port' => $port,
                        ],
                        'auth' => [
                            'type' => 'api_key',
                            'api_key' => 'test-key',
                        ],
                    ],
                ],
            ],
        ];

        $container = $this->createContainer($config);

        // Test individual client creation
        $localClient = $this->clientFactory->createClient($container, 'local-dev');
        $testClient = $this->clientFactory->createClient($container, 'local-test');

        // Verify both are proper WeaviateClient instances
        $this->assertInstanceOf(WeaviateClient::class, $localClient);
        $this->assertInstanceOf(WeaviateClient::class, $testClient);

        // Verify auth configuration differences
        $this->assertNull($localClient->getAuth()); // No auth configured
        $this->assertInstanceOf(ApiKey::class, $testClient->getAuth()); // Auth configured

        // Test multiple clients creation
        $allClients = $this->clientFactory->createMultipleClients($container);
        $this->assertCount(2, $allClients);
        $this->assertArrayHasKey('local-dev', $allClients);
        $this->assertArrayHasKey('local-test', $allClients);

        // Verify all clients are WeaviateClient instances
        foreach ($allClients as $client) {
            $this->assertInstanceOf(WeaviateClient::class, $client);
        }

        // Test client names
        $clientNames = $this->clientFactory->getConfiguredClientNames($container);
        $this->assertEquals(['local-dev', 'local-test'], $clientNames);
    }

    public function testAbstractFactoryIntegration(): void
    {
        $parsedUrl = parse_url($this->weaviateUrl);
        $port = $parsedUrl['port'] ?? 8080;

        $config = [
            'weaviate' => [
                'clients' => [
                    'test-client' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => 'localhost',
                            'port' => $port,
                        ],
                    ],
                ],
            ],
        ];

        $container = $this->createContainer($config);

        // Test canCreate
        $this->assertTrue($this->abstractFactory->canCreate($container, 'weaviate.client.test-client'));
        $this->assertFalse($this->abstractFactory->canCreate($container, 'weaviate.client.nonexistent'));
        $this->assertFalse($this->abstractFactory->canCreate($container, 'other.service.name'));

        // Test client creation through abstract factory
        $client = $this->abstractFactory->__invoke($container, 'weaviate.client.test-client');
        $this->assertInstanceOf(WeaviateClient::class, $client);

        // Test service names
        $serviceNames = $this->abstractFactory->getCreatableServiceNames($container);
        $this->assertEquals(['weaviate.client.test-client'], $serviceNames);

        // Test utility methods
        $this->assertEquals('weaviate.client.test-client', WeaviateClientAbstractFactory::getServiceName('test-client'));
        $this->assertTrue(WeaviateClientAbstractFactory::isClientServiceName('weaviate.client.test-client'));
        $this->assertEquals('test-client', WeaviateClientAbstractFactory::extractClientNameFromServiceName('weaviate.client.test-client'));
    }

    private function createContainer(array $config): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with('config')
            ->willReturn($config);

        return $container;
    }

    /**
     * Check if Weaviate is available for testing.
     */
    private function isWeaviateAvailable(): bool
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET',
            ],
        ]);

        $result = @file_get_contents($this->weaviateUrl . '/v1/.well-known/ready', false, $context);
        return $result !== false;
    }
}
