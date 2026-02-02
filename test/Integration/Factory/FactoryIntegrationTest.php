<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Integration\Factory;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Weaviate\WeaviateClient;
use Zestic\WeaviateClientComponent\Factory\WeaviateClientAbstractFactory;
use Laminas\ServiceManager\ServiceManager;

class FactoryIntegrationTest extends TestCase
{
    private WeaviateClientAbstractFactory $abstractFactory;
    private string $weaviateUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->abstractFactory = new WeaviateClientAbstractFactory();
        $this->weaviateUrl = getenv('WEAVIATE_URL') ?: 'http://localhost:8080';
    }

    public function testAbstractFactoryIntegration(): void
    {
        if (!$this->isWeaviateAvailable()) {
            $this->markTestSkipped('Weaviate is not available for integration testing');
        }

        $parsedUrl = parse_url($this->weaviateUrl);
        $port = $parsedUrl['port'] ?? 8080;
        $host = $parsedUrl['host'] ?? 'localhost';

        $config = [
            'weaviate' => [
                'clients' => [
                    'test-client' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => $host,
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

        // Test that the client is functional
        $schema = $client->schema()->get();
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('classes', $schema);

        // Test service names
        $serviceNames = $this->abstractFactory->getCreatableServiceNames($container);
        $this->assertEquals(['weaviate.client.test-client'], $serviceNames);
    }

    public function testUtilityMethods(): void
    {
        $this->assertEquals(
            'weaviate.client.test-client',
            WeaviateClientAbstractFactory::getServiceName('test-client')
        );
        $this->assertTrue(WeaviateClientAbstractFactory::isClientServiceName('weaviate.client.test-client'));
        $this->assertEquals(
            'test-client',
            WeaviateClientAbstractFactory::extractClientNameFromServiceName('weaviate.client.test-client')
        );
    }

    private function createContainer(array $config): ContainerInterface
    {
        $container = new ServiceManager([
            'services' => [
                'config' => $config,
            ],
            'factories' => [
                WeaviateClientAbstractFactory::class => fn () => $this->abstractFactory,
            ],
            'abstract_factories' => [
                WeaviateClientAbstractFactory::class,
            ],
        ]);

        return $container;
    }

    /**
     * Check if Weaviate is available for testing.
     */
    private function isWeaviateAvailable(): bool
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'method' => 'GET',
            ],
        ]);

        $result = @file_get_contents($this->weaviateUrl . '/v1/.well-known/ready', false, $context);
        return $result !== false;
    }
}
