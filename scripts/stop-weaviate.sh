#!/bin/bash

# Script to stop Weaviate Docker Compose services

set -e

echo "🛑 Stopping Weaviate..."

# Stop and remove containers
docker compose down

echo "✅ Weaviate stopped successfully!"

# Optionally remove volumes (uncomment if you want to clean up data)
# echo "🧹 Cleaning up volumes..."
# docker compose down -v
# echo "✅ Volumes cleaned up!"
