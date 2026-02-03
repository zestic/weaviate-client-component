<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Factory;

use Psr\Container\ContainerInterface;
use Weaviate\Auth\ApiKey;
use Weaviate\Auth\AuthInterface;
use Zestic\WeaviateClientComponent\Configuration\AuthConfig;
use Zestic\WeaviateClientComponent\Exception\ConfigurationException;

class AuthFactory
{
    public function __invoke(ContainerInterface $container): ?AuthInterface
    {
        $config = $container->get('config');
        $weaviateConfig = $config['weaviate'] ?? [];

        if (!isset($weaviateConfig['auth'])) {
            return null;
        }

        return $this->createAuth($weaviateConfig['auth']);
    }

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

    public function hasAuthForClient(ContainerInterface $container, string $clientName): bool
    {
        $config = $container->get('config');
        $weaviateConfig = $config['weaviate'] ?? [];

        return isset($weaviateConfig['clients'][$clientName]['auth']) ||
               isset($weaviateConfig['auth']);
    }

    private function createApiKeyAuth(AuthConfig $config): ApiKey
    {
        if ($config->apiKey === null) {
            throw ConfigurationException::missingRequiredConfig('api_key', 'API key authentication');
        }

        return new ApiKey($config->apiKey);
    }

    private function createBearerTokenAuth(AuthConfig $config): AuthInterface
    {
        if ($config->bearerToken === null) {
            throw ConfigurationException::missingRequiredConfig('bearer_token', 'Bearer token authentication');
        }

        // For now, use ApiKey for bearer tokens as they work the same way
        return new ApiKey($config->bearerToken);
    }

    /**
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
}
