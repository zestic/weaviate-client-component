<?php

declare(strict_types=1);

namespace Zestic\WeaviateClientComponent\Factory;

use Psr\Container\ContainerInterface;
use Weaviate\WeaviateClient;
use Zestic\WeaviateClientComponent\Exception\ConfigurationException;

class ClientFactory
{
    public function __invoke(ContainerInterface $container): WeaviateClient
    {
        $config = $container->get('config')['weaviate'] ?? [];
        $factoryClass = $config['factory_class'] ?? WeaviateClientFactory::class;

        if (!class_exists($factoryClass)) {
            throw new ConfigurationException("Factory class '{$factoryClass}' not found.");
        }

        $factory = new $factoryClass();

        if (!is_callable($factory)) {
            throw new ConfigurationException("Factory class '{$factoryClass}' is not invokable.");
        }

        return $factory($container);
    }
}
