<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Test\Configuration;

use PHPUnit\Framework\TestCase;
use Zestic\WeaviateClientComponent\Configuration\AuthConfig;
use Zestic\WeaviateClientComponent\Exception\ConfigurationException;

class AuthConfigTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $config = new AuthConfig(apiKey: 'test-key');

        $this->assertEquals(AuthConfig::TYPE_API_KEY, $config->type);
        $this->assertEquals('test-key', $config->apiKey);
        $this->assertNull($config->bearerToken);
        $this->assertNull($config->clientId);
        $this->assertNull($config->clientSecret);
        $this->assertNull($config->scope);
        $this->assertEquals([], $config->additionalParams);
    }

    public function testConstructorWithAllParameters(): void
    {
        $config = new AuthConfig(
            type: AuthConfig::TYPE_OIDC,
            apiKey: null,
            bearerToken: null,
            clientId: 'client-123',
            clientSecret: 'secret-456',
            scope: 'read write',
            additionalParams: ['custom' => 'value']
        );

        $this->assertEquals(AuthConfig::TYPE_OIDC, $config->type);
        $this->assertNull($config->apiKey);
        $this->assertNull($config->bearerToken);
        $this->assertEquals('client-123', $config->clientId);
        $this->assertEquals('secret-456', $config->clientSecret);
        $this->assertEquals('read write', $config->scope);
        $this->assertEquals(['custom' => 'value'], $config->additionalParams);
    }

    public function testFromArrayApiKey(): void
    {
        $data = [
            'type' => 'api_key',
            'api_key' => 'test-api-key',
        ];

        $config = AuthConfig::fromArray($data);

        $this->assertEquals(AuthConfig::TYPE_API_KEY, $config->type);
        $this->assertEquals('test-api-key', $config->apiKey);
        $this->assertTrue($config->isApiKey());
        $this->assertFalse($config->isBearerToken());
        $this->assertFalse($config->isOidc());
    }

    public function testFromArrayBearerToken(): void
    {
        $data = [
            'type' => 'bearer_token',
            'bearer_token' => 'test-bearer-token',
        ];

        $config = AuthConfig::fromArray($data);

        $this->assertEquals(AuthConfig::TYPE_BEARER_TOKEN, $config->type);
        $this->assertEquals('test-bearer-token', $config->bearerToken);
        $this->assertFalse($config->isApiKey());
        $this->assertTrue($config->isBearerToken());
        $this->assertFalse($config->isOidc());
    }

    public function testFromArrayOidc(): void
    {
        $data = [
            'type' => 'oidc',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
            'scope' => 'read write',
            'additional_params' => ['custom' => 'value'],
        ];

        $config = AuthConfig::fromArray($data);

        $this->assertEquals(AuthConfig::TYPE_OIDC, $config->type);
        $this->assertEquals('client-123', $config->clientId);
        $this->assertEquals('secret-456', $config->clientSecret);
        $this->assertEquals('read write', $config->scope);
        $this->assertEquals(['custom' => 'value'], $config->additionalParams);
        $this->assertFalse($config->isApiKey());
        $this->assertFalse($config->isBearerToken());
        $this->assertTrue($config->isOidc());
    }

    public function testFromArrayWithDefaults(): void
    {
        // API key auth requires an api_key, so we need to provide one
        $config = AuthConfig::fromArray(['api_key' => 'test-key']);

        $this->assertEquals(AuthConfig::TYPE_API_KEY, $config->type);
        $this->assertEquals('test-key', $config->apiKey);
        $this->assertEquals([], $config->additionalParams);
    }

    public function testToArrayApiKey(): void
    {
        $config = new AuthConfig(
            type: AuthConfig::TYPE_API_KEY,
            apiKey: 'test-key'
        );

        $expected = [
            'type' => 'api_key',
            'api_key' => 'test-key',
            'additional_params' => [],
        ];

        $this->assertEquals($expected, $config->toArray());
    }

    public function testToArrayBearerToken(): void
    {
        $config = new AuthConfig(
            type: AuthConfig::TYPE_BEARER_TOKEN,
            bearerToken: 'test-token'
        );

        $expected = [
            'type' => 'bearer_token',
            'bearer_token' => 'test-token',
            'additional_params' => [],
        ];

        $this->assertEquals($expected, $config->toArray());
    }

    public function testToArrayOidc(): void
    {
        $config = new AuthConfig(
            type: AuthConfig::TYPE_OIDC,
            clientId: 'client-123',
            clientSecret: 'secret-456',
            scope: 'read write',
            additionalParams: ['custom' => 'value']
        );

        $expected = [
            'type' => 'oidc',
            'client_id' => 'client-123',
            'client_secret' => 'secret-456',
            'scope' => 'read write',
            'additional_params' => ['custom' => 'value'],
        ];

        $this->assertEquals($expected, $config->toArray());
    }

    public function testInvalidAuthType(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            "Invalid authentication type: 'invalid'. Must be one of: 'api_key', 'bearer_token', 'oidc'"
        );

        new AuthConfig(type: 'invalid');
    }

    public function testApiKeyValidationMissingKey(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'api_key' in API key authentication");

        new AuthConfig(type: AuthConfig::TYPE_API_KEY);
    }

    public function testApiKeyValidationEmptyKey(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'api_key' in API key authentication");

        new AuthConfig(type: AuthConfig::TYPE_API_KEY, apiKey: '   ');
    }

    public function testBearerTokenValidationMissingToken(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'bearer_token' in Bearer token authentication");

        new AuthConfig(type: AuthConfig::TYPE_BEARER_TOKEN);
    }

    public function testBearerTokenValidationEmptyToken(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'bearer_token' in Bearer token authentication");

        new AuthConfig(type: AuthConfig::TYPE_BEARER_TOKEN, bearerToken: '');
    }

    public function testOidcValidationMissingClientId(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'client_id' in OIDC authentication");

        new AuthConfig(
            type: AuthConfig::TYPE_OIDC,
            clientSecret: 'secret'
        );
    }

    public function testOidcValidationMissingClientSecret(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'client_secret' in OIDC authentication");

        new AuthConfig(
            type: AuthConfig::TYPE_OIDC,
            clientId: 'client-123'
        );
    }

    public function testOidcValidationEmptyClientId(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'client_id' in OIDC authentication");

        new AuthConfig(
            type: AuthConfig::TYPE_OIDC,
            clientId: '  ',
            clientSecret: 'secret'
        );
    }

    public function testOidcValidationEmptyClientSecret(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Missing required configuration: 'client_secret' in OIDC authentication");

        new AuthConfig(
            type: AuthConfig::TYPE_OIDC,
            clientId: 'client-123',
            clientSecret: '  '
        );
    }
}
