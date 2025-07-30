<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test;

use PHPUnit\Framework\TestCase;
use Weaviate\WeaviateClient;
use Weaviate\Connection\ConnectionInterface;
use Weaviate\Auth\AuthInterface;
use Zestic\WeaviateClientComponent\ConfigProvider;
use Zestic\WeaviateClientComponent\Factory\WeaviateClientFactory;
use Zestic\WeaviateClientComponent\Factory\WeaviateClientAbstractFactory;
use Zestic\WeaviateClientComponent\Factory\ConnectionFactory;
use Zestic\WeaviateClientComponent\Factory\AuthFactory;

class ConfigProviderTest extends TestCase
{
    private ConfigProvider $configProvider;

    protected function setUp(): void
    {
        $this->configProvider = new ConfigProvider();
    }

    public function testInvokeReturnsCorrectStructure(): void
    {
        $config = ($this->configProvider)();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('dependencies', $config);
        $this->assertArrayHasKey('weaviate', $config);
    }

    public function testGetDependenciesReturnsCorrectStructure(): void
    {
        $dependencies = $this->configProvider->getDependencies();

        $this->assertIsArray($dependencies);
        $this->assertArrayHasKey('factories', $dependencies);
        $this->assertArrayHasKey('aliases', $dependencies);
        $this->assertArrayHasKey('abstract_factories', $dependencies);
    }

    public function testGetFactoriesIncludesCoreServices(): void
    {
        $factories = $this->configProvider->getFactories();

        // Core Weaviate services
        $this->assertArrayHasKey(WeaviateClient::class, $factories);
        $this->assertEquals(WeaviateClientFactory::class, $factories[WeaviateClient::class]);

        $this->assertArrayHasKey(ConnectionInterface::class, $factories);
        $this->assertEquals(ConnectionFactory::class, $factories[ConnectionInterface::class]);

        $this->assertArrayHasKey(AuthInterface::class, $factories);
        $this->assertEquals(AuthFactory::class, $factories[AuthInterface::class]);
    }

    public function testGetFactoriesIncludesNamedClients(): void
    {
        $factories = $this->configProvider->getFactories();

        // Named client factories
        $this->assertArrayHasKey('weaviate.client.default', $factories);
        $this->assertEquals(WeaviateClientFactory::class, $factories['weaviate.client.default']);
    }

    public function testGetFactoriesIncludesFactoryServices(): void
    {
        $factories = $this->configProvider->getFactories();

        // Factory services themselves
        $this->assertArrayHasKey(WeaviateClientFactory::class, $factories);
        $this->assertArrayHasKey(ConnectionFactory::class, $factories);
        $this->assertArrayHasKey(AuthFactory::class, $factories);
        $this->assertArrayHasKey(WeaviateClientAbstractFactory::class, $factories);
    }

    public function testGetAliasesIncludesConvenientAliases(): void
    {
        $aliases = $this->configProvider->getAliases();

        $this->assertArrayHasKey('WeaviateClient', $aliases);
        $this->assertEquals(WeaviateClient::class, $aliases['WeaviateClient']);

        $this->assertArrayHasKey('weaviate.client', $aliases);
        $this->assertEquals('weaviate.client.default', $aliases['weaviate.client']);
    }

    public function testGetAliasesIncludesFactoryAliases(): void
    {
        $aliases = $this->configProvider->getAliases();

        $this->assertArrayHasKey('weaviate.factory.client', $aliases);
        $this->assertEquals(WeaviateClientFactory::class, $aliases['weaviate.factory.client']);

        $this->assertArrayHasKey('weaviate.factory.connection', $aliases);
        $this->assertEquals(ConnectionFactory::class, $aliases['weaviate.factory.connection']);

        $this->assertArrayHasKey('weaviate.factory.auth', $aliases);
        $this->assertEquals(AuthFactory::class, $aliases['weaviate.factory.auth']);
    }

    public function testGetAbstractFactoriesIncludesWeaviateClientAbstractFactory(): void
    {
        $abstractFactories = $this->configProvider->getAbstractFactories();

        $this->assertIsArray($abstractFactories);
        $this->assertContains(WeaviateClientAbstractFactory::class, $abstractFactories);
    }

    public function testGetWeaviateConfigReturnsDefaultConfiguration(): void
    {
        $weaviateConfig = $this->configProvider->getWeaviateConfig();

        $this->assertIsArray($weaviateConfig);
        $this->assertArrayHasKey('connection_method', $weaviateConfig);
        $this->assertEquals('local', $weaviateConfig['connection_method']);

        $this->assertArrayHasKey('connection', $weaviateConfig);
        $this->assertIsArray($weaviateConfig['connection']);
        $this->assertEquals('localhost', $weaviateConfig['connection']['host']);
        $this->assertEquals(8080, $weaviateConfig['connection']['port']);
        $this->assertFalse($weaviateConfig['connection']['secure']);

        $this->assertArrayHasKey('enable_retry', $weaviateConfig);
        $this->assertTrue($weaviateConfig['enable_retry']);

        $this->assertArrayHasKey('max_retries', $weaviateConfig);
        $this->assertEquals(4, $weaviateConfig['max_retries']);

        $this->assertArrayHasKey('additional_headers', $weaviateConfig);
        $this->assertEquals([], $weaviateConfig['additional_headers']);
    }

    public function testGetConfiguredClientNamesWithNoClientsConfig(): void
    {
        $config = ['weaviate' => []];
        $clientNames = $this->configProvider->getConfiguredClientNames($config);

        $this->assertEquals(['default'], $clientNames);
    }

    public function testGetConfiguredClientNamesWithClientsConfig(): void
    {
        $config = [
            'weaviate' => [
                'clients' => [
                    'default' => [],
                    'rag' => [],
                    'customer_data' => [],
                ],
            ],
        ];

        $clientNames = $this->configProvider->getConfiguredClientNames($config);

        $this->assertEquals(['default', 'rag', 'customer_data'], $clientNames);
    }

    public function testHasClientWithNoClientsConfig(): void
    {
        $config = ['weaviate' => []];

        $this->assertTrue($this->configProvider->hasClient($config, 'default'));
        $this->assertFalse($this->configProvider->hasClient($config, 'rag'));
        $this->assertFalse($this->configProvider->hasClient($config, 'nonexistent'));
    }

    public function testHasClientWithClientsConfig(): void
    {
        $config = [
            'weaviate' => [
                'clients' => [
                    'default' => [],
                    'rag' => [],
                ],
            ],
        ];

        $this->assertTrue($this->configProvider->hasClient($config, 'default'));
        $this->assertTrue($this->configProvider->hasClient($config, 'rag'));
        $this->assertFalse($this->configProvider->hasClient($config, 'nonexistent'));
    }

    public function testGetCreatableServiceNames(): void
    {
        $config = [
            'weaviate' => [
                'clients' => [
                    'default' => [],
                    'rag' => [],
                ],
            ],
        ];

        $serviceNames = $this->configProvider->getCreatableServiceNames($config);

        $this->assertContains(WeaviateClient::class, $serviceNames);
        $this->assertContains('WeaviateClient', $serviceNames);
        $this->assertContains('weaviate.client', $serviceNames);
        $this->assertContains('weaviate.client.default', $serviceNames);
        $this->assertContains('weaviate.client.rag', $serviceNames);
    }

    public function testValidateConfigurationWithValidConfig(): void
    {
        $config = [
            'weaviate' => [
                'clients' => [
                    'default' => [
                        'connection_method' => 'local',
                        'connection' => ['host' => 'localhost'],
                    ],
                    'cloud_client' => [
                        'connection_method' => 'cloud',
                        'connection' => ['cluster_url' => 'my-cluster.weaviate.network'],
                        'auth' => ['type' => 'api_key', 'api_key' => 'test-key'],
                    ],
                ],
            ],
        ];

        $errors = $this->configProvider->validateConfiguration($config);
        $this->assertEmpty($errors);
    }

    public function testValidateConfigurationWithInvalidConnectionMethod(): void
    {
        $config = [
            'weaviate' => [
                'clients' => [
                    'invalid_client' => [
                        'connection_method' => 'invalid',
                    ],
                ],
            ],
        ];

        $errors = $this->configProvider->validateConfiguration($config);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('invalid connection method', $errors[0]);
    }

    public function testValidateConfigurationWithMissingCloudRequirements(): void
    {
        $config = [
            'weaviate' => [
                'clients' => [
                    'cloud_client' => [
                        'connection_method' => 'cloud',
                        // Missing cluster_url and auth
                    ],
                ],
            ],
        ];

        $errors = $this->configProvider->validateConfiguration($config);
        $this->assertCount(2, $errors);
        $this->assertStringContainsString('requires cluster_url', $errors[0]);
        $this->assertStringContainsString('requires auth configuration', $errors[1]);
    }

    public function testValidateConfigurationWithMissingCustomHost(): void
    {
        $config = [
            'weaviate' => [
                'clients' => [
                    'custom_client' => [
                        'connection_method' => 'custom',
                        'connection' => [
                            // Missing host
                            'port' => 9200,
                        ],
                    ],
                ],
            ],
        ];

        $errors = $this->configProvider->validateConfiguration($config);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('requires host', $errors[0]);
    }

    public function testGetConfigurationSummary(): void
    {
        $config = [
            'weaviate' => [
                'clients' => [
                    'default' => ['connection_method' => 'local'],
                    'rag' => ['connection_method' => 'local'],
                ],
            ],
        ];

        $summary = $this->configProvider->getConfigurationSummary($config);

        $this->assertIsArray($summary);
        $this->assertTrue($summary['has_weaviate_config']);
        $this->assertTrue($summary['has_clients_config']);
        $this->assertEquals(2, $summary['client_count']);
        $this->assertEquals(['default', 'rag'], $summary['client_names']);
        $this->assertIsArray($summary['creatable_services']);
        $this->assertIsArray($summary['validation_errors']);
    }
}
