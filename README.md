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

## Requirements

- PHP 8.3 or higher
- Laminas ServiceManager 3.22+
- PSR-11 Container implementation

## Installation

Install via Composer:

```bash
composer require zestic/weaviate-client-component
```

## Quick Start

1. Copy the configuration template:
```bash
cp vendor/zestic/weaviate-client-component/config/weaviate.global.php.dist config/autoload/weaviate.local.php
```

2. Configure your Weaviate connection in `config/autoload/weaviate.local.php`

3. Register the ConfigProvider in your application configuration

4. Inject the WeaviateClient into your services

## Documentation

- [Installation Guide](docs/INSTALLATION.md)
- [Configuration Reference](docs/CONFIGURATION.md)
- [Usage Examples](docs/EXAMPLES.md)

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details on how to contribute to this project.

## License

This project is licensed under the Apache 2.0 License - see the [LICENSE](LICENSE) file for details.

## Support

- [GitHub Issues](https://github.com/zestic/weaviate-client-component/issues)
- [Documentation](docs/)

## Related Projects

- [zestic/weaviate-php-client](https://github.com/zestic/weaviate-php-client) - The core Weaviate PHP client
