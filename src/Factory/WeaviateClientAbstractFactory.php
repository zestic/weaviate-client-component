<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Factory;

use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;
use Zestic\WeaviateClientComponent\Exception\ConfigurationException;

/**
 * Abstract factory for dynamic WeaviateClient creation.
 *
 * Handles requests like 'weaviate.client.{name}' and creates the appropriate client instance.
 */
class WeaviateClientAbstractFactory implements AbstractFactoryInterface
{
    private const CLIENT_PREFIX = 'weaviate.client.';

    public function __construct(
        private readonly WeaviateClientFactory $clientFactory = new WeaviateClientFactory()
    ) {
    }

    /**
     * Determine if this factory can create the requested service.
     */
    public function canCreate(ContainerInterface $container, $requestedName): bool
    {
        // Ensure requestedName is a string
        if (!is_string($requestedName)) {
            return false;
        }

        // Handle requests like 'weaviate.client.{name}'
        if (!str_starts_with($requestedName, self::CLIENT_PREFIX)) {
            return false;
        }

        $clientName = $this->extractClientName($requestedName);

        // Check if client is configured
        return $this->clientFactory->hasClient($container, $clientName);
    }

    /**
     * Create the requested WeaviateClient instance.
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): object {
        if (!is_string($requestedName)) {
            throw new \InvalidArgumentException('Requested name must be a string');
        }

        if (!$this->canCreate($container, $requestedName)) {
            throw ConfigurationException::clientNotFound($requestedName);
        }

        $clientName = $this->extractClientName($requestedName);

        return $this->clientFactory->createClient($container, $clientName);
    }

    /**
     * Extract client name from requested service name.
     */
    private function extractClientName(string $requestedName): string
    {
        return substr($requestedName, strlen(self::CLIENT_PREFIX));
    }

    /**
     * Get all service names this factory can create.
     */
    public function getCreatableServiceNames(ContainerInterface $container): array
    {
        $clientNames = $this->clientFactory->getConfiguredClientNames($container);

        return array_map(
            fn(string $name): string => self::CLIENT_PREFIX . $name,
            $clientNames
        );
    }

    /**
     * Check if a specific client service name can be created.
     */
    public function canCreateClientService(ContainerInterface $container, string $clientName): bool
    {
        $serviceName = self::CLIENT_PREFIX . $clientName;
        return $this->canCreate($container, $serviceName);
    }

    /**
     * Create a client service by client name (convenience method).
     */
    public function createClientService(ContainerInterface $container, string $clientName): object
    {
        $serviceName = self::CLIENT_PREFIX . $clientName;
        return $this->__invoke($container, $serviceName);
    }

    /**
     * Get the service name for a client.
     */
    public static function getServiceName(string $clientName): string
    {
        return self::CLIENT_PREFIX . $clientName;
    }

    /**
     * Check if a service name matches the pattern this factory handles.
     */
    public static function isClientServiceName(string $serviceName): bool
    {
        return str_starts_with($serviceName, self::CLIENT_PREFIX);
    }

    /**
     * Extract client name from service name (static version).
     */
    public static function extractClientNameFromServiceName(string $serviceName): string
    {
        if (!self::isClientServiceName($serviceName)) {
            throw new \InvalidArgumentException(
                "Service name '{$serviceName}' is not a valid client service name"
            );
        }

        return substr($serviceName, strlen(self::CLIENT_PREFIX));
    }

    /**
     * Validate that all configured clients can be created.
     */
    public function validateAllClients(ContainerInterface $container): array
    {
        $clientNames = $this->clientFactory->getConfiguredClientNames($container);
        $results = [];

        foreach ($clientNames as $clientName) {
            $serviceName = self::CLIENT_PREFIX . $clientName;

            try {
                $this->canCreate($container, $serviceName);
                $results[$clientName] = ['valid' => true, 'error' => null];
            } catch (\Exception $e) {
                $results[$clientName] = ['valid' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Get configuration summary for all clients.
     */
    public function getClientConfigurationSummary(ContainerInterface $container): array
    {
        $clientNames = $this->clientFactory->getConfiguredClientNames($container);
        $summary = [];

        foreach ($clientNames as $clientName) {
            $serviceName = self::CLIENT_PREFIX . $clientName;

            $summary[$clientName] = [
                'service_name' => $serviceName,
                'can_create' => $this->canCreate($container, $serviceName),
                'has_config' => $this->clientFactory->hasClient($container, $clientName),
            ];
        }

        return $summary;
    }
}
