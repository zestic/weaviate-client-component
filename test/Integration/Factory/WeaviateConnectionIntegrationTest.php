<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Integration\Factory;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Weaviate\WeaviateClient;
use Zestic\WeaviateClientComponent\Factory\AuthFactory;
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
    private AuthFactory $authFactory;
    private WeaviateClientFactory $clientFactory;
    private string $weaviateUrl;

    protected function setUp(): void
    {
        $this->connectionFactory = new ConnectionFactory();
        $this->authFactory = new AuthFactory();
        $this->clientFactory = new WeaviateClientFactory($this->connectionFactory, $this->authFactory);

        // Get Weaviate URL from environment or use Docker default
        $this->weaviateUrl = $_ENV['WEAVIATE_URL'] ?? 'http://localhost:18080';

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
        // Use the Docker port for testing
        $port = parse_url($this->weaviateUrl)['port'] ?? 8080;

        $config = [
            'weaviate' => [
                'connection_method' => 'local',
                'connection' => [
                    'host' => 'localhost',
                    'port' => $port,
                    'secure' => false,
                ],
                'enable_retry' => true,
                'max_retries' => 2,
            ],
        ];

        $container = $this->createContainer($config);
        $client = $this->clientFactory->createClient($container, 'default');

        // Verify we get a proper WeaviateClient object
        $this->assertInstanceOf(WeaviateClient::class, $client);

        // Test that the client has the expected API methods
        $this->assertTrue(method_exists($client, 'collections'));
        $this->assertTrue(method_exists($client, 'schema'));

        // Most importantly: Test that the client can actually connect to Weaviate
        try {
            $schema = $client->schema();
            $schemaResult = $schema->get();
            $this->assertIsArray($schemaResult);

            // If we get here, the connection is working
            $this->assertTrue(true, 'Successfully connected to Weaviate and retrieved schema');
        } catch (\Exception $e) {
            $this->fail('Failed to connect to Weaviate: ' . $e->getMessage());
        }

        // Test basic HTTP connection to the same URL
        $this->assertTrue($this->testHttpConnection($this->weaviateUrl));
    }

    public function testMultipleEnvironmentClients(): void
    {
        // Use the Docker port for testing
        $port = parse_url($this->weaviateUrl)['port'] ?? 8080;

        $config = [
            'weaviate' => [
                'clients' => [
                    'local-test' => [
                        'connection_method' => 'local',
                        'connection' => [
                            'host' => 'localhost',
                            'port' => $port,
                        ],
                    ],
                    'local-alt-port' => [
                        'connection_method' => 'custom',
                        'connection' => [
                            'host' => 'localhost',
                            'port' => $port, // Same port for testing
                        ],
                    ],
                ],
            ],
        ];

        $container = $this->createContainer($config);

        // Test both clients can be created and connect
        $client1 = $this->clientFactory->createClient($container, 'local-test');
        $client2 = $this->clientFactory->createClient($container, 'local-alt-port');

        // Verify both are proper WeaviateClient objects
        $this->assertInstanceOf(WeaviateClient::class, $client1);
        $this->assertInstanceOf(WeaviateClient::class, $client2);

        // Test that both clients can actually connect to Weaviate
        try {
            // Test client1
            $schema1 = $client1->schema();
            $result1 = $schema1->get();
            $this->assertIsArray($result1);

            // Test client2
            $schema2 = $client2->schema();
            $result2 = $schema2->get();
            $this->assertIsArray($result2);

            $this->assertTrue(true, 'Both clients successfully connected to Weaviate');
        } catch (\Exception $e) {
            $this->fail('Failed to connect to Weaviate with one or both clients: ' . $e->getMessage());
        }

        // Both should connect to the same Weaviate instance via HTTP
        $this->assertTrue($this->testHttpConnection($this->weaviateUrl));
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
        return $this->testWeaviateEndpoints($this->weaviateUrl);
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

        // If the URL already contains a path (like /v1/meta), use it as-is
        // Otherwise, append the ready endpoint
        $testUrl = (strpos($url, '/v1/') !== false && strpos($url, '/v1/.well-known/ready') === false)
            ? $url
            : $url . '/v1/.well-known/ready';

        $result = @file_get_contents($testUrl, false, $context);

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
