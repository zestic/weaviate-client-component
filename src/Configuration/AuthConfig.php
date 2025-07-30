<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Configuration;

use Zestic\WeaviateClientComponent\Exception\ConfigurationException;

/**
 * Configuration class for Weaviate authentication settings.
 */
class AuthConfig
{
    public const TYPE_API_KEY = 'api_key';
    public const TYPE_BEARER_TOKEN = 'bearer_token';
    public const TYPE_OIDC = 'oidc';

    public const VALID_TYPES = [
        self::TYPE_API_KEY,
        self::TYPE_BEARER_TOKEN,
        self::TYPE_OIDC,
    ];

    public function __construct(
        public readonly string $type = self::TYPE_API_KEY,
        public readonly ?string $apiKey = null,
        public readonly ?string $bearerToken = null,
        public readonly ?string $clientId = null,
        public readonly ?string $clientSecret = null,
        public readonly ?string $scope = null,
        public readonly array $additionalParams = []
    ) {
        $this->validateType();
        $this->validateRequiredFields();
    }

    /**
     * Create AuthConfig from array configuration.
     */
    public static function fromArray(array $config): self
    {
        return new self(
            type: $config['type'] ?? self::TYPE_API_KEY,
            apiKey: $config['api_key'] ?? null,
            bearerToken: $config['bearer_token'] ?? null,
            clientId: $config['client_id'] ?? null,
            clientSecret: $config['client_secret'] ?? null,
            scope: $config['scope'] ?? null,
            additionalParams: $config['additional_params'] ?? []
        );
    }

    /**
     * Convert to array format.
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
            'additional_params' => $this->additionalParams,
        ];

        if ($this->apiKey !== null) {
            $result['api_key'] = $this->apiKey;
        }

        if ($this->bearerToken !== null) {
            $result['bearer_token'] = $this->bearerToken;
        }

        if ($this->clientId !== null) {
            $result['client_id'] = $this->clientId;
        }

        if ($this->clientSecret !== null) {
            $result['client_secret'] = $this->clientSecret;
        }

        if ($this->scope !== null) {
            $result['scope'] = $this->scope;
        }

        return $result;
    }

    /**
     * Check if this is an API key authentication.
     */
    public function isApiKey(): bool
    {
        return $this->type === self::TYPE_API_KEY;
    }

    /**
     * Check if this is a bearer token authentication.
     */
    public function isBearerToken(): bool
    {
        return $this->type === self::TYPE_BEARER_TOKEN;
    }

    /**
     * Check if this is an OIDC authentication.
     */
    public function isOidc(): bool
    {
        return $this->type === self::TYPE_OIDC;
    }

    /**
     * Validate the authentication type.
     */
    private function validateType(): void
    {
        if (!in_array($this->type, self::VALID_TYPES, true)) {
            throw ConfigurationException::invalidAuthType($this->type, self::VALID_TYPES);
        }
    }

    /**
     * Validate required fields based on authentication type.
     */
    private function validateRequiredFields(): void
    {
        match ($this->type) {
            self::TYPE_API_KEY => $this->validateApiKeyAuth(),
            self::TYPE_BEARER_TOKEN => $this->validateBearerTokenAuth(),
            self::TYPE_OIDC => $this->validateOidcAuth(),
            default => throw ConfigurationException::invalidAuthType($this->type, self::VALID_TYPES)
        };
    }

    /**
     * Validate API key authentication requirements.
     */
    private function validateApiKeyAuth(): void
    {
        if ($this->apiKey === null || trim($this->apiKey) === '') {
            throw ConfigurationException::missingRequiredConfig('api_key', 'API key authentication');
        }
    }

    /**
     * Validate bearer token authentication requirements.
     */
    private function validateBearerTokenAuth(): void
    {
        if ($this->bearerToken === null || trim($this->bearerToken) === '') {
            throw ConfigurationException::missingRequiredConfig('bearer_token', 'Bearer token authentication');
        }
    }

    /**
     * Validate OIDC authentication requirements.
     */
    private function validateOidcAuth(): void
    {
        if ($this->clientId === null || trim($this->clientId) === '') {
            throw ConfigurationException::missingRequiredConfig('client_id', 'OIDC authentication');
        }

        if ($this->clientSecret === null || trim($this->clientSecret) === '') {
            throw ConfigurationException::missingRequiredConfig('client_secret', 'OIDC authentication');
        }
    }
}
