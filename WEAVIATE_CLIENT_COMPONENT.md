# Weaviate Client Component for Laminas

## Project Overview

This document outlines the plan to create `weaviate-client-component`, a Laminas-specific integration library that provides factories, ConfigProvider, and supporting classes to seamlessly integrate the `zestic/weaviate-php-client` library into Laminas/Mezzio applications.

## Project Details

- **Project Name**: `weaviate-client-component`
- **Package Name**: `zestic/weaviate-client-component`
- **PHP Version**: 8.3+
- **License**: Apache 2.0
- **Dependencies**:
  - `zestic/weaviate-php-client` (main dependency)
  - `laminas/laminas-servicemanager` (for factories and DI)
  - `psr/container` (PSR-11 container interface)
- **Dev Dependencies**:
  - `phpunit/phpunit` (testing framework)
  - `phpstan/phpstan` (static analysis)
  - `squizlabs/php_codesniffer` (coding standards)
  - `ergebnis/composer-normalize` (composer.json formatting)

## Architecture Overview

The component will follow modern Laminas/Mezzio conventions and provide:

1. **ConfigProvider** - Main configuration provider for service registration
2. **Factories** - Service factories for creating Weaviate client instances
3. **Configuration Classes** - Typed configuration objects with PHP 8.3+ features
4. **Documentation** - Comprehensive usage examples focused on modern patterns

## Directory Structure

```
weaviate-client-component/
├── .github/
│   └── workflows/
│       ├── tests.yml
│       └── lint.yml
├── composer.json
├── README.md
├── LICENSE
├── phpunit.xml
├── src/
│   ├── ConfigProvider.php
│   ├── Factory/
│   │   ├── WeaviateClientFactory.php
│   │   ├── WeaviateClientAbstractFactory.php
│   │   ├── ConnectionFactory.php
│   │   └── AuthFactory.php
│   ├── Configuration/
│   │   ├── WeaviateConfig.php
│   │   ├── ConnectionConfig.php
│   │   └── AuthConfig.php
│   └── Exception/
│       └── ConfigurationException.php
├── config/
│   └── weaviate.global.php.dist
├── test/
│   ├── ConfigProviderTest.php
│   ├── Integration/
│   │   ├── MultipleClientsIntegrationTest.php
│   │   ├── ConfigProviderIntegrationTest.php
│   │   ├── FactoryIntegrationTest.php
│   │   └── RealWeaviateConnectionTest.php
│   ├── Factory/
│   │   ├── WeaviateClientFactoryTest.php
│   │   ├── WeaviateClientAbstractFactoryTest.php
│   │   ├── ConnectionFactoryTest.php
│   │   └── AuthFactoryTest.php
│   └── Configuration/
│       ├── WeaviateConfigTest.php
│       ├── ConnectionConfigTest.php
│       └── AuthConfigTest.php
├── docker-compose.yml
└── docs/
    ├── INSTALLATION.md
    ├── CONFIGURATION.md
    └── EXAMPLES.md
```

## Core Components

### 1. ConfigProvider Class

The main configuration provider that registers all services:

```php
<?php
namespace Zestic\WeaviateClientComponent;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'weaviate' => $this->getWeaviateConfig(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'factories' => [
                \Weaviate\WeaviateClient::class => Factory\WeaviateClientFactory::class,
                \Weaviate\Connection\ConnectionInterface::class => Factory\ConnectionFactory::class,
                \Weaviate\Auth\AuthInterface::class => Factory\AuthFactory::class,

                // Named client factories
                'weaviate.client.default' => Factory\WeaviateClientFactory::class,
                'weaviate.client.rag' => [Factory\WeaviateClientFactory::class, 'rag'],
                'weaviate.client.analytics' => [Factory\WeaviateClientFactory::class, 'analytics'],
                'weaviate.client.customer_data' => [Factory\WeaviateClientFactory::class, 'customer_data'],
            ],
            'aliases' => [
                'WeaviateClient' => \Weaviate\WeaviateClient::class,
                'weaviate.client' => 'weaviate.client.default',
            ],
            'abstract_factories' => [
                Factory\WeaviateClientAbstractFactory::class,
            ],
        ];
    }
}
```

### 2. WeaviateClientFactory

Factory for creating WeaviateClient instances with support for multiple named connections:

```php
<?php
namespace Zestic\WeaviateClientComponent\Factory;

use Psr\Container\ContainerInterface;
use Weaviate\WeaviateClient;
use Weaviate\Connection\ConnectionInterface;
use Weaviate\Auth\AuthInterface;

class WeaviateClientFactory
{
    public function __invoke(ContainerInterface $container): WeaviateClient
    {
        // Return the default client
        return $this->createClient($container, 'default');
    }

    public function createClient(ContainerInterface $container, string $name = 'default'): WeaviateClient
    {
        $config = $container->get('config')['weaviate'] ?? [];

        // Support for multiple named clients
        if (isset($config['clients'][$name])) {
            $clientConfig = $config['clients'][$name];
        } elseif ($name === 'default' && !isset($config['clients'])) {
            // Backward compatibility: use root config as default
            $clientConfig = $config;
        } else {
            throw new \InvalidArgumentException("Weaviate client '{$name}' not configured");
        }

        // Support for different connection methods
        if (isset($clientConfig['connection_method'])) {
            return match($clientConfig['connection_method']) {
                'local' => $this->createLocalConnection($clientConfig),
                'cloud' => $this->createCloudConnection($clientConfig),
                'custom' => $this->createCustomConnection($clientConfig),
                default => throw new \InvalidArgumentException('Invalid connection method')
            };
        }

        // Manual connection using DI (for advanced users)
        $connection = $container->get(ConnectionInterface::class);
        $auth = $container->has(AuthInterface::class)
            ? $container->get(AuthInterface::class)
            : null;

        return new WeaviateClient($connection, $auth);
    }

    private function createLocalConnection(array $config): WeaviateClient
    {
        $host = $config['connection']['host'] ?? 'localhost:8080';
        $auth = isset($config['auth']) ? $this->createAuth($config['auth']) : null;

        return WeaviateClient::connectToLocal($host, $auth);
    }

    private function createCloudConnection(array $config): WeaviateClient
    {
        $clusterUrl = $config['connection']['cluster_url'] ?? throw new \InvalidArgumentException('cluster_url is required for cloud connections');
        $auth = $this->createAuth($config['auth'] ?? throw new \InvalidArgumentException('auth is required for cloud connections'));

        return WeaviateClient::connectToWeaviateCloud($clusterUrl, $auth);
    }

    private function createCustomConnection(array $config): WeaviateClient
    {
        $connection = $config['connection'] ?? [];
        $host = $connection['host'] ?? throw new \InvalidArgumentException('host is required for custom connections');
        $port = $connection['port'] ?? 8080;
        $secure = $connection['secure'] ?? false;
        $auth = isset($config['auth']) ? $this->createAuth($config['auth']) : null;
        $headers = $config['additional_headers'] ?? [];

        return WeaviateClient::connectToCustom($host, $port, $secure, $auth, $headers);
    }

    private function createAuth(array $authConfig): AuthInterface
    {
        $type = $authConfig['type'] ?? 'api_key';

        return match($type) {
            'api_key' => new ApiKey($authConfig['api_key'] ?? throw new \InvalidArgumentException('api_key is required')),
            default => throw new \InvalidArgumentException("Unsupported auth type: {$type}")
        };
    }
}
```

### 3. WeaviateClientAbstractFactory

Abstract factory for dynamic client creation:

```php
<?php
namespace Zestic\WeaviateClientComponent\Factory;

use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Psr\Container\ContainerInterface;
use Weaviate\WeaviateClient;

class WeaviateClientAbstractFactory implements AbstractFactoryInterface
{
    public function canCreate(ContainerInterface $container, string $requestedName): bool
    {
        // Handle requests like 'weaviate.client.{name}'
        if (!str_starts_with($requestedName, 'weaviate.client.')) {
            return false;
        }

        $clientName = substr($requestedName, 16); // Remove 'weaviate.client.' prefix
        $config = $container->get('config')['weaviate'] ?? [];

        return isset($config['clients'][$clientName]);
    }

    public function __invoke(ContainerInterface $container, string $requestedName, ?array $options = null): WeaviateClient
    {
        $clientName = substr($requestedName, 16); // Remove 'weaviate.client.' prefix
        $factory = $container->get(WeaviateClientFactory::class);

        return $factory->createClient($container, $clientName);
    }
}
```

### 4. Configuration Classes

Modern PHP 8.3+ typed configuration objects following existing codebase patterns:

```php
<?php
namespace Zestic\WeaviateClientComponent\Configuration;

readonly class WeaviateConfig
{
    public function __construct(
        public string $connectionMethod = 'local',
        public ConnectionConfig $connection = new ConnectionConfig(),
        public ?AuthConfig $auth = null,
        public array $additionalHeaders = [],
        public bool $enableRetry = true,
        public int $maxRetries = 4
    ) {}

    public static function fromArray(array $config): self
    {
        $connectionMethod = $config['connection_method'] ?? 'local';

        // Validate connection method
        if (!in_array($connectionMethod, ['local', 'cloud', 'custom'], true)) {
            throw new \InvalidArgumentException(
                "Invalid connection method: {$connectionMethod}. Must be 'local', 'cloud', or 'custom'"
            );
        }

        return new self(
            connectionMethod: $connectionMethod,
            connection: ConnectionConfig::fromArray($config['connection'] ?? []),
            auth: isset($config['auth']) ? AuthConfig::fromArray($config['auth']) : null,
            additionalHeaders: $config['additional_headers'] ?? [],
            enableRetry: $config['enable_retry'] ?? true,
            maxRetries: $config['max_retries'] ?? 4
        );
    }
}
```

## Configuration Examples

### Basic Local Configuration

```php
// config/autoload/weaviate.local.php
return [
    'weaviate' => [
        'connection_method' => 'local',
        'connection' => [
            'host' => 'localhost:8080',
        ],
    ],
];
```

### Weaviate Cloud Configuration

```php
// config/autoload/weaviate.local.php
return [
    'weaviate' => [
        'connection_method' => 'cloud',
        'connection' => [
            'cluster_url' => 'my-cluster.weaviate.network',
        ],
        'auth' => [
            'type' => 'api_key',
            'api_key' => 'your-wcd-api-key',
        ],
    ],
];
```

### Custom Configuration with Headers

```php
// config/autoload/weaviate.local.php
return [
    'weaviate' => [
        'connection_method' => 'custom',
        'connection' => [
            'host' => 'my-server.com',
            'port' => 9200,
            'secure' => true,
        ],
        'auth' => [
            'type' => 'api_key',
            'api_key' => 'your-api-key',
        ],
        'additional_headers' => [
            'X-OpenAI-Api-Key' => 'your-openai-key',
            'X-Custom-Header' => 'custom-value',
        ],
        'enable_retry' => true,
        'max_retries' => 3,
    ],
];
```

## Implementation Plan

### Phase 1: Core Structure (Week 1)
- [ ] Set up project structure and composer.json
- [ ] Create GitHub repository with proper README and badges
- [ ] Set up GitHub Actions workflows (tests.yml, lint.yml)
- [ ] Configure PHPUnit, PHPStan, and coding standards
- [ ] Create basic ConfigProvider class
- [ ] Implement WeaviateClientFactory with local connection support
- [ ] Create basic configuration classes

### Phase 2: Connection Factories (Week 2)
- [ ] Implement ConnectionFactory for manual DI
- [ ] Implement AuthFactory for authentication DI
- [ ] Add support for all three connection methods (local, cloud, custom)
- [ ] Create comprehensive configuration validation
- [ ] Add exception handling for configuration errors
- [ ] Set up Docker Compose for integration testing

### Phase 3: Advanced Features (Week 3)
- [ ] Implement configuration validation and error reporting
- [ ] Add support for multiple named Weaviate clients
- [ ] Create configuration builders and helpers
- [ ] Add comprehensive logging integration
- [ ] Implement health check endpoints for monitoring

### Phase 4: Documentation & Testing (Week 4)
- [ ] Write comprehensive unit tests (90%+ coverage)
- [ ] Create integration tests with real Weaviate instances
- [ ] Test multiple client scenarios with real databases
- [ ] Validate configuration edge cases with real connections
- [ ] Write detailed documentation and examples
- [ ] Create migration guide from manual setup
- [ ] Performance testing and optimization

## Usage Examples

### Basic Usage in Controller

```php
<?php
namespace App\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Weaviate\WeaviateClient;

class ArticleHandler implements RequestHandlerInterface
{
    public function __construct(
        private WeaviateClient $weaviateClient
    ) {}
    
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $collections = $this->weaviateClient->collections();
        
        if (!$collections->exists('Article')) {
            $collections->create('Article', [
                'properties' => [
                    ['name' => 'title', 'dataType' => ['text']],
                    ['name' => 'content', 'dataType' => ['text']],
                ]
            ]);
        }
        
        $article = $collections->get('Article');
        $result = $article->data()->create([
            'title' => 'My Article',
            'content' => 'Article content...'
        ]);
        
        return new JsonResponse(['id' => $result['id']]);
    }
}
```

### Multiple Named Clients

Perfect for your use case with RAG and client data separation:

```php
// config/autoload/weaviate.local.php
return [
    'weaviate' => [
        'clients' => [
            'default' => [
                'connection_method' => 'local',
                'connection' => ['host' => 'localhost:8080'],
            ],
            'rag' => [
                'connection_method' => 'cloud',
                'connection' => ['cluster_url' => 'rag-cluster.weaviate.network'],
                'auth' => ['type' => 'api_key', 'api_key' => 'rag-api-key'],
                'additional_headers' => [
                    'X-OpenAI-Api-Key' => 'your-openai-key', // For vectorization
                ],
            ],
            'customer_data' => [
                'connection_method' => 'cloud',
                'connection' => ['cluster_url' => 'customer-data.weaviate.network'],
                'auth' => ['type' => 'api_key', 'api_key' => 'customer-data-key'],
                'enable_retry' => true,
                'max_retries' => 5, // Higher retries for critical customer data
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
    ],
];

// Usage in your application
class DocumentService
{
    public function __construct(
        #[Inject('weaviate.client.rag')]
        private WeaviateClient $ragClient,

        #[Inject('weaviate.client.customer_data')]
        private WeaviateClient $customerClient
    ) {}

    public function searchDocuments(string $query): array
    {
        // Use RAG client for document search
        return $this->ragClient->collections()
            ->get('Documents')
            ->data()
            ->search($query);
    }

    public function storeCustomerData(array $data): string
    {
        // Use customer data client for sensitive data
        return $this->customerClient->collections()
            ->get('Customers')
            ->data()
            ->create($data);
    }
}

// Or retrieve clients manually
$ragClient = $container->get('weaviate.client.rag');
$customerClient = $container->get('weaviate.client.customer_data');
$analyticsClient = $container->get('weaviate.client.analytics');
```

## CI/CD and Quality Assurance

### GitHub Actions Workflows

#### tests.yml
```yaml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  unit-tests:
    name: Unit Tests
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['8.3', '8.4']
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: json, curl
          coverage: xdebug
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      - name: Run unit tests
        run: composer test-unit

  integration-tests:
    name: Integration Tests
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['8.3', '8.4']
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: json, curl
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      - name: Start Weaviate
        run: docker compose up -d weaviate
      - name: Run integration tests
        run: composer test-integration
        env:
          WEAVIATE_URL: http://localhost:18080

  coverage:
    name: Code Coverage
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: json, curl
          coverage: xdebug
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      - name: Start Weaviate
        run: docker compose up -d weaviate
      - name: Run tests with coverage
        run: composer test-coverage
        env:
          WEAVIATE_URL: http://localhost:18080
      - name: Upload coverage reports to Codecov
        uses: codecov/codecov-action@v5
        with:
          token: ${{ secrets.CODECOV_TOKEN }}
          slug: zestic/weaviate-client-component
```

#### lint.yml
```yaml
name: Lint

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  phpstan:
    name: PHPStan Static Analysis
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['8.3', '8.4']
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: json, curl
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      - name: Run PHPStan
        run: composer phpstan

  coding-standards:
    name: Coding Standards
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: json, curl
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      - name: Check coding standards
        run: composer cs-check

  security:
    name: Security Analysis
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: json, curl
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      - name: Run security audit
        run: composer security-audit

  composer-normalize:
    name: Composer Normalize
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      - name: Check composer.json format
        run: composer normalize-check
```

### README Structure

The component README should include these badges and sections:

```markdown
# Weaviate Client Component for Laminas

[![Tests](https://github.com/zestic/weaviate-client-component/actions/workflows/tests.yml/badge.svg)](https://github.com/zestic/weaviate-client-component/actions/workflows/tests.yml)
[![Lint](https://github.com/zestic/weaviate-client-component/actions/workflows/lint.yml/badge.svg)](https://github.com/zestic/weaviate-client-component/actions/workflows/lint.yml)
[![codecov](https://codecov.io/gh/zestic/weaviate-client-component/graph/badge.svg)](https://codecov.io/gh/zestic/weaviate-client-component)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-Apache%202.0-blue.svg)](LICENSE)

A modern Laminas/Mezzio integration component for the Weaviate PHP client with dependency injection, multiple client support, and comprehensive configuration.

## Features

- 🚀 **Modern PHP 8.3+** - Leverages latest PHP features
- 🔧 **ConfigProvider Integration** - Seamless Laminas/Mezzio setup
- 🏭 **Service Factories** - Full dependency injection support
- 🔀 **Multiple Clients** - Support for multiple named Weaviate connections
- ⚙️ **Type-Safe Configuration** - Readonly configuration classes
- 🧪 **Comprehensive Testing** - Unit and integration tests
- 📚 **Complete Documentation** - Installation, configuration, and examples
```

## Testing Strategy

### 1. Unit Tests
- **Factory Tests**: Test factory logic in isolation with mocked dependencies
- **Configuration Tests**: Validate configuration parsing and validation
- **ConfigProvider Tests**: Ensure proper service registration

### 2. Integration Tests
Critical for validating real-world usage:

#### **MultipleClientsIntegrationTest.php**
```php
<?php
namespace Zestic\WeaviateClientComponent\Test\Integration;

use PHPUnit\Framework\TestCase;
use Laminas\ServiceManager\ServiceManager;
use Weaviate\WeaviateClient;

class MultipleClientsIntegrationTest extends TestCase
{
    private ServiceManager $container;

    protected function setUp(): void
    {
        $config = [
            'weaviate' => [
                'clients' => [
                    'default' => [
                        'connection_method' => 'local',
                        'connection' => ['host' => 'localhost:18080'],
                    ],
                    'rag' => [
                        'connection_method' => 'local',
                        'connection' => ['host' => 'localhost:18080'],
                        'additional_headers' => ['X-Test-Client' => 'rag'],
                    ],
                    'customer_data' => [
                        'connection_method' => 'local',
                        'connection' => ['host' => 'localhost:18080'],
                        'additional_headers' => ['X-Test-Client' => 'customer'],
                    ],
                ],
            ],
        ];

        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        $this->container = new ServiceManager(array_merge($dependencies, [
            'services' => ['config' => $config]
        ]));
    }

    public function testMultipleClientsAreDistinct(): void
    {
        $defaultClient = $this->container->get('weaviate.client.default');
        $ragClient = $this->container->get('weaviate.client.rag');
        $customerClient = $this->container->get('weaviate.client.customer_data');

        $this->assertInstanceOf(WeaviateClient::class, $defaultClient);
        $this->assertInstanceOf(WeaviateClient::class, $ragClient);
        $this->assertInstanceOf(WeaviateClient::class, $customerClient);

        // Verify they are different instances
        $this->assertNotSame($defaultClient, $ragClient);
        $this->assertNotSame($defaultClient, $customerClient);
        $this->assertNotSame($ragClient, $customerClient);
    }

    public function testClientsCanConnectToWeaviate(): void
    {
        $ragClient = $this->container->get('weaviate.client.rag');
        $customerClient = $this->container->get('weaviate.client.customer_data');

        // Test basic connectivity
        $ragSchema = $ragClient->schema()->get();
        $customerSchema = $customerClient->schema()->get();

        $this->assertIsArray($ragSchema);
        $this->assertIsArray($customerSchema);
        $this->assertArrayHasKey('classes', $ragSchema);
        $this->assertArrayHasKey('classes', $customerSchema);
    }

    public function testClientsCanCreateCollections(): void
    {
        $ragClient = $this->container->get('weaviate.client.rag');
        $customerClient = $this->container->get('weaviate.client.customer_data');

        // Create collections with different names to avoid conflicts
        $ragCollectionName = 'TestRAGCollection_' . uniqid();
        $customerCollectionName = 'TestCustomerCollection_' . uniqid();

        try {
            // Create collections
            $ragClient->collections()->create($ragCollectionName, [
                'properties' => [
                    ['name' => 'content', 'dataType' => ['text']],
                    ['name' => 'embedding', 'dataType' => ['number[]']],
                ]
            ]);

            $customerClient->collections()->create($customerCollectionName, [
                'properties' => [
                    ['name' => 'name', 'dataType' => ['text']],
                    ['name' => 'email', 'dataType' => ['text']],
                ]
            ]);

            // Verify collections exist
            $this->assertTrue($ragClient->collections()->exists($ragCollectionName));
            $this->assertTrue($customerClient->collections()->exists($customerCollectionName));

            // Verify isolation - RAG client shouldn't see customer collection
            $this->assertFalse($ragClient->collections()->exists($customerCollectionName));
            $this->assertFalse($customerClient->collections()->exists($ragCollectionName));

        } finally {
            // Cleanup
            if ($ragClient->collections()->exists($ragCollectionName)) {
                $ragClient->schema()->delete($ragCollectionName);
            }
            if ($customerClient->collections()->exists($customerCollectionName)) {
                $customerClient->schema()->delete($customerCollectionName);
            }
        }
    }
}
```

#### **RealWeaviateConnectionTest.php**
```php
<?php
namespace Zestic\WeaviateClientComponent\Test\Integration;

use PHPUnit\Framework\TestCase;
use Laminas\ServiceManager\ServiceManager;
use Weaviate\WeaviateClient;

class RealWeaviateConnectionTest extends TestCase
{
    private ServiceManager $container;

    protected function setUp(): void
    {
        $config = [
            'weaviate' => [
                'connection_method' => 'local',
                'connection' => ['host' => 'localhost:18080'],
                'enable_retry' => true,
                'max_retries' => 3,
            ],
        ];

        $configProvider = new ConfigProvider();
        $dependencies = $configProvider->getDependencies();

        $this->container = new ServiceManager(array_merge($dependencies, [
            'services' => ['config' => $config]
        ]));
    }

    public function testFactoryCreatesWorkingClient(): void
    {
        $client = $this->container->get(WeaviateClient::class);

        $this->assertInstanceOf(WeaviateClient::class, $client);

        // Test actual connection
        $schema = $client->schema()->get();
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('classes', $schema);
    }

    public function testClientCanPerformCRUDOperations(): void
    {
        $client = $this->container->get(WeaviateClient::class);
        $collectionName = 'TestIntegrationCollection_' . uniqid();

        try {
            // Create collection
            $client->collections()->create($collectionName, [
                'properties' => [
                    ['name' => 'title', 'dataType' => ['text']],
                    ['name' => 'content', 'dataType' => ['text']],
                ]
            ]);

            $this->assertTrue($client->collections()->exists($collectionName));

            // Create data
            $collection = $client->collections()->get($collectionName);
            $result = $collection->data()->create([
                'title' => 'Test Article',
                'content' => 'This is test content'
            ]);

            $this->assertIsArray($result);
            $this->assertArrayHasKey('id', $result);

            // Read data
            $retrieved = $collection->data()->get($result['id']);
            $this->assertEquals('Test Article', $retrieved['title']);
            $this->assertEquals('This is test content', $retrieved['content']);

        } finally {
            // Cleanup
            if ($client->collections()->exists($collectionName)) {
                $client->schema()->delete($collectionName);
            }
        }
    }
}
```

### 3. Docker Compose for Testing
```yaml
# docker-compose.yml
version: '3.8'
services:
  weaviate:
    image: cr.weaviate.io/semitechnologies/weaviate:1.31.0
    ports:
      - "18080:8080"
      - "50051:50051"
    environment:
      QUERY_DEFAULTS_LIMIT: 25
      AUTHENTICATION_ANONYMOUS_ACCESS_ENABLED: 'true'
      PERSISTENCE_DATA_PATH: '/var/lib/weaviate'
      DEFAULT_VECTORIZER_MODULE: 'none'
      ENABLE_MODULES: ''
      CLUSTER_HOSTNAME: 'node1'
    volumes:
      - weaviate_data:/var/lib/weaviate

volumes:
  weaviate_data:
```

### 4. Quality Gates
- **PHPStan Level 8** - Maximum static analysis
- **PSR-12 Coding Standards** - Consistent code formatting
- **Security Audits** - Dependency vulnerability scanning
- **Code Coverage >90%** - High test coverage including integration tests

## Documentation Plan

1. **Installation Guide**: Step-by-step setup instructions
2. **Configuration Reference**: Complete configuration options
3. **Usage Examples**: Common patterns and use cases
4. **Migration Guide**: Moving from manual client setup
5. **Troubleshooting**: Common issues and solutions

## Modern PHP 8.3+ Features We'll Leverage

1. **Readonly Classes**: Immutable configuration objects
2. **Enums**: Type-safe connection methods and auth types
3. **Named Arguments**: Clean factory method calls
4. **Union Types**: Flexible configuration input handling
5. **Attributes**: Potential for configuration validation
6. **Match Expressions**: Clean factory logic

## Additional Considerations

### 1. Composer Scripts
The component should include the same composer scripts as the main library:

```json
{
    "scripts": {
        "cs-check": "phpcs src tests --standard=PSR12",
        "cs-fix": "phpcbf src tests --standard=PSR12",
        "phpstan": "phpstan analyse src tests --level=8",
        "security-audit": "composer audit --format=table",
        "test": "phpunit",
        "test-coverage": "phpunit --coverage-html coverage",
        "test-unit": "phpunit tests/Unit",
        "test-integration": "phpunit tests/Integration",
        "normalize": "@composer normalize",
        "normalize-check": "@composer normalize --dry-run"
    }
}
```

### 2. PHPStan Configuration
Create `phpstan.neon` with the same level 8 strictness:

```neon
parameters:
    level: 8
    paths:
        - src
        - tests
    ignoreErrors: []
```

### 3. PHPUnit Configuration
Create `phpunit.xml` with proper test suites:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/11.0/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
    <coverage>
        <report>
            <html outputDirectory="coverage"/>
            <text outputFile="coverage/coverage.txt"/>
            <clover outputFile="coverage/clover.xml"/>
        </report>
    </coverage>
</phpunit>
```

### 4. Environment Variables for Testing
Support environment-based configuration for CI/CD:

```php
// In factory methods, support environment overrides
$host = $config['connection']['host'] ?? $_ENV['WEAVIATE_URL'] ?? 'localhost:8080';
$apiKey = $config['auth']['api_key'] ?? $_ENV['WEAVIATE_API_KEY'] ?? null;
```

### 5. Logging Integration
Optional PSR-3 logger support:

```php
class WeaviateClientFactory
{
    public function __construct(
        private ?LoggerInterface $logger = null
    ) {}

    private function createLocalConnection(array $config): WeaviateClient
    {
        $this->logger?->info('Creating local Weaviate connection', ['host' => $host]);
        // ... connection logic
    }
}
```

## Questions for Clarification

1. ✅ **Multiple named clients** - Already addressed in the plan
2. Do you want integration with Laminas logging components (PSR-3)?
3. Should we provide console commands for schema management?
4. Do you need support for configuration caching/optimization?
5. Should we include health check endpoints for monitoring?
6. Do you want support for environment variable overrides in configuration?

## Summary

This comprehensive plan provides:

- ✅ **Modern PHP 8.3+ architecture** with readonly classes and type safety
- ✅ **Multiple client support** for RAG/customer data separation
- ✅ **Comprehensive testing** with unit and integration tests
- ✅ **CI/CD workflows** matching the main library's quality standards
- ✅ **Complete documentation** and usage examples
- ✅ **Flexible configuration** supporting all connection methods
- ✅ **Quality gates** with PHPStan level 8, PSR-12, and security audits

The component will provide a seamless, production-ready integration between the Weaviate PHP client and Laminas/Mezzio applications, following modern PHP best practices and maintaining the same high quality standards as the main library.
