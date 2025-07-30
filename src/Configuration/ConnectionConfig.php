<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Configuration;

use Zestic\WeaviateClientComponent\Exception\ConfigurationException;

/**
 * Configuration class for Weaviate connection settings.
 */
class ConnectionConfig
{
    public const DEFAULT_PORT = 8080;
    public const DEFAULT_SECURE = false;
    public const MIN_PORT = 1;
    public const MAX_PORT = 65535;

    public function __construct(
        public readonly ?string $host = null,
        public readonly int $port = self::DEFAULT_PORT,
        public readonly bool $secure = self::DEFAULT_SECURE,
        public readonly ?string $clusterUrl = null,
        public readonly int $timeout = 30,
        public readonly array $headers = []
    ) {
        $this->validatePort();
        $this->validateTimeout();
    }

    /**
     * Create ConnectionConfig from array configuration.
     */
    public static function fromArray(array $config): self
    {
        return new self(
            host: $config['host'] ?? null,
            port: $config['port'] ?? self::DEFAULT_PORT,
            secure: $config['secure'] ?? self::DEFAULT_SECURE,
            clusterUrl: $config['cluster_url'] ?? null,
            timeout: $config['timeout'] ?? 30,
            headers: $config['headers'] ?? []
        );
    }

    /**
     * Convert to array format.
     */
    public function toArray(): array
    {
        $result = [
            'port' => $this->port,
            'secure' => $this->secure,
            'timeout' => $this->timeout,
            'headers' => $this->headers,
        ];

        if ($this->host !== null) {
            $result['host'] = $this->host;
        }

        if ($this->clusterUrl !== null) {
            $result['cluster_url'] = $this->clusterUrl;
        }

        return $result;
    }

    /**
     * Get the full URL for the connection.
     */
    public function getUrl(): string
    {
        if ($this->clusterUrl !== null) {
            $protocol = $this->secure ? 'https' : 'http';
            return "{$protocol}://{$this->clusterUrl}";
        }

        if ($this->host === null) {
            throw ConfigurationException::missingRequiredConfig('host', 'connection configuration');
        }

        $protocol = $this->secure ? 'https' : 'http';

        // Check if host already includes port
        if (str_contains($this->host, ':')) {
            return "{$protocol}://{$this->host}";
        }

        return "{$protocol}://{$this->host}:{$this->port}";
    }

    /**
     * Get the host without port.
     */
    public function getHostOnly(): ?string
    {
        if ($this->host === null) {
            return null;
        }

        // Handle IPv6 addresses (they contain colons but are not host:port format)
        if ($this->host === '::1' || str_starts_with($this->host, '[')) {
            return $this->host;
        }

        // If host contains port, extract just the host part
        if (str_contains($this->host, ':')) {
            return explode(':', $this->host)[0];
        }

        return $this->host;
    }

    /**
     * Get the port from host if specified, otherwise return configured port.
     */
    public function getEffectivePort(): int
    {
        if ($this->host !== null && str_contains($this->host, ':')) {
            $parts = explode(':', $this->host);
            if (count($parts) === 2 && is_numeric($parts[1])) {
                return (int) $parts[1];
            }
        }

        return $this->port;
    }

    /**
     * Check if this is a cloud connection.
     */
    public function isCloudConnection(): bool
    {
        return $this->clusterUrl !== null;
    }

    /**
     * Check if this is a local connection.
     */
    public function isLocalConnection(): bool
    {
        if ($this->clusterUrl !== null) {
            return false;
        }

        $hostOnly = $this->getHostOnly();
        return $hostOnly === 'localhost' || $hostOnly === '127.0.0.1' || $hostOnly === '::1';
    }

    /**
     * Validate port number.
     */
    private function validatePort(): void
    {
        if ($this->port < self::MIN_PORT || $this->port > self::MAX_PORT) {
            throw ConfigurationException::invalidPort($this->port);
        }
    }

    /**
     * Validate timeout value.
     */
    private function validateTimeout(): void
    {
        if ($this->timeout <= 0) {
            throw new ConfigurationException('Timeout must be greater than 0');
        }
    }
}
