<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Configuration;

use Zestic\WeaviateClientComponent\Exception\ConfigurationException;

/**
 * Configuration class for Weaviate client settings.
 */
class WeaviateConfig
{
    public const CONNECTION_METHOD_LOCAL = 'local';
    public const CONNECTION_METHOD_CLOUD = 'cloud';
    public const CONNECTION_METHOD_CUSTOM = 'custom';

    public const VALID_CONNECTION_METHODS = [
        self::CONNECTION_METHOD_LOCAL,
        self::CONNECTION_METHOD_CLOUD,
        self::CONNECTION_METHOD_CUSTOM,
    ];

    public const DEFAULT_MAX_RETRIES = 4;

    public function __construct(
        public string $connectionMethod = self::CONNECTION_METHOD_LOCAL,
        public ConnectionConfig $connection = new ConnectionConfig(),
        public ?AuthConfig $auth = null,
        public array $additionalHeaders = [],
        public bool $enableRetry = true,
        public int $maxRetries = self::DEFAULT_MAX_RETRIES
    ) {
        $this->validateConnectionMethod();
        $this->validateRetryConfig();
        $this->validateConnectionRequirements();
    }

    /**
     * Create WeaviateConfig from array configuration.
     */
    public static function fromArray(array $config): self
    {
        $connectionMethod = $config['connection_method'] ?? self::CONNECTION_METHOD_LOCAL;

        return new self(
            connectionMethod: $connectionMethod,
            connection: ConnectionConfig::fromArray($config['connection'] ?? []),
            auth: isset($config['auth']) ? AuthConfig::fromArray($config['auth']) : null,
            additionalHeaders: $config['additional_headers'] ?? [],
            enableRetry: $config['enable_retry'] ?? true,
            maxRetries: $config['max_retries'] ?? self::DEFAULT_MAX_RETRIES
        );
    }

    /**
     * Convert to array format.
     */
    public function toArray(): array
    {
        $result = [
            'connection_method' => $this->connectionMethod,
            'connection' => $this->connection->toArray(),
            'additional_headers' => $this->additionalHeaders,
            'enable_retry' => $this->enableRetry,
            'max_retries' => $this->maxRetries,
        ];

        if ($this->auth !== null) {
            $result['auth'] = $this->auth->toArray();
        }

        return $result;
    }

    /**
     * Check if this is a local connection.
     */
    public function isLocalConnection(): bool
    {
        return $this->connectionMethod === self::CONNECTION_METHOD_LOCAL;
    }

    /**
     * Check if this is a cloud connection.
     */
    public function isCloudConnection(): bool
    {
        return $this->connectionMethod === self::CONNECTION_METHOD_CLOUD;
    }

    /**
     * Check if this is a custom connection.
     */
    public function isCustomConnection(): bool
    {
        return $this->connectionMethod === self::CONNECTION_METHOD_CUSTOM;
    }

    /**
     * Check if authentication is configured.
     */
    public function hasAuth(): bool
    {
        return $this->auth !== null;
    }

    /**
     * Get all headers (additional headers merged with auth headers if applicable).
     */
    public function getAllHeaders(): array
    {
        $headers = $this->additionalHeaders;

        if ($this->auth !== null && $this->auth->isApiKey()) {
            $headers['Authorization'] = 'Bearer ' . $this->auth->apiKey;
        } elseif ($this->auth !== null && $this->auth->isBearerToken()) {
            $headers['Authorization'] = 'Bearer ' . $this->auth->bearerToken;
        }

        return $headers;
    }

    /**
     * Validate connection method.
     */
    private function validateConnectionMethod(): void
    {
        if (!in_array($this->connectionMethod, self::VALID_CONNECTION_METHODS, true)) {
            throw ConfigurationException::invalidConnectionMethod(
                $this->connectionMethod,
                self::VALID_CONNECTION_METHODS
            );
        }
    }

    /**
     * Validate retry configuration.
     */
    private function validateRetryConfig(): void
    {
        if ($this->maxRetries < 0) {
            throw ConfigurationException::invalidRetryConfig('max_retries must be >= 0');
        }

        if ($this->maxRetries > 10) {
            throw ConfigurationException::invalidRetryConfig('max_retries should not exceed 10');
        }
    }

    /**
     * Validate connection requirements based on connection method.
     */
    private function validateConnectionRequirements(): void
    {
        match ($this->connectionMethod) {
            self::CONNECTION_METHOD_LOCAL => $this->validateLocalConnection(),
            self::CONNECTION_METHOD_CLOUD => $this->validateCloudConnection(),
            self::CONNECTION_METHOD_CUSTOM => $this->validateCustomConnection(),
            default => throw ConfigurationException::invalidConnectionMethod(
                $this->connectionMethod,
                self::VALID_CONNECTION_METHODS
            )
        };
    }

    /**
     * Validate local connection requirements.
     */
    private function validateLocalConnection(): void
    {
        // For local connections, we need either host or default to localhost
        // No specific validation needed as defaults are acceptable
    }

    /**
     * Validate cloud connection requirements.
     */
    private function validateCloudConnection(): void
    {
        if ($this->connection->clusterUrl === null || trim($this->connection->clusterUrl) === '') {
            throw ConfigurationException::missingRequiredConfig('cluster_url', 'cloud connection');
        }

        if ($this->auth === null) {
            throw ConfigurationException::missingRequiredConfig('auth', 'cloud connection');
        }
    }

    /**
     * Validate custom connection requirements.
     */
    private function validateCustomConnection(): void
    {
        if ($this->connection->host === null || trim($this->connection->host) === '') {
            throw ConfigurationException::missingRequiredConfig('host', 'custom connection');
        }
    }
}
