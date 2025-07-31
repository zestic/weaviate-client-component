# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2025-01-31

### Added

#### Core Architecture
- **ConfigProvider** - Main configuration provider for Laminas/Mezzio integration
- **WeaviateClientFactory** - Factory for creating WeaviateClient instances with multiple connection methods
- **WeaviateClientAbstractFactory** - Abstract factory for dynamic client creation
- **ConnectionFactory** - Factory for creating connection objects
- **AuthFactory** - Factory for authentication objects

#### Configuration System
- **WeaviateConfig** - Main configuration class with validation
- **ConnectionConfig** - Connection-specific configuration with support for local, cloud, and custom connections
- **AuthConfig** - Authentication configuration supporting API key, bearer token, and OIDC
- **ConfigurationException** - Comprehensive exception handling for configuration errors

#### Multiple Client Support
- Support for multiple named Weaviate clients (e.g., 'rag', 'customer_data', 'analytics')
- Client-specific configuration with inheritance from global settings
- Abstract factory pattern for dynamic client creation

#### Connection Methods
- **Local Connection** - Connect to local Weaviate instances
- **Cloud Connection** - Connect to Weaviate Cloud Service (WCS)
- **Custom Connection** - Connect to custom Weaviate deployments with full configuration control

#### Testing Suite
- **175 total tests** (152 unit tests + 23 integration tests)
- **434 assertions** with comprehensive coverage
- Unit tests for all configuration and factory classes
- Integration tests with real Weaviate instances
- Docker Compose setup for testing environment

#### Quality Assurance
- **PHPStan Level 8** - Maximum static analysis
- **PSR-12 Coding Standards** - Consistent code formatting
- **GitHub Actions CI/CD** - Automated testing and linting
- **Security Audits** - Dependency vulnerability scanning
- **Composer Normalize** - Consistent composer.json formatting

#### Documentation
- Comprehensive README with badges and quick start guide
- Configuration examples for all connection methods
- Usage examples for multiple client scenarios
- Docker setup and testing scripts
- API documentation and examples

#### Modern PHP Features
- **PHP 8.3+ requirement** - Leverages latest PHP features
- **Readonly classes** - Immutable configuration objects
- **Named arguments** - Clean factory method calls
- **Match expressions** - Type-safe factory logic
- **Union types** - Flexible configuration handling

### Technical Details

#### Dependencies
- `php: ^8.3` - Modern PHP version requirement
- `zestic/weaviate-php-client: ^0.3.0` - Core Weaviate client
- `laminas/laminas-servicemanager: ^3.22` - Dependency injection
- `psr/container: ^1.0` - PSR-11 container interface

#### Development Dependencies
- `phpunit/phpunit: ^10.0` - Testing framework
- `phpstan/phpstan: ^1.10` - Static analysis
- `squizlabs/php_codesniffer: ^3.8` - Coding standards
- `ergebnis/composer-normalize: ^2.42` - Composer formatting

#### Scripts and Tools
- Automated Weaviate Docker management scripts
- Comprehensive test suites (unit, integration, coverage)
- Code quality tools (PHPStan, PHPCS, security audit)
- CI/CD workflows for GitHub Actions

### Configuration Examples

#### Basic Local Connection
```php
return [
    'weaviate' => [
        'connection_method' => 'local',
        'connection' => [
            'host' => 'localhost:8080',
        ],
    ],
];
```

#### Multiple Named Clients
```php
return [
    'weaviate' => [
        'clients' => [
            'rag' => [
                'connection_method' => 'cloud',
                'connection' => ['cluster_url' => 'rag-cluster.weaviate.network'],
                'auth' => ['type' => 'api_key', 'api_key' => 'your-rag-key'],
            ],
            'customer_data' => [
                'connection_method' => 'cloud',
                'connection' => ['cluster_url' => 'customer.weaviate.network'],
                'auth' => ['type' => 'api_key', 'api_key' => 'your-customer-key'],
            ],
        ],
    ],
];
```

### Usage Examples

#### Basic Dependency Injection
```php
class DocumentService
{
    public function __construct(
        private WeaviateClient $weaviateClient
    ) {}
}
```

#### Multiple Named Clients
```php
class DocumentService
{
    public function __construct(
        #[Inject('weaviate.client.rag')]
        private WeaviateClient $ragClient,
        
        #[Inject('weaviate.client.customer_data')]
        private WeaviateClient $customerClient
    ) {}
}
```

### Breaking Changes
- None (initial release)

### Deprecated
- None (initial release)

### Removed
- None (initial release)

### Fixed
- None (initial release)

### Security
- Comprehensive input validation for all configuration options
- Secure handling of API keys and authentication tokens
- Protection against configuration injection attacks

---

## Release Notes

This is the initial release of the Weaviate Client Component for Laminas/Mezzio. The component provides a complete integration solution for using Weaviate vector database in Laminas and Mezzio applications with full dependency injection support, multiple client configurations, and comprehensive testing.

The component is production-ready and follows modern PHP best practices with extensive test coverage and quality assurance measures.

### Next Steps

Future releases will focus on:
- Additional authentication methods
- Enhanced logging integration
- Performance optimizations
- Extended documentation
- Migration tools and guides

[0.1.0]: https://github.com/zestic/weaviate-client-component/releases/tag/0.1.0
