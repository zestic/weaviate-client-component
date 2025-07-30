<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Factory;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Weaviate\WeaviateClient;
use Zestic\WeaviateClientComponent\Exception\ConfigurationException;
use Zestic\WeaviateClientComponent\Factory\WeaviateClientAbstractFactory;
use Zestic\WeaviateClientComponent\Factory\WeaviateClientFactory;

class WeaviateClientAbstractFactoryTest extends TestCase
{
    private WeaviateClientAbstractFactory $factory;
    private WeaviateClientFactory&MockObject $clientFactory;

    protected function setUp(): void
    {
        $this->clientFactory = $this->createMock(WeaviateClientFactory::class);
        $this->factory = new WeaviateClientAbstractFactory($this->clientFactory);
    }

    public function testCanCreateWithValidClientService(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $this->clientFactory
            ->expects($this->once())
            ->method('hasClient')
            ->with($container, 'test-client')
            ->willReturn(true);

        $result = $this->factory->canCreate($container, 'weaviate.client.test-client');

        $this->assertTrue($result);
    }

    public function testCanCreateWithInvalidPrefix(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $result = $this->factory->canCreate($container, 'invalid.service.name');

        $this->assertFalse($result);
    }

    public function testCanCreateWithNonexistentClient(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $this->clientFactory
            ->expects($this->once())
            ->method('hasClient')
            ->with($container, 'nonexistent')
            ->willReturn(false);

        $result = $this->factory->canCreate($container, 'weaviate.client.nonexistent');

        $this->assertFalse($result);
    }

    public function testInvokeWithValidClient(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $expectedClient = $this->createMock(WeaviateClient::class);

        $this->clientFactory
            ->expects($this->once())
            ->method('hasClient')
            ->with($container, 'test-client')
            ->willReturn(true);

        $this->clientFactory
            ->expects($this->once())
            ->method('createClient')
            ->with($container, 'test-client')
            ->willReturn($expectedClient);

        $result = $this->factory->__invoke($container, 'weaviate.client.test-client');

        $this->assertSame($expectedClient, $result);
    }

    public function testInvokeWithInvalidClient(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $this->clientFactory
            ->expects($this->once())
            ->method('hasClient')
            ->with($container, 'invalid')
            ->willReturn(false);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Weaviate client 'weaviate.client.invalid' not configured");

        $this->factory->__invoke($container, 'weaviate.client.invalid');
    }

    public function testGetCreatableServiceNames(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $this->clientFactory
            ->expects($this->once())
            ->method('getConfiguredClientNames')
            ->with($container)
            ->willReturn(['client1', 'client2', 'default']);

        $result = $this->factory->getCreatableServiceNames($container);

        $expected = [
            'weaviate.client.client1',
            'weaviate.client.client2',
            'weaviate.client.default',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testCanCreateClientService(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $this->clientFactory
            ->expects($this->once())
            ->method('hasClient')
            ->with($container, 'test-client')
            ->willReturn(true);

        $result = $this->factory->canCreateClientService($container, 'test-client');

        $this->assertTrue($result);
    }

    public function testCreateClientService(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $expectedClient = $this->createMock(WeaviateClient::class);

        $this->clientFactory
            ->expects($this->once())
            ->method('hasClient')
            ->with($container, 'test-client')
            ->willReturn(true);

        $this->clientFactory
            ->expects($this->once())
            ->method('createClient')
            ->with($container, 'test-client')
            ->willReturn($expectedClient);

        $result = $this->factory->createClientService($container, 'test-client');

        $this->assertSame($expectedClient, $result);
        $this->assertInstanceOf(WeaviateClient::class, $result);
    }

    public function testGetServiceName(): void
    {
        $serviceName = WeaviateClientAbstractFactory::getServiceName('test-client');

        $this->assertEquals('weaviate.client.test-client', $serviceName);
    }

    public function testIsClientServiceName(): void
    {
        $this->assertTrue(WeaviateClientAbstractFactory::isClientServiceName('weaviate.client.test'));
        $this->assertFalse(WeaviateClientAbstractFactory::isClientServiceName('other.service.name'));
    }

    public function testExtractClientNameFromServiceName(): void
    {
        $clientName = WeaviateClientAbstractFactory::extractClientNameFromServiceName('weaviate.client.test-client');

        $this->assertEquals('test-client', $clientName);
    }

    public function testExtractClientNameFromServiceNameInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Service name 'invalid.service' is not a valid client service name");

        WeaviateClientAbstractFactory::extractClientNameFromServiceName('invalid.service');
    }

    public function testValidateAllClients(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $this->clientFactory
            ->expects($this->once())
            ->method('getConfiguredClientNames')
            ->with($container)
            ->willReturn(['client1', 'client2']);

        $this->clientFactory
            ->expects($this->exactly(2))
            ->method('hasClient')
            ->willReturnMap([
                [$container, 'client1', true],
                [$container, 'client2', false],
            ]);

        $result = $this->factory->validateAllClients($container);

        $expected = [
            'client1' => ['valid' => true, 'error' => null],
            'client2' => ['valid' => true, 'error' => null], // canCreate doesn't throw, just returns false
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetClientConfigurationSummary(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $this->clientFactory
            ->expects($this->once())
            ->method('getConfiguredClientNames')
            ->with($container)
            ->willReturn(['client1', 'client2']);

        $this->clientFactory
            ->expects($this->exactly(4))
            ->method('hasClient')
            ->willReturnMap([
                [$container, 'client1', true],
                [$container, 'client1', true], // Called twice: once for canCreate, once for hasClient
                [$container, 'client2', false],
                [$container, 'client2', false],
            ]);

        $result = $this->factory->getClientConfigurationSummary($container);

        $expected = [
            'client1' => [
                'service_name' => 'weaviate.client.client1',
                'can_create' => true,
                'has_config' => true,
            ],
            'client2' => [
                'service_name' => 'weaviate.client.client2',
                'can_create' => false,
                'has_config' => false,
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testConstructorWithDefaultFactory(): void
    {
        $factory = new WeaviateClientAbstractFactory();
        $container = $this->createMock(ContainerInterface::class);

        // This should not throw an exception, indicating the default factory was created
        $result = $factory->canCreate($container, 'invalid.service');
        $this->assertFalse($result);
    }

    public function testInvokeWithOptions(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $expectedClient = $this->createMock(WeaviateClient::class);
        $options = ['some' => 'options'];

        $this->clientFactory
            ->expects($this->once())
            ->method('hasClient')
            ->with($container, 'test-client')
            ->willReturn(true);

        $this->clientFactory
            ->expects($this->once())
            ->method('createClient')
            ->with($container, 'test-client')
            ->willReturn($expectedClient);

        $result = $this->factory->__invoke($container, 'weaviate.client.test-client', $options);

        $this->assertSame($expectedClient, $result);
    }
}
