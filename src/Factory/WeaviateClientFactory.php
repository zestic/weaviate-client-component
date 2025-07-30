<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Factory;

use Psr\Container\ContainerInterface;
use Weaviate\WeaviateClient;
use Zestic\WeaviateClientComponent\Configuration\WeaviateConfig;
use Zestic\WeaviateClientComponent\Exception\ConfigurationException;

/**
 * Factory for creating WeaviateClient instances with support for multiple named connections.
 */
class WeaviateClientFactory
{
    public function __construct(
        private readonly ConnectionFactory $connectionFactory = new ConnectionFactory(),
        private readonly AuthFactory $authFactory = new AuthFactory()
    ) {
    }

    /**
     * Create the default WeaviateClient instance.
     */
    public function __invoke(ContainerInterface $container): WeaviateClient
    {
        return $this->createClient($container, 'default');
    }

    /**
     * Create a WeaviateClient instance for a specific named client.
     */
    public function createClient(ContainerInterface $container, string $name = 'default'): WeaviateClient
    {
        $config = $container->get('config');
        $weaviateConfig = $config['weaviate'] ?? [];

        // Get client-specific configuration
        $clientConfig = $this->getClientConfig($weaviateConfig, $name);

        // Create WeaviateConfig object for validation
        $weaviateConfigObj = WeaviateConfig::fromArray($clientConfig);

        // Create connection and auth based on connection method
        return match ($weaviateConfigObj->connectionMethod) {
            WeaviateConfig::CONNECTION_METHOD_LOCAL => $this->createLocalClient($clientConfig),
            WeaviateConfig::CONNECTION_METHOD_CLOUD => $this->createCloudClient($clientConfig),
            WeaviateConfig::CONNECTION_METHOD_CUSTOM => $this->createCustomClient($clientConfig),
            default => throw ConfigurationException::invalidConnectionMethod(
                $weaviateConfigObj->connectionMethod,
                WeaviateConfig::VALID_CONNECTION_METHODS
            )
        };
    }

    /**
     * Get configuration for a specific client.
     */
    private function getClientConfig(array $weaviateConfig, string $name): array
    {
        // Support for multiple named clients
        if (isset($weaviateConfig['clients'][$name])) {
            return $weaviateConfig['clients'][$name];
        }

        // Backward compatibility: use root config as default
        if ($name === 'default' && !isset($weaviateConfig['clients'])) {
            return $weaviateConfig;
        }

        throw ConfigurationException::clientNotFound($name);
    }

    /**
     * Create local WeaviateClient instance.
     */
    private function createLocalClient(array $config): WeaviateClient
    {
        $connectionConfig = $this->connectionFactory->createConnection($config['connection'] ?? []);
        $auth = isset($config['auth']) ? $this->authFactory->createAuth($config['auth']) : null;

        // Extract host from the connection URL
        $url = $connectionConfig['url'];
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? 8080;
        $hostWithPort = $port !== 80 && $port !== 443 ? "{$host}:{$port}" : $host;

        return WeaviateClient::connectToLocal($hostWithPort, $auth);
    }

    /**
     * Create cloud WeaviateClient instance.
     */
    private function createCloudClient(array $config): WeaviateClient
    {
        if (!isset($config['connection']['cluster_url'])) {
            throw ConfigurationException::missingRequiredConfig('cluster_url', 'cloud connection');
        }

        if (!isset($config['auth'])) {
            throw ConfigurationException::missingRequiredConfig('auth', 'cloud connection');
        }

        $clusterUrl = $config['connection']['cluster_url'];
        $auth = $this->authFactory->createAuth($config['auth']);

        if ($auth === null) {
            throw ConfigurationException::missingRequiredConfig('auth', 'cloud connection');
        }

        return WeaviateClient::connectToWeaviateCloud($clusterUrl, $auth);
    }

    /**
     * Create custom WeaviateClient instance.
     */
    private function createCustomClient(array $config): WeaviateClient
    {
        if (!isset($config['connection']['host'])) {
            throw ConfigurationException::missingRequiredConfig('host', 'custom connection');
        }

        $auth = isset($config['auth']) ? $this->authFactory->createAuth($config['auth']) : null;

        $host = $config['connection']['host'];
        $port = $config['connection']['port'] ?? 8080;
        $secure = $config['connection']['secure'] ?? false;
        $headers = $config['additional_headers'] ?? [];

        return WeaviateClient::connectToCustom($host, $port, $secure, $auth, $headers);
    }

    /**
     * Create multiple clients from configuration.
     */
    public function createMultipleClients(ContainerInterface $container): array
    {
        $config = $container->get('config');
        $weaviateConfig = $config['weaviate'] ?? [];

        $clients = [];

        // Create clients from 'clients' configuration
        if (isset($weaviateConfig['clients'])) {
            foreach (array_keys($weaviateConfig['clients']) as $name) {
                $clients[$name] = $this->createClient($container, (string) $name);
            }
        } else {
            // Create default client from root configuration
            $clients['default'] = $this->createClient($container, 'default');
        }

        return $clients;
    }

    /**
     * Get all configured client names.
     */
    public function getConfiguredClientNames(ContainerInterface $container): array
    {
        $config = $container->get('config');
        $weaviateConfig = $config['weaviate'] ?? [];

        if (isset($weaviateConfig['clients'])) {
            return array_keys($weaviateConfig['clients']);
        }

        return ['default'];
    }

    /**
     * Check if a client is configured.
     */
    public function hasClient(ContainerInterface $container, string $name): bool
    {
        $config = $container->get('config');
        $weaviateConfig = $config['weaviate'] ?? [];

        if (isset($weaviateConfig['clients'][$name])) {
            return true;
        }

        return $name === 'default' && !isset($weaviateConfig['clients']);
    }

    /**
     * Validate client configuration.
     */
    public function validateClientConfig(array $clientConfig): bool
    {
        try {
            WeaviateConfig::fromArray($clientConfig);
            return true;
        } catch (ConfigurationException) {
            return false;
        }
    }
}
