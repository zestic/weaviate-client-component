<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Integration;

use PHPUnit\Framework\TestCase;
use Laminas\ServiceManager\ServiceManager;
use Weaviate\WeaviateClient;
use Weaviate\Connection\ConnectionInterface;
use Weaviate\Auth\AuthInterface;
use Zestic\WeaviateClientComponent\ConfigProvider;
use Zestic\WeaviateClientComponent\Factory\WeaviateClientFactory;
use Zestic\WeaviateClientComponent\Factory\WeaviateClientAbstractFactory;
use Zestic\WeaviateClientComponent\Factory\ConnectionFactory;
use Zestic\WeaviateClientComponent\Factory\AuthFactory;

/**
 * Integration test for ConfigProvider with Laminas ServiceManager.
 *
 * Tests that the ConfigProvider properly integrates with the service container
 * and all services can be resolved correctly.
 */
class ConfigProviderIntegrationTest extends TestCase
{
    private ServiceManager $container;
    private ConfigProvider $configProvider;
    private string $weaviateUrl;

    protected function setUp(): void
    {
        $this->configProvider = new ConfigProvider();
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
                ],
                'enable_retry' => true,
                'max_retries' => 3,
                'clients' => [
                    'default' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => $host,
                            'port' => $port,
                        ],
                    ],
                    'test_client' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => $host,
                            'port' => $port,
                        ],
                        'additional_headers' => ['X-Test-Client' => 'integration'],
                    ],
                ],
            ],
        ];

        $dependencies = $this->configProvider->getDependencies();
        $this->container = new ServiceManager(array_merge($dependencies, [
            'services' => ['config' => $config]
        ]));
    }

    public function testConfigProviderRegistersAllServices(): void
    {
        $config = ($this->configProvider)();

        $this->assertArrayHasKey('dependencies', $config);
        $this->assertArrayHasKey('weaviate', $config);

        $dependencies = $config['dependencies'];
        $this->assertArrayHasKey('factories', $dependencies);
        $this->assertArrayHasKey('aliases', $dependencies);
        $this->assertArrayHasKey('abstract_factories', $dependencies);
    }

    public function testCoreServicesCanBeResolved(): void
    {
        // Test core WeaviateClient
        $client = $this->container->get(WeaviateClient::class);
        $this->assertInstanceOf(WeaviateClient::class, $client);

        // Test WeaviateClient alias
        $clientAlias = $this->container->get('WeaviateClient');
        $this->assertInstanceOf(WeaviateClient::class, $clientAlias);

        // Test default client
        $defaultClient = $this->container->get('weaviate.client.default');
        $this->assertInstanceOf(WeaviateClient::class, $defaultClient);

        // Test client alias
        $clientShortAlias = $this->container->get('weaviate.client');
        $this->assertInstanceOf(WeaviateClient::class, $clientShortAlias);
    }

    public function testFactoryServicesCanBeResolved(): void
    {
        $clientFactory = $this->container->get(WeaviateClientFactory::class);
        $this->assertInstanceOf(WeaviateClientFactory::class, $clientFactory);

        $connectionFactory = $this->container->get(ConnectionFactory::class);
        $this->assertInstanceOf(ConnectionFactory::class, $connectionFactory);

        $authFactory = $this->container->get(AuthFactory::class);
        $this->assertInstanceOf(AuthFactory::class, $authFactory);

        $abstractFactory = $this->container->get(WeaviateClientAbstractFactory::class);
        $this->assertInstanceOf(WeaviateClientAbstractFactory::class, $abstractFactory);
    }

    public function testFactoryAliasesWork(): void
    {
        $clientFactory = $this->container->get('weaviate.factory.client');
        $this->assertInstanceOf(WeaviateClientFactory::class, $clientFactory);

        $connectionFactory = $this->container->get('weaviate.factory.connection');
        $this->assertInstanceOf(ConnectionFactory::class, $connectionFactory);

        $authFactory = $this->container->get('weaviate.factory.auth');
        $this->assertInstanceOf(AuthFactory::class, $authFactory);
    }

    public function testAbstractFactoryWorksWithServiceManager(): void
    {
        // Test that abstract factory can create configured clients
        $testClient = $this->container->get('weaviate.client.test_client');
        $this->assertInstanceOf(WeaviateClient::class, $testClient);
    }

    public function testAbstractFactoryRejectsNonConfiguredClients(): void
    {
        // Test that abstract factory rejects non-configured clients
        $this->expectException(\Laminas\ServiceManager\Exception\ServiceNotFoundException::class);
        $this->container->get('weaviate.client.nonexistent');
    }

    public function testServiceManagerCanCreateMultipleClientInstances(): void
    {
        $client1 = $this->container->get('weaviate.client.default');
        $client2 = $this->container->get('weaviate.client.test_client');

        $this->assertInstanceOf(WeaviateClient::class, $client1);
        $this->assertInstanceOf(WeaviateClient::class, $client2);

        // They should be different instances
        $this->assertNotSame($client1, $client2);
    }

    public function testConfigProviderValidationWorks(): void
    {
        $validConfig = [
            'weaviate' => [
                'clients' => [
                    'valid_client' => [
                        'connection_method' => 'local',
                        'connection' => ['host' => 'localhost'],
                    ],
                ],
            ],
        ];

        $errors = $this->configProvider->validateConfiguration($validConfig);
        $this->assertEmpty($errors);

        $invalidConfig = [
            'weaviate' => [
                'clients' => [
                    'invalid_client' => [
                        'connection_method' => 'invalid_method',
                    ],
                ],
            ],
        ];

        $errors = $this->configProvider->validateConfiguration($invalidConfig);
        $this->assertNotEmpty($errors);
    }

    public function testConfigProviderHelperMethods(): void
    {
        $config = $this->container->get('config');

        // Test getConfiguredClientNames
        $clientNames = $this->configProvider->getConfiguredClientNames($config);
        $this->assertContains('default', $clientNames);
        $this->assertContains('test_client', $clientNames);

        // Test hasClient
        $this->assertTrue($this->configProvider->hasClient($config, 'default'));
        $this->assertTrue($this->configProvider->hasClient($config, 'test_client'));
        $this->assertFalse($this->configProvider->hasClient($config, 'nonexistent'));

        // Test getCreatableServiceNames
        $serviceNames = $this->configProvider->getCreatableServiceNames($config);
        $this->assertContains(WeaviateClient::class, $serviceNames);
        $this->assertContains('weaviate.client.default', $serviceNames);
        $this->assertContains('weaviate.client.test_client', $serviceNames);

        // Test getConfigurationSummary
        $summary = $this->configProvider->getConfigurationSummary($config);
        $this->assertIsArray($summary);
        $this->assertTrue($summary['has_weaviate_config']);
        $this->assertTrue($summary['has_clients_config']);
        $this->assertEquals(2, $summary['client_count']);
    }

    public function testRealWeaviateConnectionThroughConfigProvider(): void
    {
        if (!$this->isWeaviateAvailable()) {
            $this->markTestSkipped('Weaviate is not available for integration testing');
        }

        $client = $this->container->get(WeaviateClient::class);

        // Test that we can actually connect to Weaviate
        $schema = $client->schema()->get();
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('classes', $schema);

        // Test that we can perform basic operations
        $collections = $client->collections();
        $this->assertInstanceOf(\Weaviate\Collections\Collections::class, $collections);
    }

    public function testConfigProviderWithMinimalConfiguration(): void
    {
        // Test with minimal configuration
        $minimalConfig = [
            'weaviate' => [
                'connection_method' => 'local',
            ],
        ];

        $dependencies = $this->configProvider->getDependencies();
        $minimalContainer = new ServiceManager(array_merge($dependencies, [
            'services' => ['config' => $minimalConfig]
        ]));

        $client = $minimalContainer->get(WeaviateClient::class);
        $this->assertInstanceOf(WeaviateClient::class, $client);
    }

    public function testConfigProviderWithBackwardCompatibility(): void
    {
        // Test backward compatibility - root level configuration without 'clients'
        $backwardCompatConfig = [
            'weaviate' => [
                'connection_method' => 'local',
                'connection' => [
                    'host' => 'localhost',
                    'port' => 8080,
                ],
            ],
        ];

        $dependencies = $this->configProvider->getDependencies();
        $backwardContainer = new ServiceManager(array_merge($dependencies, [
            'services' => ['config' => $backwardCompatConfig]
        ]));

        $client = $backwardContainer->get(WeaviateClient::class);
        $this->assertInstanceOf(WeaviateClient::class, $client);

        $defaultClient = $backwardContainer->get('weaviate.client.default');
        $this->assertInstanceOf(WeaviateClient::class, $defaultClient);
    }

    public function testServiceManagerCaching(): void
    {
        // Test that the service manager properly caches instances
        $client1 = $this->container->get(WeaviateClient::class);
        $client2 = $this->container->get(WeaviateClient::class);

        // Should be the same instance due to service manager caching
        $this->assertSame($client1, $client2);

        $factory1 = $this->container->get(WeaviateClientFactory::class);
        $factory2 = $this->container->get(WeaviateClientFactory::class);
        
        // Factories should also be cached
        $this->assertSame($factory1, $factory2);
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
