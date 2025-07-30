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
                'connection_method' => 'local',
                'connection' => [
                    'host' => 'localhost',
                    'port' => 8080,
                ],
            ],
        ]);

        $result = ($this->factory)($container);

        $this->assertEquals('local', $result['type']);
        $this->assertArrayHasKey('connection', $result);
        $this->assertNull($result['auth']);
        $this->assertEquals([], $result['additional_headers']);
        $this->assertTrue($result['enable_retry']);
        $this->assertEquals(4, $result['max_retries']);
    }

    public function testCreateLocalClient(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
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
        ]);

        $result = $this->factory->createClient($container, 'default');

        $this->assertEquals('local', $result['type']);
        $this->assertArrayHasKey('connection', $result);
        $this->assertArrayHasKey('auth', $result);
        $this->assertEquals(['X-Custom' => 'value'], $result['additional_headers']);
        $this->assertFalse($result['enable_retry']);
        $this->assertEquals(2, $result['max_retries']);
    }

    public function testCreateCloudClient(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'connection_method' => 'cloud',
                'connection' => [
                    'cluster_url' => 'my-cluster.weaviate.network',
                ],
                'auth' => [
                    'type' => 'api_key',
                    'api_key' => 'test-key',
                ],
            ],
        ]);

        $result = $this->factory->createClient($container, 'default');

        $this->assertEquals('cloud', $result['type']);
        $this->assertArrayHasKey('connection', $result);
        $this->assertArrayHasKey('auth', $result);
        $this->assertEquals('https://my-cluster.weaviate.network', $result['connection']['url']);
    }

    public function testCreateCloudClientMissingClusterUrl(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'connection_method' => 'cloud',
                'connection' => [],
                'auth' => [
                    'type' => 'api_key',
                    'api_key' => 'test-key',
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
                'connection_method' => 'cloud',
                'connection' => [
                    'cluster_url' => 'my-cluster.weaviate.network',
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
        ]);

        $result = $this->factory->createClient($container, 'default');

        $this->assertEquals('custom', $result['type']);
        $this->assertArrayHasKey('connection', $result);
        $this->assertArrayHasKey('auth', $result);
        $this->assertEquals('https://example.com:9200', $result['connection']['url']);
    }

    public function testCreateCustomClientMissingHost(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'connection_method' => 'custom',
                'connection' => [
                    'port' => 9200,
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

        $this->assertEquals('local', $result['type']);
        $this->assertEquals('http://test-host:8080', $result['connection']['url']);
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
        $this->assertEquals('http://host1:8080', $result['client1']['connection']['url']);
        $this->assertEquals('http://host2:8080', $result['client2']['connection']['url']);
    }

    public function testCreateMultipleClientsWithRootConfig(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'connection_method' => 'local',
                'connection' => ['host' => 'localhost'],
            ],
        ]);

        $result = $this->factory->createMultipleClients($container);

        $this->assertArrayHasKey('default', $result);
        $this->assertEquals('http://localhost:8080', $result['default']['connection']['url']);
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

    public function testGetConfiguredClientNamesWithRootConfig(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'connection_method' => 'local',
            ],
        ]);

        $names = $this->factory->getConfiguredClientNames($container);

        $this->assertEquals(['default'], $names);
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

    public function testHasClientWithRootConfig(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'connection_method' => 'local',
            ],
        ]);

        $this->assertTrue($this->factory->hasClient($container, 'default'));
        $this->assertFalse($this->factory->hasClient($container, 'other'));
    }

    public function testValidateClientConfigValid(): void
    {
        $clientConfig = [
            'connection_method' => 'local',
            'connection' => ['host' => 'localhost'],
        ];

        $this->assertTrue($this->factory->validateClientConfig($clientConfig));
    }

    public function testValidateClientConfigInvalid(): void
    {
        $clientConfig = [
            'connection_method' => 'invalid',
        ];

        $this->assertFalse($this->factory->validateClientConfig($clientConfig));
    }

    public function testInvalidConnectionMethod(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'connection_method' => 'invalid',
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
