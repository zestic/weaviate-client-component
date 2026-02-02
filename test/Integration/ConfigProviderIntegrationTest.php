<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Integration;

use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use Weaviate\WeaviateClient;
use Zestic\WeaviateClientComponent\ConfigProvider;
use Zestic\WeaviateClientComponent\Exception\ConfigurationException;

/**
 * @covers \Zestic\WeaviateClientComponent\ConfigProvider
 * @covers \Zestic\WeaviateClientComponent\Factory\WeaviateClientFactory
 * @covers \Zestic\WeaviateClientComponent\Factory\WeaviateClientAbstractFactory
 */
class ConfigProviderIntegrationTest extends TestCase
{
    private ConfigProvider $configProvider;
    private ServiceManager $container;
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
                'clients' => [
                    'default' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => $host,
                            'port' => $port,
                            'secure' => false,
                        ],
                        'enable_retry' => true,
                        'max_retries' => 3,
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

    // ... existing code
    public function testConfigProviderWithMinimalConfiguration(): void
    {
        // Minimal config should now be nested under 'clients'
        $minimalConfig = [
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
        ];

        $dependencies = $this->configProvider->getDependencies();
        $minimalContainer = new ServiceManager(array_merge($dependencies, [
            'services' => ['config' => $minimalConfig]
        ]));

        $client = $minimalContainer->get(WeaviateClient::class);
        $this->assertInstanceOf(WeaviateClient::class, $client);

        $defaultClient = $minimalContainer->get('weaviate.client.default');
        $this->assertInstanceOf(WeaviateClient::class, $defaultClient);
    }

    public function testServiceManagerCaching(): void
    {
        $client1 = $this->container->get('weaviate.client.test_client');
        $client2 = $this->container->get('weaviate.client.test_client');

        $this->assertSame($client1, $client2, 'Service manager should return the same instance for the same client');
    }
}
