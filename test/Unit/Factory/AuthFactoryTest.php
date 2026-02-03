<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Unit\Factory;

use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Weaviate\Auth\ApiKey;
use Weaviate\Auth\AuthInterface;
use Zestic\WeaviateClientComponent\Factory\AuthFactory;

class AuthFactoryTest extends TestCase
{
    private AuthFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new AuthFactory();
    }

    public function testInvokeReturnsNullWhenGlobalAuthIsMissing(): void
    {
        $container = $this->createContainer(['weaviate' => []]);
        $result = ($this->factory)($container);
        $this->assertNull($result);
    }

    public function testInvokeReturnsAuthWhenGlobalAuthIsPresent(): void
    {
        $config = [
            'weaviate' => [
                'auth' => [
                    'type' => 'api_key',
                    'api_key' => 'test-secret-key',
                ],
            ],
        ];
        $container = $this->createContainer($config);
        $result = ($this->factory)($container);

        $this->assertInstanceOf(AuthInterface::class, $result);
        $this->assertInstanceOf(ApiKey::class, $result);
    }

    private function createContainer(array $config): ContainerInterface
    {
        $container = new ServiceManager();
        $container->setService('config', $config);

        return $container;
    }
}
