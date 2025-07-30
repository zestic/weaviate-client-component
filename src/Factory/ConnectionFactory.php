<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Factory;

use Psr\Container\ContainerInterface;
use Zestic\WeaviateClientComponent\Configuration\ConnectionConfig;
use Zestic\WeaviateClientComponent\Exception\ConfigurationException;

/**
 * Factory for creating connection objects based on configuration.
 */
class ConnectionFactory
{
    /**
     * Create connection object from container configuration.
     */
    public function __invoke(ContainerInterface $container): array
    {
        $config = $container->get('config');
        $weaviateConfig = $config['weaviate'] ?? [];

        $connectionConfig = $weaviateConfig['connection'] ?? [];
        return $this->createConnection($connectionConfig);
    }

    /**
     * Create connection object from configuration array.
     */
    public function createConnection(array $connectionConfig): array
    {
        $config = ConnectionConfig::fromArray($connectionConfig);

        return [
            'url' => $this->buildUrl($config),
            'host' => $config->getHostOnly(),
            'port' => $config->getEffectivePort(),
            'secure' => $config->secure,
            'timeout' => $config->timeout,
            'headers' => $config->headers,
            'cluster_url' => $config->clusterUrl,
            'is_cloud' => $config->isCloudConnection(),
            'is_local' => $config->isLocalConnection(),
        ];
    }

    /**
     * Create connection for a specific named client.
     */
    public function createConnectionForClient(ContainerInterface $container, string $clientName): array
    {
        $config = $container->get('config');
        $weaviateConfig = $config['weaviate'] ?? [];

        // Check for client-specific connection configuration
        if (isset($weaviateConfig['clients'][$clientName]['connection'])) {
            return $this->createConnection($weaviateConfig['clients'][$clientName]['connection']);
        }

        // Fall back to global connection configuration
        if (isset($weaviateConfig['connection'])) {
            return $this->createConnection($weaviateConfig['connection']);
        }

        // Use default local connection
        return $this->createConnection([]);
    }

    /**
     * Build URL from connection configuration.
     */
    private function buildUrl(ConnectionConfig $config): string
    {
        if ($config->clusterUrl !== null) {
            $protocol = $config->secure ? 'https' : 'http';
            return "{$protocol}://{$config->clusterUrl}";
        }

        if ($config->host === null) {
            // Default to localhost
            $protocol = $config->secure ? 'https' : 'http';
            return "{$protocol}://localhost:{$config->port}";
        }

        $protocol = $config->secure ? 'https' : 'http';

        // Check if host already includes port
        if (str_contains($config->host, ':')) {
            return "{$protocol}://{$config->host}";
        }

        return "{$protocol}://{$config->host}:{$config->port}";
    }

    /**
     * Create connection configuration for local Weaviate instance.
     */
    public function createLocalConnection(string $host = 'localhost', int $port = 8080, bool $secure = false): array
    {
        return $this->createConnection([
            'host' => $host,
            'port' => $port,
            'secure' => $secure,
        ]);
    }

    /**
     * Create connection configuration for Weaviate Cloud.
     */
    public function createCloudConnection(string $clusterUrl, bool $secure = true): array
    {
        return $this->createConnection([
            'cluster_url' => $clusterUrl,
            'secure' => $secure,
        ]);
    }

    /**
     * Create connection configuration for custom Weaviate instance.
     */
    public function createCustomConnection(
        string $host,
        int $port = 8080,
        bool $secure = false,
        int $timeout = 30,
        array $headers = []
    ): array {
        return $this->createConnection([
            'host' => $host,
            'port' => $port,
            'secure' => $secure,
            'timeout' => $timeout,
            'headers' => $headers,
        ]);
    }

    /**
     * Validate connection by attempting to build URL.
     */
    public function validateConnection(array $connectionConfig): bool
    {
        try {
            $config = ConnectionConfig::fromArray($connectionConfig);
            $this->buildUrl($config);
            return true;
        } catch (ConfigurationException) {
            return false;
        }
    }

    /**
     * Get connection timeout for HTTP client configuration.
     */
    public function getConnectionTimeout(array $connectionConfig): int
    {
        $config = ConnectionConfig::fromArray($connectionConfig);
        return $config->timeout;
    }

    /**
     * Get connection headers for HTTP client configuration.
     */
    public function getConnectionHeaders(array $connectionConfig): array
    {
        $config = ConnectionConfig::fromArray($connectionConfig);
        return $config->headers;
    }

    /**
     * Check if connection is secure (HTTPS).
     */
    public function isSecureConnection(array $connectionConfig): bool
    {
        $config = ConnectionConfig::fromArray($connectionConfig);
        return $config->secure;
    }
}
