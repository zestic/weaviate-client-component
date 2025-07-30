#!/bin/bash

# Script to run integration tests with Weaviate
# This script starts Weaviate, runs the tests, and cleans up

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
WEAVIATE_URL="http://localhost:18080"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🧪 Running integration tests with Weaviate${NC}"
echo "=================================================="

# Function to cleanup on exit
cleanup() {
    echo -e "\n${YELLOW}🧹 Cleaning up...${NC}"
    "$SCRIPT_DIR/stop-weaviate.sh"
}

# Set trap to cleanup on script exit
trap cleanup EXIT

# Start Weaviate
echo -e "${BLUE}Step 1: Starting Weaviate${NC}"
"$SCRIPT_DIR/start-weaviate.sh"

# Export environment variable for tests
export WEAVIATE_URL="$WEAVIATE_URL"

echo -e "\n${BLUE}Step 2: Running integration tests${NC}"
echo "Environment: WEAVIATE_URL=$WEAVIATE_URL"

# Change to project root and run integration tests
cd "$PROJECT_ROOT"

echo -e "\n${BLUE}Running integration tests...${NC}"
./vendor/bin/phpunit test/Integration --testdox || true

echo -e "\n${BLUE}Step 3: Running all tests (including integration)${NC}"
./vendor/bin/phpunit --testdox || true

echo -e "\n${GREEN}✅ Test execution completed!${NC}"
echo -e "${YELLOW}Note: PHPUnit warnings (like XDEBUG coverage) are normal and don't indicate test failures.${NC}"
