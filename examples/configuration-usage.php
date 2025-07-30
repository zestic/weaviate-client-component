<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Zestic\WeaviateClientComponent\Configuration\WeaviateConfig;
use Zestic\WeaviateClientComponent\Configuration\ConnectionConfig;
use Zestic\WeaviateClientComponent\Configuration\AuthConfig;

// Example 1: Local connection
echo "=== Local Connection ===\n";
$localConfig = WeaviateConfig::fromArray([
    'connection_method' => 'local',
    'connection' => [
        'host' => 'localhost:8080',
    ],
]);

echo "Connection method: " . $localConfig->connectionMethod . "\n";
echo "Is local: " . ($localConfig->isLocalConnection() ? 'Yes' : 'No') . "\n";
echo "URL: " . $localConfig->connection->getUrl() . "\n\n";

// Example 2: Cloud connection with API key
echo "=== Cloud Connection ===\n";
$cloudConfig = WeaviateConfig::fromArray([
    'connection_method' => 'cloud',
    'connection' => [
        'cluster_url' => 'my-cluster.weaviate.network',
    ],
    'auth' => [
        'type' => 'api_key',
        'api_key' => 'your-wcd-api-key',
    ],
    'additional_headers' => [
        'X-OpenAI-Api-Key' => 'your-openai-key',
    ],
]);

echo "Connection method: " . $cloudConfig->connectionMethod . "\n";
echo "Is cloud: " . ($cloudConfig->isCloudConnection() ? 'Yes' : 'No') . "\n";
echo "Has auth: " . ($cloudConfig->hasAuth() ? 'Yes' : 'No') . "\n";
echo "Auth type: " . $cloudConfig->auth->type . "\n";
echo "URL: " . $cloudConfig->connection->getUrl() . "\n";
echo "Headers: " . json_encode($cloudConfig->getAllHeaders()) . "\n\n";

// Example 3: Custom connection with Bearer token
echo "=== Custom Connection ===\n";
$customConfig = WeaviateConfig::fromArray([
    'connection_method' => 'custom',
    'connection' => [
        'host' => 'my-server.com',
        'port' => 9200,
        'secure' => true,
        'timeout' => 60,
    ],
    'auth' => [
        'type' => 'bearer_token',
        'bearer_token' => 'your-bearer-token',
    ],
    'enable_retry' => true,
    'max_retries' => 5,
]);

echo "Connection method: " . $customConfig->connectionMethod . "\n";
echo "Is custom: " . ($customConfig->isCustomConnection() ? 'Yes' : 'No') . "\n";
echo "Host: " . $customConfig->connection->host . "\n";
echo "Port: " . $customConfig->connection->port . "\n";
echo "Secure: " . ($customConfig->connection->secure ? 'Yes' : 'No') . "\n";
echo "URL: " . $customConfig->connection->getUrl() . "\n";
echo "Retry enabled: " . ($customConfig->enableRetry ? 'Yes' : 'No') . "\n";
echo "Max retries: " . $customConfig->maxRetries . "\n\n";

// Example 4: OIDC authentication
echo "=== OIDC Authentication ===\n";
$oidcAuth = AuthConfig::fromArray([
    'type' => 'oidc',
    'client_id' => 'my-client-id',
    'client_secret' => 'my-client-secret',
    'scope' => 'read write',
    'additional_params' => [
        'audience' => 'weaviate-api',
    ],
]);

echo "Auth type: " . $oidcAuth->type . "\n";
echo "Is OIDC: " . ($oidcAuth->isOidc() ? 'Yes' : 'No') . "\n";
echo "Client ID: " . $oidcAuth->clientId . "\n";
echo "Scope: " . $oidcAuth->scope . "\n";
echo "Additional params: " . json_encode($oidcAuth->additionalParams) . "\n\n";

// Example 5: Converting to array for serialization
echo "=== Configuration Serialization ===\n";
$configArray = $cloudConfig->toArray();
echo "Serialized config: " . json_encode($configArray, JSON_PRETTY_PRINT) . "\n\n";

// Example 6: Error handling
echo "=== Error Handling ===\n";
try {
    // This will throw an exception - missing cluster_url for cloud connection
    WeaviateConfig::fromArray([
        'connection_method' => 'cloud',
        'auth' => [
            'type' => 'api_key',
            'api_key' => 'test-key',
        ],
    ]);
} catch (\Zestic\WeaviateClientComponent\Exception\ConfigurationException $e) {
    echo "Caught expected error: " . $e->getMessage() . "\n";
}

try {
    // This will throw an exception - invalid connection method
    WeaviateConfig::fromArray([
        'connection_method' => 'invalid',
    ]);
} catch (\Zestic\WeaviateClientComponent\Exception\ConfigurationException $e) {
    echo "Caught expected error: " . $e->getMessage() . "\n";
}

echo "\nAll examples completed successfully!\n";
