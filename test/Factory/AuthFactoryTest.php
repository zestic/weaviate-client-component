<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Factory;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zestic\WeaviateClientComponent\Configuration\AuthConfig;
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

        $this->assertEquals('api_key', $result['type']);
        $this->assertEquals('test-api-key', $result['api_key']);
        $this->assertEquals(['Authorization' => 'Bearer test-api-key'], $result['headers']);
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

        $this->assertEquals('bearer_token', $result['type']);
        $this->assertEquals('test-bearer-token', $result['bearer_token']);
        $this->assertEquals(['Authorization' => 'Bearer test-bearer-token'], $result['headers']);
    }

    public function testInvokeWithOidcAuth(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [
                'auth' => [
                    'type' => 'oidc',
                    'client_id' => 'test-client-id',
                    'client_secret' => 'test-client-secret',
                    'scope' => 'read write',
                ],
            ],
        ]);

        $result = ($this->factory)($container);

        $this->assertEquals('oidc', $result['type']);
        $this->assertEquals('test-client-id', $result['client_id']);
        $this->assertEquals('test-client-secret', $result['client_secret']);
        $this->assertEquals('read write', $result['scope']);
    }

    public function testInvokeWithMissingAuth(): void
    {
        $container = $this->createMockContainer([
            'weaviate' => [],
        ]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'auth' in Weaviate configuration");

        ($this->factory)($container);
    }

    public function testCreateAuthApiKey(): void
    {
        $authConfig = [
            'type' => 'api_key',
            'api_key' => 'test-key',
        ];

        $result = $this->factory->createAuth($authConfig);

        $this->assertEquals('api_key', $result['type']);
        $this->assertEquals('test-key', $result['api_key']);
        $this->assertEquals(['Authorization' => 'Bearer test-key'], $result['headers']);
    }

    public function testCreateAuthBearerToken(): void
    {
        $authConfig = [
            'type' => 'bearer_token',
            'bearer_token' => 'test-token',
        ];

        $result = $this->factory->createAuth($authConfig);

        $this->assertEquals('bearer_token', $result['type']);
        $this->assertEquals('test-token', $result['bearer_token']);
        $this->assertEquals(['Authorization' => 'Bearer test-token'], $result['headers']);
    }

    public function testCreateAuthOidc(): void
    {
        $authConfig = [
            'type' => 'oidc',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
            'scope' => 'read write',
            'additional_params' => ['audience' => 'weaviate'],
        ];

        $result = $this->factory->createAuth($authConfig);

        $this->assertEquals('oidc', $result['type']);
        $this->assertEquals('client-123', $result['client_id']);
        $this->assertEquals('secret-456', $result['client_secret']);
        $this->assertEquals('read write', $result['scope']);
        $this->assertEquals(['audience' => 'weaviate'], $result['additional_params']);
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

        $this->assertEquals('bearer_token', $result['type']);
        $this->assertEquals('client-token', $result['bearer_token']);
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

        $this->assertEquals('api_key', $result['type']);
        $this->assertEquals('global-key', $result['api_key']);
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
                    'test-client' => [],
                ],
            ],
        ]);

        $this->assertFalse($this->factory->hasAuthForClient($container, 'test-client'));
    }

    public function testGetAuthHeaders(): void
    {
        $authConfig = [
            'type' => 'api_key',
            'api_key' => 'test-key',
        ];

        $headers = $this->factory->getAuthHeaders($authConfig);

        $this->assertEquals(['Authorization' => 'Bearer test-key'], $headers);
    }

    public function testGetAuthHeadersOidc(): void
    {
        $authConfig = [
            'type' => 'oidc',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
        ];

        $headers = $this->factory->getAuthHeaders($authConfig);

        $this->assertEquals([], $headers);
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
