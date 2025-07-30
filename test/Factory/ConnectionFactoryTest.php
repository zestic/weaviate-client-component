<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Factory;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zestic\WeaviateClientComponent\Factory\ConnectionFactory;

class ConnectionFactoryTest extends TestCase
{
    private ConnectionFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ConnectionFactory();
    }

    public function testInvokeWithDefaultConfig(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [],
        ]);

        $result = ($this->factory)($container);

        $this->assertEquals('http://localhost:8080', $result['url']);
        $this->assertNull($result['host']);
        $this->assertEquals(8080, $result['port']);
        $this->assertFalse($result['secure']);
        $this->assertEquals(30, $result['timeout']);
        $this->assertEquals([], $result['headers']);
        $this->assertNull($result['cluster_url']);
        $this->assertFalse($result['is_cloud']);
        $this->assertTrue($result['is_local']);
    }

    public function testInvokeWithCustomConfig(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'connection' => [
                    'host' => 'example.com',
                    'port' => 9200,
                    'secure' => true,
                    'timeout' => 60,
                    'headers' => ['X-Custom' => 'value'],
                ],
            ],
        ]);

        $result = ($this->factory)($container);

        $this->assertEquals('https://example.com:9200', $result['url']);
        $this->assertEquals('example.com', $result['host']);
        $this->assertEquals(9200, $result['port']);
        $this->assertTrue($result['secure']);
        $this->assertEquals(60, $result['timeout']);
        $this->assertEquals(['X-Custom' => 'value'], $result['headers']);
        $this->assertNull($result['cluster_url']);
        $this->assertFalse($result['is_cloud']);
        $this->assertFalse($result['is_local']);
    }

    public function testInvokeWithClusterUrl(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'connection' => [
                    'cluster_url' => 'my-cluster.weaviate.network',
                    'secure' => true,
                ],
            ],
        ]);

        $result = ($this->factory)($container);

        $this->assertEquals('https://my-cluster.weaviate.network', $result['url']);
        $this->assertNull($result['host']);
        $this->assertEquals(8080, $result['port']);
        $this->assertTrue($result['secure']);
        $this->assertEquals('my-cluster.weaviate.network', $result['cluster_url']);
        $this->assertTrue($result['is_cloud']);
        $this->assertFalse($result['is_local']);
    }

    public function testCreateConnection(): void
    {
        $connectionConfig = [
            'host' => 'localhost',
            'port' => 8080,
            'secure' => false,
        ];

        $result = $this->factory->createConnection($connectionConfig);

        $this->assertEquals('http://localhost:8080', $result['url']);
        $this->assertEquals('localhost', $result['host']);
        $this->assertEquals(8080, $result['port']);
        $this->assertFalse($result['secure']);
    }

    public function testCreateConnectionWithHostIncludingPort(): void
    {
        $connectionConfig = [
            'host' => 'localhost:9200',
            'secure' => true,
        ];

        $result = $this->factory->createConnection($connectionConfig);

        $this->assertEquals('https://localhost:9200', $result['url']);
        $this->assertEquals('localhost', $result['host']);
        $this->assertEquals(9200, $result['port']);
        $this->assertTrue($result['secure']);
    }

    public function testCreateConnectionForClientWithClientConfig(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'connection' => [
                    'host' => 'global-host',
                ],
                'clients' => [
                    'test-client' => [
                        'connection' => [
                            'host' => 'client-host',
                            'port' => 9200,
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->factory->createConnectionForClient($container, 'test-client');

        $this->assertEquals('http://client-host:9200', $result['url']);
        $this->assertEquals('client-host', $result['host']);
        $this->assertEquals(9200, $result['port']);
    }

    public function testCreateConnectionForClientWithGlobalConfig(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'connection' => [
                    'host' => 'global-host',
                    'port' => 8080,
                ],
                'clients' => [
                    'test-client' => [],
                ],
            ],
        ]);

        $result = $this->factory->createConnectionForClient($container, 'test-client');

        $this->assertEquals('http://global-host:8080', $result['url']);
        $this->assertEquals('global-host', $result['host']);
        $this->assertEquals(8080, $result['port']);
    }

    public function testCreateConnectionForClientWithDefaults(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'test-client' => [],
                ],
            ],
        ]);

        $result = $this->factory->createConnectionForClient($container, 'test-client');

        $this->assertEquals('http://localhost:8080', $result['url']);
        $this->assertNull($result['host']);
        $this->assertEquals(8080, $result['port']);
    }

    public function testCreateLocalConnection(): void
    {
        $result = $this->factory->createLocalConnection('localhost', 8080, false);

        $this->assertEquals('http://localhost:8080', $result['url']);
        $this->assertEquals('localhost', $result['host']);
        $this->assertEquals(8080, $result['port']);
        $this->assertFalse($result['secure']);
        $this->assertTrue($result['is_local']);
    }

    public function testCreateLocalConnectionWithDefaults(): void
    {
        $result = $this->factory->createLocalConnection();

        $this->assertEquals('http://localhost:8080', $result['url']);
        $this->assertEquals('localhost', $result['host']);
        $this->assertEquals(8080, $result['port']);
        $this->assertFalse($result['secure']);
    }

    public function testCreateCloudConnection(): void
    {
        $result = $this->factory->createCloudConnection('my-cluster.weaviate.network');

        $this->assertEquals('https://my-cluster.weaviate.network', $result['url']);
        $this->assertEquals('my-cluster.weaviate.network', $result['cluster_url']);
        $this->assertTrue($result['secure']);
        $this->assertTrue($result['is_cloud']);
    }

    public function testCreateCloudConnectionInsecure(): void
    {
        $result = $this->factory->createCloudConnection('my-cluster.weaviate.network', false);

        $this->assertEquals('http://my-cluster.weaviate.network', $result['url']);
        $this->assertFalse($result['secure']);
    }

    public function testCreateCustomConnection(): void
    {
        $result = $this->factory->createCustomConnection(
            'example.com',
            9200,
            true,
            60,
            ['X-Custom' => 'value']
        );

        $this->assertEquals('https://example.com:9200', $result['url']);
        $this->assertEquals('example.com', $result['host']);
        $this->assertEquals(9200, $result['port']);
        $this->assertTrue($result['secure']);
        $this->assertEquals(60, $result['timeout']);
        $this->assertEquals(['X-Custom' => 'value'], $result['headers']);
    }

    public function testCreateCustomConnectionWithDefaults(): void
    {
        $result = $this->factory->createCustomConnection('example.com');

        $this->assertEquals('http://example.com:8080', $result['url']);
        $this->assertEquals('example.com', $result['host']);
        $this->assertEquals(8080, $result['port']);
        $this->assertFalse($result['secure']);
        $this->assertEquals(30, $result['timeout']);
        $this->assertEquals([], $result['headers']);
    }

    public function testValidateConnectionValid(): void
    {
        $connectionConfig = [
            'host' => 'localhost',
            'port' => 8080,
        ];

        $this->assertTrue($this->factory->validateConnection($connectionConfig));
    }

    public function testValidateConnectionInvalid(): void
    {
        $connectionConfig = [
            'port' => 70000, // Invalid port
        ];

        $this->assertFalse($this->factory->validateConnection($connectionConfig));
    }

    public function testGetConnectionTimeout(): void
    {
        $connectionConfig = [
            'timeout' => 45,
        ];

        $timeout = $this->factory->getConnectionTimeout($connectionConfig);

        $this->assertEquals(45, $timeout);
    }

    public function testGetConnectionHeaders(): void
    {
        $connectionConfig = [
            'headers' => ['X-Custom' => 'value'],
        ];

        $headers = $this->factory->getConnectionHeaders($connectionConfig);

        $this->assertEquals(['X-Custom' => 'value'], $headers);
    }

    public function testIsSecureConnection(): void
    {
        $secureConfig = ['secure' => true];
        $insecureConfig = ['secure' => false];

        $this->assertTrue($this->factory->isSecureConnection($secureConfig));
        $this->assertFalse($this->factory->isSecureConnection($insecureConfig));
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
