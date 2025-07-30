<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Configuration;

use PHPUnit\Framework\TestCase;
use Zestic\WeaviateClientComponent\Configuration\ConnectionConfig;
use Zestic\WeaviateClientComponent\Exception\ConfigurationException;

class ConnectionConfigTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $config = new ConnectionConfig();

        $this->assertNull($config->host);
        $this->assertEquals(8080, $config->port);
        $this->assertFalse($config->secure);
        $this->assertNull($config->clusterUrl);
        $this->assertEquals(30, $config->timeout);
        $this->assertEquals([], $config->headers);
    }

    public function testConstructorWithAllParameters(): void
    {
        $config = new ConnectionConfig(
            host: 'localhost',
            port: 9200,
            secure: true,
            clusterUrl: 'my-cluster.weaviate.network',
            timeout: 60,
            headers: ['X-Custom' => 'value']
        );

        $this->assertEquals('localhost', $config->host);
        $this->assertEquals(9200, $config->port);
        $this->assertTrue($config->secure);
        $this->assertEquals('my-cluster.weaviate.network', $config->clusterUrl);
        $this->assertEquals(60, $config->timeout);
        $this->assertEquals(['X-Custom' => 'value'], $config->headers);
    }

    public function testFromArrayWithDefaults(): void
    {
        $config = ConnectionConfig::fromArray([]);

        $this->assertNull($config->host);
        $this->assertEquals(8080, $config->port);
        $this->assertFalse($config->secure);
        $this->assertNull($config->clusterUrl);
        $this->assertEquals(30, $config->timeout);
        $this->assertEquals([], $config->headers);
    }

    public function testFromArrayWithAllValues(): void
    {
        $data = [
            'host' => 'example.com',
            'port' => 9200,
            'secure' => true,
            'cluster_url' => 'my-cluster.weaviate.network',
            'timeout' => 45,
            'headers' => ['Authorization' => 'Bearer token'],
        ];

        $config = ConnectionConfig::fromArray($data);

        $this->assertEquals('example.com', $config->host);
        $this->assertEquals(9200, $config->port);
        $this->assertTrue($config->secure);
        $this->assertEquals('my-cluster.weaviate.network', $config->clusterUrl);
        $this->assertEquals(45, $config->timeout);
        $this->assertEquals(['Authorization' => 'Bearer token'], $config->headers);
    }

    public function testToArray(): void
    {
        $config = new ConnectionConfig(
            host: 'localhost',
            port: 9200,
            secure: true,
            clusterUrl: 'my-cluster.weaviate.network',
            timeout: 60,
            headers: ['X-Custom' => 'value']
        );

        $expected = [
            'host' => 'localhost',
            'port' => 9200,
            'secure' => true,
            'cluster_url' => 'my-cluster.weaviate.network',
            'timeout' => 60,
            'headers' => ['X-Custom' => 'value'],
        ];

        $this->assertEquals($expected, $config->toArray());
    }

    public function testToArrayWithNullValues(): void
    {
        $config = new ConnectionConfig(
            host: null,
            port: 8080,
            secure: false,
            clusterUrl: null,
            timeout: 30,
            headers: []
        );

        $expected = [
            'port' => 8080,
            'secure' => false,
            'timeout' => 30,
            'headers' => [],
        ];

        $this->assertEquals($expected, $config->toArray());
    }

    public function testGetUrlWithClusterUrl(): void
    {
        $config = new ConnectionConfig(
            clusterUrl: 'my-cluster.weaviate.network',
            secure: true
        );

        $this->assertEquals('https://my-cluster.weaviate.network', $config->getUrl());
    }

    public function testGetUrlWithClusterUrlInsecure(): void
    {
        $config = new ConnectionConfig(
            clusterUrl: 'my-cluster.weaviate.network',
            secure: false
        );

        $this->assertEquals('http://my-cluster.weaviate.network', $config->getUrl());
    }

    public function testGetUrlWithHost(): void
    {
        $config = new ConnectionConfig(
            host: 'localhost',
            port: 8080,
            secure: false
        );

        $this->assertEquals('http://localhost:8080', $config->getUrl());
    }

    public function testGetUrlWithHostAndPort(): void
    {
        $config = new ConnectionConfig(
            host: 'example.com',
            port: 9200,
            secure: true
        );

        $this->assertEquals('https://example.com:9200', $config->getUrl());
    }

    public function testGetUrlWithHostIncludingPort(): void
    {
        $config = new ConnectionConfig(
            host: 'localhost:9200',
            secure: false
        );

        $this->assertEquals('http://localhost:9200', $config->getUrl());
    }

    public function testGetUrlMissingHost(): void
    {
        $config = new ConnectionConfig();

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'host' in connection configuration");

        $config->getUrl();
    }

    public function testGetHostOnly(): void
    {
        $config = new ConnectionConfig(host: 'localhost');
        $this->assertEquals('localhost', $config->getHostOnly());

        $config = new ConnectionConfig(host: 'localhost:9200');
        $this->assertEquals('localhost', $config->getHostOnly());

        $config = new ConnectionConfig(host: null);
        $this->assertNull($config->getHostOnly());
    }

    public function testGetEffectivePort(): void
    {
        $config = new ConnectionConfig(host: 'localhost', port: 8080);
        $this->assertEquals(8080, $config->getEffectivePort());

        $config = new ConnectionConfig(host: 'localhost:9200', port: 8080);
        $this->assertEquals(9200, $config->getEffectivePort());

        $config = new ConnectionConfig(host: 'localhost:invalid', port: 8080);
        $this->assertEquals(8080, $config->getEffectivePort());
    }

    public function testIsCloudConnection(): void
    {
        $config = new ConnectionConfig(clusterUrl: 'my-cluster.weaviate.network');
        $this->assertTrue($config->isCloudConnection());

        $config = new ConnectionConfig(host: 'localhost');
        $this->assertFalse($config->isCloudConnection());
    }

    public function testIsLocalConnection(): void
    {
        $config = new ConnectionConfig(host: 'localhost');
        $this->assertTrue($config->isLocalConnection());

        $config = new ConnectionConfig(host: '127.0.0.1');
        $this->assertTrue($config->isLocalConnection());

        $config = new ConnectionConfig(host: '::1');
        $this->assertTrue($config->isLocalConnection());

        $config = new ConnectionConfig(host: 'example.com');
        $this->assertFalse($config->isLocalConnection());

        $config = new ConnectionConfig(clusterUrl: 'my-cluster.weaviate.network');
        $this->assertFalse($config->isLocalConnection());
    }

    public function testInvalidPortTooLow(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid port number: 0. Port must be between 1 and 65535');

        new ConnectionConfig(port: 0);
    }

    public function testInvalidPortTooHigh(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Invalid port number: 65536. Port must be between 1 and 65535');

        new ConnectionConfig(port: 65536);
    }

    public function testValidPortBoundaries(): void
    {
        $config = new ConnectionConfig(port: 1);
        $this->assertEquals(1, $config->port);

        $config = new ConnectionConfig(port: 65535);
        $this->assertEquals(65535, $config->port);
    }

    public function testInvalidTimeoutZero(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Timeout must be greater than 0');

        new ConnectionConfig(timeout: 0);
    }

    public function testInvalidTimeoutNegative(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Timeout must be greater than 0');

        new ConnectionConfig(timeout: -1);
    }

    public function testValidTimeout(): void
    {
        $config = new ConnectionConfig(timeout: 1);
        $this->assertEquals(1, $config->timeout);

        $config = new ConnectionConfig(timeout: 300);
        $this->assertEquals(300, $config->timeout);
    }
}
