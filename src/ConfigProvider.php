<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent;

use Weaviate\WeaviateClient;
use Weaviate\Connection\ConnectionInterface;
use Weaviate\Auth\AuthInterface;

/**
 * Configuration provider for Weaviate Client Component.
 * 
 * Registers all services, factories, and aliases needed for Laminas/Mezzio integration.
 */
class ConfigProvider
{
    /**
     * Return configuration for this component.
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'weaviate' => $this->getWeaviateConfig(),
        ];
    }

    /**
     * Return dependency configuration for this component.
     */
    public function getDependencies(): array
    {
        return [
            'factories' => $this->getFactories(),
            'aliases' => $this->getAliases(),
            'abstract_factories' => $this->getAbstractFactories(),
        ];
    }

    /**
     * Return factory configuration.
     */
    public function getFactories(): array
    {
        return [
            // Core Weaviate services
            WeaviateClient::class => Factory\WeaviateClientFactory::class,
            ConnectionInterface::class => Factory\ConnectionFactory::class,
            AuthInterface::class => Factory\AuthFactory::class,

            // Named client factories - these will be handled by the abstract factory
            'weaviate.client.default' => Factory\WeaviateClientFactory::class,

            // Factory services themselves
            Factory\WeaviateClientFactory::class => Factory\WeaviateClientFactory::class,
            Factory\ConnectionFactory::class => Factory\ConnectionFactory::class,
            Factory\AuthFactory::class => Factory\AuthFactory::class,
            Factory\WeaviateClientAbstractFactory::class => Factory\WeaviateClientAbstractFactory::class,
        ];
    }

    /**
     * Return alias configuration.
     */
    public function getAliases(): array
    {
        return [
            // Convenient aliases
            'WeaviateClient' => WeaviateClient::class,
            'weaviate.client' => 'weaviate.client.default',
            
            // Factory aliases
            'weaviate.factory.client' => Factory\WeaviateClientFactory::class,
            'weaviate.factory.connection' => Factory\ConnectionFactory::class,
            'weaviate.factory.auth' => Factory\AuthFactory::class,
        ];
    }

    /**
     * Return abstract factory configuration.
     */
    public function getAbstractFactories(): array
    {
        return [
            Factory\WeaviateClientAbstractFactory::class,
        ];
    }

    /**
     * Return default Weaviate configuration.
     */
    public function getWeaviateConfig(): array
    {
        return [
            // Default configuration - can be overridden in application config
            'connection_method' => 'local',
            'connection' => [
                'host' => 'localhost',
                'port' => 8080,
                'secure' => false,
                'timeout' => 30,
            ],
            'enable_retry' => true,
            'max_retries' => 4,
            'additional_headers' => [],
            
            // Example clients configuration (commented out by default)
            /*
            'clients' => [
                'default' => [
                    'connection_method' => 'local',
                    'connection' => [
                        'host' => 'localhost',
                        'port' => 8080,
                    ],
                ],
                'rag' => [
                    'connection_method' => 'cloud',
                    'connection' => [
                        'cluster_url' => 'rag-cluster.weaviate.network',
                    ],
                    'auth' => [
                        'type' => 'api_key',
                        'api_key' => 'your-rag-api-key',
                    ],
                    'additional_headers' => [
                        'X-OpenAI-Api-Key' => 'your-openai-key',
                    ],
                ],
                'customer_data' => [
                    'connection_method' => 'cloud',
                    'connection' => [
                        'cluster_url' => 'customer-data.weaviate.network',
                    ],
                    'auth' => [
                        'type' => 'api_key',
                        'api_key' => 'your-customer-data-api-key',
                    ],
                    'enable_retry' => true,
                    'max_retries' => 5,
                ],
                'analytics' => [
                    'connection_method' => 'custom',
                    'connection' => [
                        'host' => 'analytics-server.internal',
                        'port' => 8080,
                        'secure' => false,
                    ],
                ],
            ],
            */
        ];
    }

    /**
     * Get all configured client names.
     */
    public function getConfiguredClientNames(array $config): array
    {
        $weaviateConfig = $config['weaviate'] ?? [];
        
        if (isset($weaviateConfig['clients'])) {
            return array_keys($weaviateConfig['clients']);
        }
        
        // If no clients configuration, return default
        return ['default'];
    }

    /**
     * Check if a specific client is configured.
     */
    public function hasClient(array $config, string $clientName): bool
    {
        $weaviateConfig = $config['weaviate'] ?? [];
        
        if (isset($weaviateConfig['clients'])) {
            return isset($weaviateConfig['clients'][$clientName]);
        }
        
        // If no clients configuration, only 'default' is available
        return $clientName === 'default';
    }

    /**
     * Get service names that can be created by this configuration.
     */
    public function getCreatableServiceNames(array $config): array
    {
        $clientNames = $this->getConfiguredClientNames($config);
        $serviceNames = [];
        
        // Add core services
        $serviceNames[] = WeaviateClient::class;
        $serviceNames[] = 'WeaviateClient';
        $serviceNames[] = 'weaviate.client';
        
        // Add named client services
        foreach ($clientNames as $clientName) {
            $serviceNames[] = "weaviate.client.{$clientName}";
        }
        
        return $serviceNames;
    }

    /**
     * Validate configuration structure.
     */
    public function validateConfiguration(array $config): array
    {
        $errors = [];
        $weaviateConfig = $config['weaviate'] ?? [];
        
        // Validate clients configuration if present
        if (isset($weaviateConfig['clients'])) {
            foreach ($weaviateConfig['clients'] as $clientName => $clientConfig) {
                if (!is_array($clientConfig)) {
                    $errors[] = "Client '{$clientName}' configuration must be an array";
                    continue;
                }
                
                // Validate connection method
                $connectionMethod = $clientConfig['connection_method'] ?? 'local';
                if (!in_array($connectionMethod, ['local', 'cloud', 'custom'], true)) {
                    $errors[] = "Client '{$clientName}' has invalid connection method: {$connectionMethod}";
                }
                
                // Validate cloud connection requirements
                if ($connectionMethod === 'cloud') {
                    if (!isset($clientConfig['connection']['cluster_url'])) {
                        $errors[] = "Client '{$clientName}' cloud connection requires cluster_url";
                    }
                    if (!isset($clientConfig['auth'])) {
                        $errors[] = "Client '{$clientName}' cloud connection requires auth configuration";
                    }
                }
                
                // Validate custom connection requirements
                if ($connectionMethod === 'custom') {
                    if (!isset($clientConfig['connection']['host'])) {
                        $errors[] = "Client '{$clientName}' custom connection requires host";
                    }
                }
            }
        }
        
        return $errors;
    }

    /**
     * Get configuration summary for debugging.
     */
    public function getConfigurationSummary(array $config): array
    {
        $weaviateConfig = $config['weaviate'] ?? [];
        $clientNames = $this->getConfiguredClientNames($config);
        
        return [
            'has_weaviate_config' => !empty($weaviateConfig),
            'has_clients_config' => isset($weaviateConfig['clients']),
            'client_count' => count($clientNames),
            'client_names' => $clientNames,
            'creatable_services' => $this->getCreatableServiceNames($config),
            'validation_errors' => $this->validateConfiguration($config),
        ];
    }
}
