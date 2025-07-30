<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Factory;

use Psr\Container\ContainerInterface;
use Weaviate\Auth\ApiKey;
use Weaviate\Auth\AuthInterface;
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
    public function __invoke(ContainerInterface $container): ?AuthInterface
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
    public function createAuth(array $authConfig): ?AuthInterface
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
    private function createApiKeyAuth(AuthConfig $config): ApiKey
    {
        if ($config->apiKey === null) {
            throw ConfigurationException::missingRequiredConfig('api_key', 'API key authentication');
        }

        return new ApiKey($config->apiKey);
    }

    /**
     * Create Bearer token authentication.
     */
    private function createBearerTokenAuth(AuthConfig $config): AuthInterface
    {
        if ($config->bearerToken === null) {
            throw ConfigurationException::missingRequiredConfig('bearer_token', 'Bearer token authentication');
        }

        // For now, use ApiKey for bearer tokens as they work the same way
        return new ApiKey($config->bearerToken);
    }

    /**
     * Create OIDC authentication.
     *
     * @return never
     */
    private function createOidcAuth(AuthConfig $config): AuthInterface
    {
        // OIDC is not yet implemented in the Weaviate PHP client
        throw ConfigurationException::invalidAuthType(
            'oidc',
            ['api_key', 'bearer_token']
        );
    }

    /**
     * Create authentication for a specific named client.
     */
    public function createAuthForClient(ContainerInterface $container, string $clientName): ?AuthInterface
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
     *
     * @deprecated This method is deprecated as auth objects now handle headers internally
     */
    public function getAuthHeaders(array $authConfig): array
    {
        // This method is kept for backward compatibility but is deprecated
        // Auth objects now handle headers internally via the apply() method

        try {
            $auth = $this->createAuth($authConfig);

            if ($auth === null) {
                return [];
            }

            // Extract headers from auth objects for backward compatibility
            if ($auth instanceof ApiKey) {
                // We need to simulate what the ApiKey would do
                // Since we can't access the private $apiKey property, we'll reconstruct it
                if (isset($authConfig['api_key'])) {
                    return ['Authorization' => 'Bearer ' . $authConfig['api_key']];
                }
                if (isset($authConfig['bearer_token'])) {
                    return ['Authorization' => 'Bearer ' . $authConfig['bearer_token']];
                }
            }

            return [];
        } catch (ConfigurationException $e) {
            // For unsupported auth types (like OIDC), return empty headers
            // This maintains backward compatibility where unsupported types would return []
            return [];
        }
    }
}
