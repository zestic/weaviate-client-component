<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Integration\Factory;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zestic\WeaviateClientComponent\Factory\ConnectionFactory;
use Zestic\WeaviateClientComponent\Factory\WeaviateClientFactory;

/**
 * Integration tests that verify factory-created configurations work with real Weaviate instances.
 * 
 * These tests require a running Weaviate instance (typically provided by CI with Docker).
 */
class WeaviateConnectionIntegrationTest extends TestCase
{
    private ConnectionFactory $connectionFactory;
    private WeaviateClientFactory $clientFactory;
    private string $weaviateUrl;

    protected function setUp(): void
    {
        $this->connectionFactory = new ConnectionFactory();
        $this->clientFactory = new WeaviateClientFactory($this->connectionFactory);
        
        // Get Weaviate URL from environment or use default
        $this->weaviateUrl = $_ENV['WEAVIATE_URL'] ?? 'http://localhost:8080';
        
        // Skip tests if Weaviate is not available
        if (!$this->isWeaviateAvailable()) {
            $this->markTestSkipped('Weaviate instance not available at ' . $this->weaviateUrl);
        }
    }

    public function testLocalConnectionFactoryWithRealWeaviate(): void
    {
        $connectionConfig = [
            'host' => 'localhost',
            'port' => 8080,
            'secure' => false,
            'timeout' => 30,
        ];

        $connection = $this->connectionFactory->createConnection($connectionConfig);

        // Verify connection configuration
        $this->assertEquals('http://localhost:8080', $connection['url']);
        $this->assertEquals('localhost', $connection['host']);
        $this->assertEquals(8080, $connection['port']);
        $this->assertFalse($connection['secure']);
        $this->assertEquals(30, $connection['timeout']);

        // Test actual HTTP connection
        $this->assertTrue($this->testHttpConnection($connection['url']));
    }

    public function testClientFactoryWithRealWeaviate(): void
    {
        $config = [
            'weaviate' => [
                'connection_method' => 'local',
                'connection' => [
                    'host' => 'localhost',
                    'port' => 8080,
                    'secure' => false,
                ],
                'enable_retry' => true,
                'max_retries' => 2,
            ],
        ];

        $container = $this->createContainer($config);
        $client = $this->clientFactory->createClient($container, 'default');

        // Verify client configuration
        $this->assertEquals('local', $client['type']);
        $this->assertEquals('http://localhost:8080', $client['connection']['url']);
        $this->assertTrue($client['enable_retry']);
        $this->assertEquals(2, $client['max_retries']);

        // Test connection to Weaviate
        $this->assertTrue($this->testHttpConnection($client['connection']['url']));
        
        // Test Weaviate-specific endpoints
        $this->assertTrue($this->testWeaviateEndpoints($client['connection']['url']));
    }

    public function testMultipleEnvironmentClients(): void
    {
        $config = [
            'weaviate' => [
                'clients' => [
                    'local-test' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => 'localhost',
                            'port' => 8080,
                        ],
                    ],
                    'local-alt-port' => [
                        'connection_method' => 'custom',
                        'connection' => [
                            'host' => 'localhost',
                            'port' => 8080, // Same port for testing
                        ],
                    ],
                ],
            ],
        ];

        $container = $this->createContainer($config);

        // Test both clients can be created and connect
        $client1 = $this->clientFactory->createClient($container, 'local-test');
        $client2 = $this->clientFactory->createClient($container, 'local-alt-port');

        $this->assertEquals('local', $client1['type']);
        $this->assertEquals('custom', $client2['type']);

        // Both should connect to the same Weaviate instance
        $this->assertTrue($this->testHttpConnection($client1['connection']['url']));
        $this->assertTrue($this->testHttpConnection($client2['connection']['url']));
    }

    public function testConnectionValidation(): void
    {
        // Test valid connection
        $validConfig = [
            'host' => 'localhost',
            'port' => 8080,
        ];
        $this->assertTrue($this->connectionFactory->validateConnection($validConfig));

        // Test invalid port
        $invalidConfig = [
            'host' => 'localhost',
            'port' => 99999, // Invalid port
        ];
        $this->assertFalse($this->connectionFactory->validateConnection($invalidConfig));
    }

    public function testConnectionTimeout(): void
    {
        $connectionConfig = [
            'host' => 'localhost',
            'port' => 8080,
            'timeout' => 5, // Short timeout for testing
        ];

        $timeout = $this->connectionFactory->getConnectionTimeout($connectionConfig);
        $this->assertEquals(5, $timeout);

        // Test that connection respects timeout (this is more of a configuration test)
        $connection = $this->connectionFactory->createConnection($connectionConfig);
        $this->assertEquals(5, $connection['timeout']);
    }

    public function testConnectionHeaders(): void
    {
        $connectionConfig = [
            'host' => 'localhost',
            'port' => 8080,
            'headers' => [
                'X-Test-Header' => 'test-value',
                'User-Agent' => 'WeaviateClientComponent/1.0',
            ],
        ];

        $headers = $this->connectionFactory->getConnectionHeaders($connectionConfig);
        $expectedHeaders = [
            'X-Test-Header' => 'test-value',
            'User-Agent' => 'WeaviateClientComponent/1.0',
        ];

        $this->assertEquals($expectedHeaders, $headers);

        // Verify headers are included in connection
        $connection = $this->connectionFactory->createConnection($connectionConfig);
        $this->assertEquals($expectedHeaders, $connection['headers']);
    }

    public function testSecureConnectionDetection(): void
    {
        $secureConfig = ['secure' => true];
        $insecureConfig = ['secure' => false];

        $this->assertTrue($this->connectionFactory->isSecureConnection($secureConfig));
        $this->assertFalse($this->connectionFactory->isSecureConnection($insecureConfig));
    }

    /**
     * Check if Weaviate is available for testing.
     */
    private function isWeaviateAvailable(): bool
    {
        return $this->testHttpConnection($this->weaviateUrl);
    }

    /**
     * Test basic HTTP connection to a URL.
     */
    private function testHttpConnection(string $url): bool
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET',
            ],
        ]);

        $result = @file_get_contents($url . '/v1/.well-known/ready', false, $context);
        
        return $result !== false;
    }

    /**
     * Test Weaviate-specific endpoints.
     */
    private function testWeaviateEndpoints(string $baseUrl): bool
    {
        $endpoints = [
            '/v1/.well-known/ready',
            '/v1/.well-known/live',
            '/v1/meta',
        ];

        foreach ($endpoints as $endpoint) {
            if (!$this->testHttpConnection($baseUrl . $endpoint)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create a mock container with configuration.
     */
    private function createContainer(array $config): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with('config')
            ->willReturn($config);

        return $container;
    }
}
