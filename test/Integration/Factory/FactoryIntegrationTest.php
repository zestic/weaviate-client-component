<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Integration\Factory;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
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

    protected function setUp(): void
    {
        $this->authFactory = new AuthFactory();
        $this->connectionFactory = new ConnectionFactory();
        $this->clientFactory = new WeaviateClientFactory($this->connectionFactory, $this->authFactory);
        $this->abstractFactory = new WeaviateClientAbstractFactory($this->clientFactory);
    }

    public function testCompleteLocalClientCreation(): void
    {
        $config = [
            'weaviate' => [
                'connection_method' => 'local',
                'connection' => [
                    'host' => 'localhost',
                    'port' => 8080,
                    'secure' => false,
                    'timeout' => 30,
                ],
                'auth' => [
                    'type' => 'api_key',
                    'api_key' => 'test-local-key',
                ],
                'additional_headers' => [
                    'X-Custom-Header' => 'local-value',
                ],
                'enable_retry' => true,
                'max_retries' => 3,
            ],
        ];

        $container = $this->createContainer($config);
        $client = $this->clientFactory->createClient($container, 'default');

        // Verify client structure
        $this->assertEquals('local', $client['type']);
        $this->assertTrue($client['enable_retry']);
        $this->assertEquals(3, $client['max_retries']);
        $this->assertEquals(['X-Custom-Header' => 'local-value'], $client['additional_headers']);

        // Verify connection
        $connection = $client['connection'];
        $this->assertEquals('http://localhost:8080', $connection['url']);
        $this->assertEquals('localhost', $connection['host']);
        $this->assertEquals(8080, $connection['port']);
        $this->assertFalse($connection['secure']);
        $this->assertEquals(30, $connection['timeout']);
        $this->assertTrue($connection['is_local']);
        $this->assertFalse($connection['is_cloud']);

        // Verify auth
        $auth = $client['auth'];
        $this->assertEquals('api_key', $auth['type']);
        $this->assertEquals('test-local-key', $auth['api_key']);
        $this->assertEquals(['Authorization' => 'Bearer test-local-key'], $auth['headers']);
    }

    public function testCompleteCloudClientCreation(): void
    {
        $config = [
            'weaviate' => [
                'connection_method' => 'cloud',
                'connection' => [
                    'cluster_url' => 'my-test-cluster.weaviate.network',
                    'secure' => true,
                    'timeout' => 60,
                ],
                'auth' => [
                    'type' => 'api_key',
                    'api_key' => 'wcd-api-key-12345',
                ],
                'additional_headers' => [
                    'X-OpenAI-Api-Key' => 'openai-key-67890',
                    'X-Cohere-Api-Key' => 'cohere-key-abcdef',
                ],
            ],
        ];

        $container = $this->createContainer($config);
        $client = $this->clientFactory->createClient($container, 'default');

        // Verify client structure
        $this->assertEquals('cloud', $client['type']);
        $this->assertTrue($client['enable_retry']);
        $this->assertEquals(4, $client['max_retries']); // Default value

        // Verify connection
        $connection = $client['connection'];
        $this->assertEquals('https://my-test-cluster.weaviate.network', $connection['url']);
        $this->assertEquals('my-test-cluster.weaviate.network', $connection['cluster_url']);
        $this->assertTrue($connection['secure']);
        $this->assertEquals(60, $connection['timeout']);
        $this->assertFalse($connection['is_local']);
        $this->assertTrue($connection['is_cloud']);

        // Verify auth
        $auth = $client['auth'];
        $this->assertEquals('api_key', $auth['type']);
        $this->assertEquals('wcd-api-key-12345', $auth['api_key']);
        $this->assertEquals(['Authorization' => 'Bearer wcd-api-key-12345'], $auth['headers']);

        // Verify additional headers
        $expectedHeaders = [
            'X-OpenAI-Api-Key' => 'openai-key-67890',
            'X-Cohere-Api-Key' => 'cohere-key-abcdef',
        ];
        $this->assertEquals($expectedHeaders, $client['additional_headers']);
    }

    public function testCompleteCustomClientCreation(): void
    {
        $config = [
            'weaviate' => [
                'connection_method' => 'custom',
                'connection' => [
                    'host' => 'my-custom-weaviate.example.com',
                    'port' => 9200,
                    'secure' => true,
                    'timeout' => 45,
                    'headers' => [
                        'X-Custom-Connection-Header' => 'connection-value',
                    ],
                ],
                'auth' => [
                    'type' => 'bearer_token',
                    'bearer_token' => 'custom-bearer-token-xyz',
                ],
                'enable_retry' => false,
                'max_retries' => 0,
            ],
        ];

        $container = $this->createContainer($config);
        $client = $this->clientFactory->createClient($container, 'default');

        // Verify client structure
        $this->assertEquals('custom', $client['type']);
        $this->assertFalse($client['enable_retry']);
        $this->assertEquals(0, $client['max_retries']);

        // Verify connection
        $connection = $client['connection'];
        $this->assertEquals('https://my-custom-weaviate.example.com:9200', $connection['url']);
        $this->assertEquals('my-custom-weaviate.example.com', $connection['host']);
        $this->assertEquals(9200, $connection['port']);
        $this->assertTrue($connection['secure']);
        $this->assertEquals(45, $connection['timeout']);
        $this->assertEquals(['X-Custom-Connection-Header' => 'connection-value'], $connection['headers']);
        $this->assertFalse($connection['is_local']);
        $this->assertFalse($connection['is_cloud']);

        // Verify auth
        $auth = $client['auth'];
        $this->assertEquals('bearer_token', $auth['type']);
        $this->assertEquals('custom-bearer-token-xyz', $auth['bearer_token']);
        $this->assertEquals(['Authorization' => 'Bearer custom-bearer-token-xyz'], $auth['headers']);
    }

    public function testMultipleNamedClients(): void
    {
        $config = [
            'weaviate' => [
                'clients' => [
                    'local-dev' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => 'localhost',
                            'port' => 8080,
                        ],
                        'auth' => [
                            'type' => 'api_key',
                            'api_key' => 'dev-key',
                        ],
                    ],
                    'staging' => [
                        'connection_method' => 'cloud',
                        'connection' => [
                            'cluster_url' => 'staging-cluster.weaviate.network',
                        ],
                        'auth' => [
                            'type' => 'api_key',
                            'api_key' => 'staging-key',
                        ],
                    ],
                    'production' => [
                        'connection_method' => 'cloud',
                        'connection' => [
                            'cluster_url' => 'prod-cluster.weaviate.network',
                        ],
                        'auth' => [
                            'type' => 'api_key',
                            'api_key' => 'prod-key',
                        ],
                        'additional_headers' => [
                            'X-Environment' => 'production',
                        ],
                    ],
                ],
            ],
        ];

        $container = $this->createContainer($config);

        // Test individual client creation
        $localClient = $this->clientFactory->createClient($container, 'local-dev');
        $stagingClient = $this->clientFactory->createClient($container, 'staging');
        $prodClient = $this->clientFactory->createClient($container, 'production');

        // Verify local client
        $this->assertEquals('local', $localClient['type']);
        $this->assertEquals('http://localhost:8080', $localClient['connection']['url']);
        $this->assertEquals('dev-key', $localClient['auth']['api_key']);

        // Verify staging client
        $this->assertEquals('cloud', $stagingClient['type']);
        $this->assertEquals('https://staging-cluster.weaviate.network', $stagingClient['connection']['url']);
        $this->assertEquals('staging-key', $stagingClient['auth']['api_key']);

        // Verify production client
        $this->assertEquals('cloud', $prodClient['type']);
        $this->assertEquals('https://prod-cluster.weaviate.network', $prodClient['connection']['url']);
        $this->assertEquals('prod-key', $prodClient['auth']['api_key']);
        $this->assertEquals(['X-Environment' => 'production'], $prodClient['additional_headers']);

        // Test multiple clients creation
        $allClients = $this->clientFactory->createMultipleClients($container);
        $this->assertCount(3, $allClients);
        $this->assertArrayHasKey('local-dev', $allClients);
        $this->assertArrayHasKey('staging', $allClients);
        $this->assertArrayHasKey('production', $allClients);

        // Test client names
        $clientNames = $this->clientFactory->getConfiguredClientNames($container);
        $this->assertEquals(['local-dev', 'staging', 'production'], $clientNames);
    }

    public function testAbstractFactoryIntegration(): void
    {
        $config = [
            'weaviate' => [
                'clients' => [
                    'test-client' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => 'localhost',
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

        // Test canCreate
        $this->assertTrue($this->abstractFactory->canCreate($container, 'weaviate.client.test-client'));
        $this->assertFalse($this->abstractFactory->canCreate($container, 'weaviate.client.nonexistent'));
        $this->assertFalse($this->abstractFactory->canCreate($container, 'other.service.name'));

        // Test client creation through abstract factory
        $client = $this->abstractFactory->__invoke($container, 'weaviate.client.test-client');
        $this->assertEquals('local', $client['type']);
        $this->assertEquals('test-key', $client['auth']['api_key']);

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
}
