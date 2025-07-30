<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Configuration;

use PHPUnit\Framework\TestCase;
use Zestic\WeaviateClientComponent\Configuration\WeaviateConfig;
use Zestic\WeaviateClientComponent\Configuration\ConnectionConfig;
use Zestic\WeaviateClientComponent\Configuration\AuthConfig;
use Zestic\WeaviateClientComponent\Exception\ConfigurationException;

class WeaviateConfigTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $config = new WeaviateConfig();

        $this->assertEquals(WeaviateConfig::CONNECTION_METHOD_LOCAL, $config->connectionMethod);
        $this->assertInstanceOf(ConnectionConfig::class, $config->connection);
        $this->assertNull($config->auth);
        $this->assertEquals([], $config->additionalHeaders);
        $this->assertTrue($config->enableRetry);
        $this->assertEquals(4, $config->maxRetries);
    }

    public function testConstructorWithAllParameters(): void
    {
        $connection = new ConnectionConfig(clusterUrl: 'my-cluster.weaviate.network');
        $auth = new AuthConfig(apiKey: 'test-key');

        $config = new WeaviateConfig(
            connectionMethod: WeaviateConfig::CONNECTION_METHOD_CLOUD,
            connection: $connection,
            auth: $auth,
            additionalHeaders: ['X-Custom' => 'value'],
            enableRetry: false,
            maxRetries: 2
        );

        $this->assertEquals(WeaviateConfig::CONNECTION_METHOD_CLOUD, $config->connectionMethod);
        $this->assertSame($connection, $config->connection);
        $this->assertSame($auth, $config->auth);
        $this->assertEquals(['X-Custom' => 'value'], $config->additionalHeaders);
        $this->assertFalse($config->enableRetry);
        $this->assertEquals(2, $config->maxRetries);
    }

    public function testFromArrayWithDefaults(): void
    {
        $config = WeaviateConfig::fromArray([]);

        $this->assertEquals(WeaviateConfig::CONNECTION_METHOD_LOCAL, $config->connectionMethod);
        $this->assertInstanceOf(ConnectionConfig::class, $config->connection);
        $this->assertNull($config->auth);
        $this->assertEquals([], $config->additionalHeaders);
        $this->assertTrue($config->enableRetry);
        $this->assertEquals(4, $config->maxRetries);
    }

    public function testFromArrayLocalConnection(): void
    {
        $data = [
            'connection_method' => 'local',
            'connection' => [
                'host' => 'localhost:8080',
            ],
            'enable_retry' => true,
            'max_retries' => 3,
        ];

        $config = WeaviateConfig::fromArray($data);

        $this->assertEquals(WeaviateConfig::CONNECTION_METHOD_LOCAL, $config->connectionMethod);
        $this->assertEquals('localhost:8080', $config->connection->host);
        $this->assertTrue($config->enableRetry);
        $this->assertEquals(3, $config->maxRetries);
        $this->assertNull($config->auth);
    }

    public function testFromArrayCloudConnection(): void
    {
        $data = [
            'connection_method' => 'cloud',
            'connection' => [
                'cluster_url' => 'my-cluster.weaviate.network',
            ],
            'auth' => [
                'type' => 'api_key',
                'api_key' => 'test-key',
            ],
            'additional_headers' => [
                'X-OpenAI-Api-Key' => 'openai-key',
            ],
        ];

        $config = WeaviateConfig::fromArray($data);

        $this->assertEquals(WeaviateConfig::CONNECTION_METHOD_CLOUD, $config->connectionMethod);
        $this->assertEquals('my-cluster.weaviate.network', $config->connection->clusterUrl);
        $this->assertNotNull($config->auth);
        $this->assertEquals('test-key', $config->auth->apiKey);
        $this->assertEquals(['X-OpenAI-Api-Key' => 'openai-key'], $config->additionalHeaders);
    }

    public function testFromArrayCustomConnection(): void
    {
        $data = [
            'connection_method' => 'custom',
            'connection' => [
                'host' => 'example.com',
                'port' => 9200,
                'secure' => true,
            ],
            'auth' => [
                'type' => 'bearer_token',
                'bearer_token' => 'bearer-token',
            ],
        ];

        $config = WeaviateConfig::fromArray($data);

        $this->assertEquals(WeaviateConfig::CONNECTION_METHOD_CUSTOM, $config->connectionMethod);
        $this->assertEquals('example.com', $config->connection->host);
        $this->assertEquals(9200, $config->connection->port);
        $this->assertTrue($config->connection->secure);
        $this->assertNotNull($config->auth);
        $this->assertEquals('bearer-token', $config->auth->bearerToken);
    }

    public function testToArray(): void
    {
        $connection = new ConnectionConfig(host: 'localhost', port: 8080);
        $auth = new AuthConfig(apiKey: 'test-key');

        $config = new WeaviateConfig(
            connectionMethod: WeaviateConfig::CONNECTION_METHOD_LOCAL,
            connection: $connection,
            auth: $auth,
            additionalHeaders: ['X-Custom' => 'value'],
            enableRetry: false,
            maxRetries: 2
        );

        $result = $config->toArray();

        $this->assertEquals(WeaviateConfig::CONNECTION_METHOD_LOCAL, $result['connection_method']);
        $this->assertEquals($connection->toArray(), $result['connection']);
        $this->assertEquals($auth->toArray(), $result['auth']);
        $this->assertEquals(['X-Custom' => 'value'], $result['additional_headers']);
        $this->assertFalse($result['enable_retry']);
        $this->assertEquals(2, $result['max_retries']);
    }

    public function testToArrayWithoutAuth(): void
    {
        $config = new WeaviateConfig();
        $result = $config->toArray();

        $this->assertArrayNotHasKey('auth', $result);
    }

    public function testConnectionMethodCheckers(): void
    {
        $localConfig = new WeaviateConfig(connectionMethod: WeaviateConfig::CONNECTION_METHOD_LOCAL);
        $this->assertTrue($localConfig->isLocalConnection());
        $this->assertFalse($localConfig->isCloudConnection());
        $this->assertFalse($localConfig->isCustomConnection());

        $cloudConfig = new WeaviateConfig(
            connectionMethod: WeaviateConfig::CONNECTION_METHOD_CLOUD,
            connection: new ConnectionConfig(clusterUrl: 'my-cluster.weaviate.network'),
            auth: new AuthConfig(apiKey: 'test-key')
        );
        $this->assertFalse($cloudConfig->isLocalConnection());
        $this->assertTrue($cloudConfig->isCloudConnection());
        $this->assertFalse($cloudConfig->isCustomConnection());

        $customConfig = new WeaviateConfig(
            connectionMethod: WeaviateConfig::CONNECTION_METHOD_CUSTOM,
            connection: new ConnectionConfig(host: 'custom-server.com')
        );
        $this->assertFalse($customConfig->isLocalConnection());
        $this->assertFalse($customConfig->isCloudConnection());
        $this->assertTrue($customConfig->isCustomConnection());
    }

    public function testHasAuth(): void
    {
        $configWithoutAuth = new WeaviateConfig();
        $this->assertFalse($configWithoutAuth->hasAuth());

        $auth = new AuthConfig(apiKey: 'test-key');
        $configWithAuth = new WeaviateConfig(auth: $auth);
        $this->assertTrue($configWithAuth->hasAuth());
    }

    public function testGetAllHeadersWithoutAuth(): void
    {
        $config = new WeaviateConfig(
            additionalHeaders: ['X-Custom' => 'value', 'X-Another' => 'header']
        );

        $headers = $config->getAllHeaders();

        $this->assertEquals(['X-Custom' => 'value', 'X-Another' => 'header'], $headers);
    }

    public function testGetAllHeadersWithApiKeyAuth(): void
    {
        $auth = new AuthConfig(apiKey: 'test-api-key');
        $config = new WeaviateConfig(
            auth: $auth,
            additionalHeaders: ['X-Custom' => 'value']
        );

        $headers = $config->getAllHeaders();

        $expected = [
            'X-Custom' => 'value',
            'Authorization' => 'Bearer test-api-key',
        ];

        $this->assertEquals($expected, $headers);
    }

    public function testGetAllHeadersWithBearerTokenAuth(): void
    {
        $auth = new AuthConfig(type: AuthConfig::TYPE_BEARER_TOKEN, bearerToken: 'test-bearer-token');
        $config = new WeaviateConfig(
            auth: $auth,
            additionalHeaders: ['X-Custom' => 'value']
        );

        $headers = $config->getAllHeaders();

        $expected = [
            'X-Custom' => 'value',
            'Authorization' => 'Bearer test-bearer-token',
        ];

        $this->assertEquals($expected, $headers);
    }

    public function testGetAllHeadersWithOidcAuth(): void
    {
        $auth = new AuthConfig(
            type: AuthConfig::TYPE_OIDC,
            clientId: 'client-123',
            clientSecret: 'secret-456'
        );
        $config = new WeaviateConfig(
            auth: $auth,
            additionalHeaders: ['X-Custom' => 'value']
        );

        $headers = $config->getAllHeaders();

        // OIDC auth doesn't add Authorization header automatically
        $this->assertEquals(['X-Custom' => 'value'], $headers);
    }

    public function testInvalidConnectionMethod(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            "Invalid connection method: 'invalid'. Must be one of: 'local', 'cloud', 'custom'"
        );

        new WeaviateConfig(connectionMethod: 'invalid');
    }

    public function testInvalidRetryConfigNegative(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid retry configuration: max_retries must be >= 0');

        new WeaviateConfig(maxRetries: -1);
    }

    public function testInvalidRetryConfigTooHigh(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid retry configuration: max_retries should not exceed 10');

        new WeaviateConfig(maxRetries: 11);
    }

    public function testValidRetryConfigBoundaries(): void
    {
        $config = new WeaviateConfig(maxRetries: 0);
        $this->assertEquals(0, $config->maxRetries);

        $config = new WeaviateConfig(maxRetries: 10);
        $this->assertEquals(10, $config->maxRetries);
    }

    public function testCloudConnectionValidationMissingClusterUrl(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'cluster_url' in cloud connection");

        new WeaviateConfig(
            connectionMethod: WeaviateConfig::CONNECTION_METHOD_CLOUD,
            connection: new ConnectionConfig(),
            auth: new AuthConfig(apiKey: 'test-key')
        );
    }

    public function testCloudConnectionValidationEmptyClusterUrl(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'cluster_url' in cloud connection");

        new WeaviateConfig(
            connectionMethod: WeaviateConfig::CONNECTION_METHOD_CLOUD,
            connection: new ConnectionConfig(clusterUrl: '   '),
            auth: new AuthConfig(apiKey: 'test-key')
        );
    }

    public function testCloudConnectionValidationMissingAuth(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'auth' in cloud connection");

        new WeaviateConfig(
            connectionMethod: WeaviateConfig::CONNECTION_METHOD_CLOUD,
            connection: new ConnectionConfig(clusterUrl: 'my-cluster.weaviate.network')
        );
    }

    public function testCustomConnectionValidationMissingHost(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'host' in custom connection");

        new WeaviateConfig(
            connectionMethod: WeaviateConfig::CONNECTION_METHOD_CUSTOM,
            connection: new ConnectionConfig()
        );
    }

    public function testCustomConnectionValidationEmptyHost(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'host' in custom connection");

        new WeaviateConfig(
            connectionMethod: WeaviateConfig::CONNECTION_METHOD_CUSTOM,
            connection: new ConnectionConfig(host: '  ')
        );
    }

    public function testLocalConnectionValidation(): void
    {
        // Local connections should work without host (defaults are acceptable)
        $config = new WeaviateConfig(
            connectionMethod: WeaviateConfig::CONNECTION_METHOD_LOCAL,
            connection: new ConnectionConfig()
        );

        $this->assertEquals(WeaviateConfig::CONNECTION_METHOD_LOCAL, $config->connectionMethod);
        $this->assertTrue($config->isLocalConnection());
    }

    public function testValidCloudConnection(): void
    {
        $config = new WeaviateConfig(
            connectionMethod: WeaviateConfig::CONNECTION_METHOD_CLOUD,
            connection: new ConnectionConfig(clusterUrl: 'my-cluster.weaviate.network'),
            auth: new AuthConfig(apiKey: 'test-key')
        );

        $this->assertEquals(WeaviateConfig::CONNECTION_METHOD_CLOUD, $config->connectionMethod);
        $this->assertTrue($config->isCloudConnection());
        $this->assertTrue($config->hasAuth());
    }

    public function testValidCustomConnection(): void
    {
        $config = new WeaviateConfig(
            connectionMethod: WeaviateConfig::CONNECTION_METHOD_CUSTOM,
            connection: new ConnectionConfig(host: 'example.com', port: 9200, secure: true)
        );

        $this->assertEquals(WeaviateConfig::CONNECTION_METHOD_CUSTOM, $config->connectionMethod);
        $this->assertTrue($config->isCustomConnection());
        $this->assertEquals('example.com', $config->connection->host);
        $this->assertEquals(9200, $config->connection->port);
        $this->assertTrue($config->connection->secure);
    }

    public function testFromArrayValidationErrors(): void
    {
        // Test cloud connection validation through fromArray
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'cluster_url' in cloud connection");

        WeaviateConfig::fromArray([
            'connection_method' => 'cloud',
            'auth' => ['type' => 'api_key', 'api_key' => 'test-key'],
        ]);
    }
}
