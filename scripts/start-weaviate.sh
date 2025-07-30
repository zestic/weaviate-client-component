#!/bin/bash

# Script to start Weaviate using Docker Compose and wait for it to be ready

set -e

WEAVIATE_URL="http://localhost:18080"
MAX_WAIT_TIME=120  # Maximum wait time in seconds
WAIT_INTERVAL=5    # Check interval in seconds

echo "🚀 Starting Weaviate using Docker Compose..."

# Start Weaviate in detached mode
docker compose up -d weaviate

echo "⏳ Waiting for Weaviate to be ready at $WEAVIATE_URL..."

# Function to check if Weaviate is ready
check_weaviate() {
    curl -s -f "$WEAVIATE_URL/v1/.well-known/ready" > /dev/null 2>&1
}

# Wait for Weaviate to be ready
elapsed=0
while ! check_weaviate; do
    if [ $elapsed -ge $MAX_WAIT_TIME ]; then
        echo "❌ Timeout: Weaviate did not become ready within $MAX_WAIT_TIME seconds"
        echo "📋 Docker Compose logs:"
        docker compose logs weaviate
        exit 1
    fi
    
    echo "   Still waiting... (${elapsed}s/${MAX_WAIT_TIME}s)"
    sleep $WAIT_INTERVAL
    elapsed=$((elapsed + WAIT_INTERVAL))
done

echo "✅ Weaviate is ready!"
echo "🔗 Weaviate URL: $WEAVIATE_URL"
echo "🏥 Health check: $WEAVIATE_URL/v1/.well-known/ready"
echo "📊 Meta endpoint: $WEAVIATE_URL/v1/meta"
