<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Factory;

use Psr\Container\ContainerInterface;
use Zestic\WeaviateClientComponent\Configuration\AuthConfig;
use Zestic\WeaviateClientComponent\Exception\ConfigurationException;

/**
 * Factory for creating authentication objects based on configuration.
 */
class AuthFactory
{
    /**
     * Create authentication object from container configuration.
     */
    public function __invoke(ContainerInterface $container): mixed
    {
        $config = $container->get('config');
        $weaviateConfig = $config['weaviate'] ?? [];

        if (!isset($weaviateConfig['auth'])) {
            throw ConfigurationException::missingRequiredConfig('auth', 'Weaviate configuration');
        }

        return $this->createAuth($weaviateConfig['auth']);
    }

    /**
     * Create authentication object from configuration array.
     */
    public function createAuth(array $authConfig): mixed
    {
        $authConfigObj = AuthConfig::fromArray($authConfig);

        return match ($authConfigObj->type) {
            AuthConfig::TYPE_API_KEY => $this->createApiKeyAuth($authConfigObj),
            AuthConfig::TYPE_BEARER_TOKEN => $this->createBearerTokenAuth($authConfigObj),
            AuthConfig::TYPE_OIDC => $this->createOidcAuth($authConfigObj),
            default => throw ConfigurationException::invalidAuthType(
                $authConfigObj->type,
                AuthConfig::VALID_TYPES
            )
        };
    }

    /**
     * Create API key authentication.
     */
    private function createApiKeyAuth(AuthConfig $config): array
    {
        return [
            'type' => 'api_key',
            'api_key' => $config->apiKey,
            'headers' => [
                'Authorization' => 'Bearer ' . $config->apiKey,
            ],
        ];
    }

    /**
     * Create Bearer token authentication.
     */
    private function createBearerTokenAuth(AuthConfig $config): array
    {
        return [
            'type' => 'bearer_token',
            'bearer_token' => $config->bearerToken,
            'headers' => [
                'Authorization' => 'Bearer ' . $config->bearerToken,
            ],
        ];
    }

    /**
     * Create OIDC authentication.
     */
    private function createOidcAuth(AuthConfig $config): array
    {
        return [
            'type' => 'oidc',
            'client_id' => $config->clientId,
            'client_secret' => $config->clientSecret,
            'scope' => $config->scope,
            'additional_params' => $config->additionalParams,
        ];
    }

    /**
     * Create authentication for a specific named client.
     */
    public function createAuthForClient(ContainerInterface $container, string $clientName): mixed
    {
        $config = $container->get('config');
        $weaviateConfig = $config['weaviate'] ?? [];

        // Check for client-specific auth configuration
        if (isset($weaviateConfig['clients'][$clientName]['auth'])) {
            return $this->createAuth($weaviateConfig['clients'][$clientName]['auth']);
        }

        // Fall back to global auth configuration
        if (isset($weaviateConfig['auth'])) {
            return $this->createAuth($weaviateConfig['auth']);
        }

        throw ConfigurationException::missingRequiredConfig(
            'auth',
            "client '{$clientName}' or global Weaviate configuration"
        );
    }

    /**
     * Check if authentication is configured for a client.
     */
    public function hasAuthForClient(ContainerInterface $container, string $clientName): bool
    {
        $config = $container->get('config');
        $weaviateConfig = $config['weaviate'] ?? [];

        return isset($weaviateConfig['clients'][$clientName]['auth']) ||
               isset($weaviateConfig['auth']);
    }

    /**
     * Get authentication headers for HTTP requests.
     */
    public function getAuthHeaders(array $authConfig): array
    {
        $auth = $this->createAuth($authConfig);

        return $auth['headers'] ?? [];
    }
}
