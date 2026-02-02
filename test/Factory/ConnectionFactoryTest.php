<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Factory;

use PHPUnit\Framework\TestCase;
use Zestic\WeaviateClientComponent\Factory\ConnectionFactory;

/**
 * @covers \Zestic\WeaviateClientComponent\Factory\ConnectionFactory
 */
class ConnectionFactoryTest extends TestCase
{
    private ConnectionFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ConnectionFactory();
    }

    public function testCreateConnectionForLocal(): void
    {
        $config = [
            'host' => 'localhost',
            'port' => 8080,
        ];
        $connection = $this->factory->createConnection($config);
        $this->assertIsArray($connection);
        $this->assertEquals('http://localhost:8080', $connection['url']);
    }

    public function testCreateConnectionForCloud(): void
    {
        $config = [
            'cluster_url' => 'https://my-cluster.weaviate.network',
        ];
        $connection = $this->factory->createConnection($config);
        $this->assertIsArray($connection);
        $this->assertEquals('https://my-cluster.weaviate.network', $connection['cluster_url']);
    }

    public function testCreateConnectionForCustom(): void
    {
        $config = [
            'host' => 'custom.host',
            'port' => 9090,
            'secure' => true,
        ];
        $connection = $this->factory->createConnection($config);
        $this->assertIsArray($connection);
        $this->assertEquals('custom.host', $connection['host']);
        $this->assertEquals(9090, $connection['port']);
        $this->assertTrue($connection['secure']);
    }

    public function testCreateConnectionWithDefaults(): void
    {
        $config = [];
        $connection = $this->factory->createConnection($config);
        $this->assertEquals('http://localhost:8080', $connection['url']);
    }
}
