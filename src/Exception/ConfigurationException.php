<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Exception;

use InvalidArgumentException;

/**
 * Exception thrown when there are configuration errors in the Weaviate client component.
 */
class ConfigurationException extends InvalidArgumentException
{
    /**
     * Create exception for invalid connection method.
     */
    public static function invalidConnectionMethod(string $method, array $validMethods): self
    {
        $validMethodsString = implode(', ', array_map(fn($m) => "'$m'", $validMethods));

        return new self(
            "Invalid connection method: '{$method}'. Must be one of: {$validMethodsString}"
        );
    }

    /**
     * Create exception for missing required configuration.
     */
    public static function missingRequiredConfig(string $key, string $context = ''): self
    {
        $message = "Missing required configuration: '{$key}'";
        if ($context !== '') {
            $message .= " in {$context}";
        }

        return new self($message);
    }

    /**
     * Create exception for invalid authentication type.
     */
    public static function invalidAuthType(string $type, array $validTypes): self
    {
        $validTypesString = implode(', ', array_map(fn($t) => "'$t'", $validTypes));

        return new self(
            "Invalid authentication type: '{$type}'. Must be one of: {$validTypesString}"
        );
    }

    /**
     * Create exception for invalid port number.
     */
    public static function invalidPort(int $port): self
    {
        return new self(
            "Invalid port number: {$port}. Port must be between 1 and 65535"
        );
    }

    /**
     * Create exception for invalid retry configuration.
     */
    public static function invalidRetryConfig(string $reason): self
    {
        return new self("Invalid retry configuration: {$reason}");
    }

    /**
     * Create exception for invalid client name.
     */
    public static function invalidClientName(string $name): self
    {
        return new self("Invalid client name: '{$name}'. Client name cannot be empty");
    }

    /**
     * Create exception for client not found.
     */
    public static function clientNotFound(string $name): self
    {
        return new self("Weaviate client '{$name}' not configured");
    }
}
