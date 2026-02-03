<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Factory;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zestic\WeaviateClientComponent\Exception\ConfigurationException;
use Zestic\WeaviateClientComponent\Factory\AuthFactory;

class AuthFactoryTest extends TestCase
{
    private AuthFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new AuthFactory();
    }

    public function testInvokeWithApiKeyAuth(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'auth' => [
                    'type' => 'api_key',
                    'api_key' => 'test-api-key',
                ],
            ],
        ]);

        $result = ($this->factory)($container);

        // Now we expect an actual ApiKey object
        $this->assertInstanceOf(\Weaviate\Auth\ApiKey::class, $result);

        // We can test that the auth object works by applying it to a mock request
        $mockRequest = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $mockRequest->expects($this->once())
            ->method('withHeader')
            ->with('Authorization', 'Bearer test-api-key')
            ->willReturnSelf();

        $result->apply($mockRequest);
    }

    public function testInvokeWithBearerTokenAuth(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'auth' => [
                    'type' => 'bearer_token',
                    'bearer_token' => 'test-bearer-token',
                ],
            ],
        ]);

        $result = ($this->factory)($container);

        // Bearer token auth also returns an ApiKey object (as per implementation)
        $this->assertInstanceOf(\Weaviate\Auth\ApiKey::class, $result);

        // Test that the auth object works by applying it to a mock request
        $mockRequest = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $mockRequest->expects($this->once())
            ->method('withHeader')
            ->with('Authorization', 'Bearer test-bearer-token')
            ->willReturnSelf();

        $result->apply($mockRequest);
    }

    public function testInvokeWithOidcAuth(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'auth' => [
                    'type' => 'oidc',
                    'client_id' => 'test-client',
                    'client_secret' => 'test-secret',
                ],
            ],
        ]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Invalid authentication type: 'oidc'. Must be one of: 'api_key', 'bearer_token'");

        ($this->factory)($container);
    }

    public function testInvokeWithMissingAuth(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [],
        ]);

        $result = ($this->factory)($container);

        $this->assertNull($result);
    }


    public function testCreateAuthApiKey(): void
    {
        $authConfig = [
            'type' => 'api_key',
            'api_key' => 'test-key',
        ];

        $result = $this->factory->createAuth($authConfig);

        // Should return an ApiKey object
        $this->assertInstanceOf(\Weaviate\Auth\ApiKey::class, $result);

        // Test that the auth object works by applying it to a mock request
        $mockRequest = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $mockRequest->expects($this->once())
            ->method('withHeader')
            ->with('Authorization', 'Bearer test-key')
            ->willReturnSelf();

        $result->apply($mockRequest);
    }

    public function testCreateAuthBearerToken(): void
    {
        $authConfig = [
            'type' => 'bearer_token',
            'bearer_token' => 'test-token',
        ];

        $result = $this->factory->createAuth($authConfig);

        // Bearer token also returns an ApiKey object (as per implementation)
        $this->assertInstanceOf(\Weaviate\Auth\ApiKey::class, $result);

        // Test that the auth object works by applying it to a mock request
        $mockRequest = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $mockRequest->expects($this->once())
            ->method('withHeader')
            ->with('Authorization', 'Bearer test-token')
            ->willReturnSelf();

        $result->apply($mockRequest);
    }

    public function testCreateAuthOidc(): void
    {
        $authConfig = [
            'type' => 'oidc',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
        ];

        // OIDC is not yet implemented, so it should throw an exception
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Invalid authentication type: 'oidc'. Must be one of: 'api_key', 'bearer_token'");

        $this->factory->createAuth($authConfig);
    }

    public function testCreateAuthInvalidType(): void
    {
        $authConfig = [
            'type' => 'invalid',
        ];

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            "Invalid authentication type: 'invalid'. Must be one of: 'api_key', 'bearer_token', 'oidc'"
        );
        $this->factory->createAuth($authConfig);
    }

    public function testCreateAuthForClientWithClientSpecificAuth(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'auth' => [
                    'type' => 'api_key',
                    'api_key' => 'global-key',
                ],
                'clients' => [
                    'test-client' => [
                        'auth' => [
                            'type' => 'bearer_token',
                            'bearer_token' => 'client-token',
                        ],
                    ],
                ],
            ],
        ]);

        $result = $this->factory->createAuthForClient($container, 'test-client');

        // Should return an ApiKey object (bearer token uses ApiKey implementation)
        $this->assertInstanceOf(\Weaviate\Auth\ApiKey::class, $result);

        // Test that the auth object works by applying it to a mock request
        $mockRequest = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $mockRequest->expects($this->once())
            ->method('withHeader')
            ->with('Authorization', 'Bearer client-token')
            ->willReturnSelf();

        $result->apply($mockRequest);
    }

    public function testCreateAuthForClientWithGlobalAuth(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'auth' => [
                    'type' => 'api_key',
                    'api_key' => 'global-key',
                ],
                'clients' => [
                    'test-client' => [],
                ],
            ],
        ]);

        $result = $this->factory->createAuthForClient($container, 'test-client');

        // Should return an ApiKey object
        $this->assertInstanceOf(\Weaviate\Auth\ApiKey::class, $result);

        // Test that the auth object works by applying it to a mock request
        $mockRequest = $this->createMock(\Psr\Http\Message\RequestInterface::class);
        $mockRequest->expects($this->once())
            ->method('withHeader')
            ->with('Authorization', 'Bearer global-key')
            ->willReturnSelf();

        $result->apply($mockRequest);
    }

    public function testCreateAuthForClientWithNoAuth(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'test-client' => [],
                ],
            ],
        ]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            "Missing required configuration: 'auth' in client 'test-client' or global Weaviate configuration"
        );

        $this->factory->createAuthForClient($container, 'test-client');
    }

    public function testHasAuthForClientWithClientAuth(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'test-client' => [
                        'auth' => [
                            'type' => 'api_key',
                            'api_key' => 'test-key',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($this->factory->hasAuthForClient($container, 'test-client'));
    }

    public function testHasAuthForClientWithGlobalAuth(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'auth' => [
                    'type' => 'api_key',
                    'api_key' => 'global-key',
                ],
                'clients' => [
                    'test-client' => [],
                ],
            ],
        ]);

        $this->assertTrue($this->factory->hasAuthForClient($container, 'test-client'));
    }

    public function testHasAuthForClientWithNoAuth(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'clients' => [
                    'test_client' => [],
                ],
            ],
        ]);

        $this->assertFalse($this->factory->hasAuthForClient($container, 'test_client'));
    }

    private function createMockContainer(array $config): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with('config')->willReturn($config);

        return $container;
    }
}
