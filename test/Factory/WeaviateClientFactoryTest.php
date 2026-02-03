<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Factory;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zestic\WeaviateClientComponent\Configuration\WeaviateConfig;
use Zestic\WeaviateClientComponent\Exception\ConfigurationException;
use Zestic\WeaviateClientComponent\Factory\AuthFactory;
use Zestic\WeaviateClientComponent\Factory\ConnectionFactory;
use Zestic\WeaviateClientComponent\Factory\WeaviateClientFactory;

class WeaviateClientFactoryTest extends TestCase
{
    private WeaviateClientFactory $factory;
    private ConnectionFactory $connectionFactory;
    private AuthFactory $authFactory;

    protected function setUp(): void
    {
        $this->connectionFactory = new ConnectionFactory();
        $this->authFactory = new AuthFactory();
        $this->factory = new WeaviateClientFactory($this->connectionFactory, $this->authFactory);
    }

    public function testInvokeCreatesDefaultClient(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'default' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => 'localhost',
                            'port' => 8080,
                        ],
                    ],
                ],
            ],
        ]);

        $result = ($this->factory)($container);

        // Now we expect an actual WeaviateClient object
        $this->assertInstanceOf(\Weaviate\WeaviateClient::class, $result);

        // We can test that the client is functional by checking it has the expected methods
        $this->assertTrue(method_exists($result, 'collections'));
        $this->assertTrue(method_exists($result, 'schema'));
    }

    public function testCreateLocalClient(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'default' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => 'localhost',
                            'port' => 8080,
                        ],
                        'auth' => [
                            'type' => 'api_key',
                            'api_key' => 'test-key',
                        ],
                        'additional_headers' => ['X-Custom' => 'value'],
                        'enable_retry' => false,
                        'max_retries' => 2,
                    ],
                ],
            ],
        ]);

        $result = $this->factory->createClient($container, 'default');

        // Test that we get a proper WeaviateClient instance
        $this->assertInstanceOf(\Weaviate\WeaviateClient::class, $result);

        // Test that the client has the expected functionality
        $this->assertTrue(method_exists($result, 'collections'));
        $this->assertTrue(method_exists($result, 'schema'));

        // Note: We can't easily test the internal configuration (auth, headers, etc.)
        // without making actual HTTP requests or exposing internal state.
        // In a real scenario, you might test by making actual API calls
        // or by mocking the HTTP client and verifying the requests.
    }

    public function testCreateCloudClient(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'default' => [
                        'connection_method' => 'cloud',
                        'connection' => [
                            'cluster_url' => 'my-cluster.weaviate.network',
                        ],
                        'auth' => [
                            'type' => 'api_key',
                            'api_key' => 'test-key',
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->factory->createClient($container, 'default');

        // Test that we get a proper WeaviateClient instance for cloud connection
        $this->assertInstanceOf(\Weaviate\WeaviateClient::class, $result);

        // Test that the client has the expected functionality
        $this->assertTrue(method_exists($result, 'collections'));
        $this->assertTrue(method_exists($result, 'schema'));

        // For cloud clients, we could potentially test that they're configured
        // to use HTTPS by default, but this would require inspecting internal state
        // or making actual requests
    }

    public function testCreateCloudClientMissingClusterUrl(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'default' => [
                        'connection_method' => 'cloud',
                        'connection' => [],
                        'auth' => [
                            'type' => 'api_key',
                            'api_key' => 'test-key',
                        ],
                    ],
                ],
            ],
        ]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'cluster_url' in cloud connection");

        $this->factory->createClient($container, 'default');
    }

    public function testCreateCloudClientMissingAuth(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'default' => [
                        'connection_method' => 'cloud',
                        'connection' => [
                            'cluster_url' => 'my-cluster.weaviate.network',
                        ],
                    ],
                ],
            ],
        ]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'auth' in cloud connection");

        $this->factory->createClient($container, 'default');
    }

    public function testCreateCustomClient(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'default' => [
                        'connection_method' => 'custom',
                        'connection' => [
                            'host' => 'example.com',
                            'port' => 9200,
                            'secure' => true,
                        ],
                        'auth' => [
                            'type' => 'bearer_token',
                            'bearer_token' => 'test-token',
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->factory->createClient($container, 'default');

        // Test that we get a proper WeaviateClient instance for custom connection
        $this->assertInstanceOf(\Weaviate\WeaviateClient::class, $result);

        // Test that the client has the expected functionality
        $this->assertTrue(method_exists($result, 'collections'));
        $this->assertTrue(method_exists($result, 'schema'));
    }

    public function testCreateCustomClientMissingHost(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'default' => [
                        'connection_method' => 'custom',
                        'connection' => [
                            'port' => 9200,
                        ],
                    ],
                ],
            ],
        ]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'host' in custom connection");

        $this->factory->createClient($container, 'default');
    }

    public function testCreateClientWithNamedClient(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'test-client' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => 'test-host',
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->factory->createClient($container, 'test-client');

        // Test that we get a proper WeaviateClient instance
        $this->assertInstanceOf(\Weaviate\WeaviateClient::class, $result);

        // Test that the client has the expected functionality
        $this->assertTrue(method_exists($result, 'collections'));
        $this->assertTrue(method_exists($result, 'schema'));
    }

    public function testCreateClientNotFound(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [],
            ],
        ]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Weaviate client 'nonexistent' not configured");

        $this->factory->createClient($container, 'nonexistent');
    }

    public function testCreateMultipleClients(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'client1' => [
                        'connection_method' => 'local',
                        'connection' => ['host' => 'host1'],
                    ],
                    'client2' => [
                        'connection_method' => 'local',
                        'connection' => ['host' => 'host2'],
                    ],
                ],
            ],
        ]);

        $result = $this->factory->createMultipleClients($container);

        $this->assertArrayHasKey('client1', $result);
        $this->assertArrayHasKey('client2', $result);

        // Test that we get proper WeaviateClient instances
        $this->assertInstanceOf(\Weaviate\WeaviateClient::class, $result['client1']);
        $this->assertInstanceOf(\Weaviate\WeaviateClient::class, $result['client2']);

        // Test that the clients have the expected functionality
        $this->assertTrue(method_exists($result['client1'], 'collections'));
        $this->assertTrue(method_exists($result['client1'], 'schema'));
        $this->assertTrue(method_exists($result['client2'], 'collections'));
        $this->assertTrue(method_exists($result['client2'], 'schema'));
    }

    public function testCreateMultipleClientsWithRootConfig(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'default' => [
                        'connection_method' => 'local',
                        'connection' => ['host' => 'localhost'],
                    ],
                ],
            ],
        ]);

        $result = $this->factory->createMultipleClients($container);

        $this->assertArrayHasKey('default', $result);

        // Test that we get a proper WeaviateClient instance
        $this->assertInstanceOf(\Weaviate\WeaviateClient::class, $result['default']);

        // Test that the client has the expected functionality
        $this->assertTrue(method_exists($result['default'], 'collections'));
        $this->assertTrue(method_exists($result['default'], 'schema'));
    }

    public function testGetConfiguredClientNames(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'client1' => [],
                    'client2' => [],
                ],
            ],
        ]);

        $names = $this->factory->getConfiguredClientNames($container);

        $this->assertEquals(['client1', 'client2'], $names);
    }

    public function testHasClient(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'existing-client' => [],
                ],
            ],
        ]);

        $this->assertTrue($this->factory->hasClient($container, 'existing-client'));
        $this->assertFalse($this->factory->hasClient($container, 'nonexistent-client'));
    }

    public function testInvalidConnectionMethod(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'default' => [
                        'connection_method' => 'invalid',
                    ],
                ],
            ],
        ]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            "Invalid connection method: 'invalid'. Must be one of: 'local', 'cloud', 'custom'"
        );

        $this->factory->createClient($container, 'default');
    }

    private function createMockContainer(array $config): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with('config')
            ->willReturn($config);

        return $container;
    }
}
