# Test Scripts

This directory contains scripts to help run integration tests with Weaviate.

## Scripts

### `start-weaviate.sh`
Starts a Weaviate instance using Docker Compose and waits for it to be ready.

```bash
./scripts/start-weaviate.sh
```

### `stop-weaviate.sh`
Stops the Weaviate Docker Compose services.

```bash
./scripts/stop-weaviate.sh
```

### `test-with-weaviate.sh`
Runs integration tests with Weaviate. This script:
1. Starts Weaviate
2. Waits for it to be ready
3. Runs integration tests
4. Runs all tests (unit + integration)
5. Cleans up by stopping Weaviate

```bash
./scripts/test-with-weaviate.sh
```

## Composer Scripts

You can also use these convenient composer commands:

```bash
# Start Weaviate
composer weaviate:start

# Stop Weaviate
composer weaviate:stop

# Run all tests with Weaviate (recommended)
composer test:with-weaviate
# or
composer test:full

# Run only integration tests (requires Weaviate to be running)
composer test-integration
```

## Requirements

- Docker and Docker Compose
- curl (for health checks)

## Weaviate Configuration

The Docker Compose setup uses:
- **Port**: 18080 (mapped from container port 8080)
- **URL**: http://localhost:18080
- **Anonymous access**: Enabled (no authentication required)
- **Modules**: None (minimal setup for testing)

## Troubleshooting

If tests fail to connect to Weaviate:

1. Check if Docker is running: `docker --version`
2. Check if Weaviate is running: `docker compose ps`
3. Check Weaviate logs: `docker compose logs weaviate`
4. Test Weaviate manually: `curl http://localhost:18080/v1/.well-known/ready`

To clean up completely (including data):
```bash
docker compose down -v
```
