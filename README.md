# CubePath PHP SDK

Official PHP SDK for the [CubePath](https://cubepath.com) Cloud API.

[![CI](https://github.com/CubePathInc/cubepath-php-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/CubePathInc/cubepath-php-sdk/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/cubepath/cubepath-php-sdk)](https://packagist.org/packages/cubepath/cubepath-php-sdk)

## Installation

```bash
composer require cubepath/cubepath-php-sdk
```

## Quick Start

```php
use Cubepath\CubepathClient;

$client = new CubepathClient('your-api-key');

// List all projects
$projects = $client->projects()->list();
```

## Authentication

All API requests require a Bearer token. Pass your API key when creating the client:

```php
$client = new CubepathClient('your-api-key');
```

## Configuration

| Option | Default | Description |
|--------|---------|-------------|
| `base_url` | `https://api.cubepath.com` | API base URL |
| `ai_gateway_base_url` | `https://ai-gateway.cubepath.com` | AI Gateway base URL |
| `user_agent` | `cubepath-sdk-php/0.2.1` | Custom User-Agent header |
| `max_retries` | `3` | Maximum retry attempts on 429/5xx |
| `retry_wait_min` | `1.0` | Minimum retry wait (seconds) |
| `retry_wait_max` | `30.0` | Maximum retry wait (seconds) |
| `rate_limit_per_sec` | `10` | Max requests per second |
| `timeout` | `30` | Request timeout (seconds) |
| `http_client` | auto | Custom Guzzle HTTP client |

```php
$client = new CubepathClient('your-api-key', [
    'max_retries' => 5,
    'timeout' => 60,
]);
```

The client automatically retries on `429` (rate limited) and `5xx` (server error) responses with exponential backoff and jitter.

## Services

### Projects

```php
// Create a project
$project = $client->projects()->create('my-project', 'Production workloads');

// List projects
$projects = $client->projects()->list();

// Delete a project
$client->projects()->delete($projectId);
```

### SSH Keys

```php
// Add an SSH key
$key = $client->sshKeys()->create('my-key', 'ssh-ed25519 AAAA...');

// List SSH keys
$keys = $client->sshKeys()->list();
```

### VPS

```php
// Create a VPS
$task = $client->vps()->create($projectId, [
    'name' => 'web-server',
    'plan_name' => 'gp.small',
    'template_name' => 'debian-12',
    'location_name' => 'us-mia-1',
    'ssh_key_ids' => [12],
    'enable_backups' => true,
]);

// Power actions
$client->vps()->power($vpsId, 'reboot');

// Resize
$client->vps()->resize($vpsId, 'gp.pro');

// Reinstall
$client->vps()->reinstall($vpsId, 'debian-12');

// Destroy
$client->vps()->destroy($vpsId, true); // release floating IPs
```

#### VPS Backups

```php
// List backups
$backups = $client->vps()->backups()->list($vpsId);

// Create a backup
$client->vps()->backups()->create($vpsId, 'Before upgrade');

// Restore a backup
$client->vps()->backups()->restore($vpsId, $backupId);

// Configure backup settings
$client->vps()->backups()->updateSettings($vpsId, [
    'enabled' => true,
    'schedule_hour' => 3,
    'retention_days' => 7,
    'max_backups' => 5,
]);
```

#### VPS ISOs

```php
// List available ISOs
$isos = $client->vps()->isos()->list($vpsId);

// Mount an ISO
$client->vps()->isos()->mount($vpsId, 'iso-id');

// Unmount
$client->vps()->isos()->unmount($vpsId);
```

### Baremetal

```php
// Deploy a bare metal server
$task = $client->baremetal()->deploy($projectId, [
    'model_name' => 'c1.metal.plus',
    'location_name' => 'us-hou-1',
    'hostname' => 'db-primary',
    'password' => 'secure-password',
    'ssh_key_ids' => [12],
    'os_name' => 'debian-12',
]);

// Power actions
$client->baremetal()->power($baremetalId, 'reboot');

// Rescue mode
$rescue = $client->baremetal()->rescue($baremetalId);
echo $rescue['username'] . ' ' . $rescue['password'];

// BMC sensors
$sensors = $client->baremetal()->bmcSensors($baremetalId);

// IPMI session
$session = $client->baremetal()->ipmiSession($baremetalId);
```

### Networks

```php
// Create a private network
$network = $client->networks()->create([
    'name' => 'internal',
    'location_name' => 'us-mia-1',
    'ip_range' => '10.0.0.0',
    'prefix' => 24,
    'project_id' => $projectId,
]);

// Update a network
$client->networks()->update($networkId, ['label' => 'production']);
```

### Floating IPs

```php
// Acquire a floating IP
$ip = $client->floatingIPs()->acquire('ipv4', 'us-mia-1');

// Assign to a VPS
$client->floatingIPs()->assign('vps', $vpsId, $ip['address']);

// Configure reverse DNS
$client->floatingIPs()->configureReverseDNS($ip['address'], 'web.example.com');

// Unassign and release
$client->floatingIPs()->unassign($ip['address']);
$client->floatingIPs()->release($ip['address']);
```

### Firewall

```php
// Create a firewall group
$group = $client->firewall()->create([
    'name' => 'web-rules',
    'enabled' => true,
    'rules' => [
        ['direction' => 'in', 'protocol' => 'tcp', 'port' => '443', 'source' => '0.0.0.0/0'],
        ['direction' => 'in', 'protocol' => 'tcp', 'port' => '80', 'source' => '0.0.0.0/0'],
    ],
]);

// Assign to a VPS
$client->firewall()->assignToVPS($vpsId, [$group['id']]);
```

### DNS

```php
// Create a DNS zone
$zone = $client->dns()->createZone(['domain' => 'example.com']);

// Add records
$client->dns()->createRecord($zone['uuid'], [
    'name' => 'www',
    'record_type' => 'A',
    'content' => '203.0.113.10',
    'ttl' => 300,
]);

$client->dns()->createRecord($zone['uuid'], [
    'name' => 'mail',
    'record_type' => 'MX',
    'content' => 'mail.example.com',
    'ttl' => 3600,
    'priority' => 10,
]);

// Verify zone delegation
$verification = $client->dns()->verifyZone($zone['uuid']);
```

### Load Balancer

```php
// Create a load balancer
$lb = $client->loadBalancer()->create([
    'name' => 'web-lb',
    'plan_name' => 'lb.small',
    'location_name' => 'eu-bcn-1',
]);

// Add a listener
$listener = $client->loadBalancer()->createListener($lb['uuid'], [
    'name' => 'https',
    'protocol' => 'tcp',
    'source_port' => 443,
    'target_port' => 443,
    'algorithm' => 'round_robin',
    'sticky_sessions' => false,
]);

// Add targets
$client->loadBalancer()->addTarget($lb['uuid'], $listener['uuid'], [
    'target_type' => 'vps',
    'target_uuid' => $vpsId,
    'weight' => 100,
]);

// Configure health checks
$client->loadBalancer()->configureHealthCheck($lb['uuid'], $listener['uuid'], [
    'protocol' => 'http',
    'path' => '/health',
    'interval_seconds' => 10,
    'timeout_seconds' => 5,
    'healthy_threshold' => 3,
    'unhealthy_threshold' => 3,
]);
```

### CDN

```php
// Create a CDN zone
$zone = $client->cdn()->createZone([
    'name' => 'my-cdn',
    'plan_name' => 'cdn.starter',
]);

// Add an origin
$client->cdn()->createOrigin($zone['uuid'], [
    'name' => 'primary',
    'address' => 'origin.example.com',
    'port' => 443,
    'protocol' => 'https',
    'weight' => 100,
    'priority' => 1,
    'is_backup' => false,
    'health_check_enabled' => true,
    'health_check_path' => '/health',
    'verify_ssl' => true,
    'enabled' => true,
]);

// Create a WAF rule
$client->cdn()->createWAFRule($zone['uuid'], [
    'name' => 'block-scanners',
    'rule_type' => 'block',
    'priority' => 1,
    'action_config' => ['action' => 'block'],
    'match_conditions' => ['user_agent' => '*scanner*'],
    'enabled' => true,
]);

// Get metrics
$metrics = $client->cdn()->getMetrics($zone['uuid'], 'bandwidth', [
    'minutes' => 60,
]);
```

### Kubernetes

```php
// Create a cluster
$cluster = $client->kubernetes()->create([
    'project_id' => $projectId,
    'name' => 'production',
    'location_name' => 'us-mia-1',
    'ha_control_plane' => true,
    'node_pools' => [
        ['name' => 'workers', 'plan' => 'gp.small', 'count' => 3],
    ],
]);

// Get kubeconfig
$kubeconfig = $client->kubernetes()->getKubeconfig($cluster['uuid']);

// List versions and plans
$versions = $client->kubernetes()->listVersions();
$plans = $client->kubernetes()->listPlans();

// Add a node pool
$pool = $client->kubernetes()->createNodePool($cluster['uuid'], [
    'name' => 'gpu-pool',
    'plan' => 'gp.pro',
    'count' => 2,
    'auto_scale' => true,
    'labels' => ['workload' => 'ml'],
]);

// Scale up
$client->kubernetes()->addNodes($cluster['uuid'], $pool['uuid'], 2);

// Install an addon
$client->kubernetes()->installAddon($cluster['uuid'], 'cert-manager');
```

### NAT Gateway

```php
// List available plans
$plans = $client->natGateway()->listPlans();

// Create a NAT gateway on a private network
$nat = $client->natGateway()->create([
    'name' => 'egress-nat',
    'plan_name' => 'nat.small',
    'network_id' => $networkId,
    'project_id' => $projectId,
]);

// Get details
$nat = $client->natGateway()->get($nat['uuid']);

// Resize to a larger plan
$client->natGateway()->resize($nat['uuid'], 'nat.medium');

// Move to another project
$client->natGateway()->moveToProject($nat['uuid'], $newProjectId);

// Enable delete protection
$client->natGateway()->protection($nat['uuid'], true);

// Metrics and bandwidth
$metrics = $client->natGateway()->getMetrics($nat['uuid']);
$bandwidth = $client->natGateway()->getBandwidthUsage($nat['uuid']);

// Delete
$client->natGateway()->delete($nat['uuid']);
```

### Object Storage

S3 compatible buckets. Buckets and access keys are created asynchronously: they start as
`pending` and are `active` a few seconds later.

```php
$os = $client->objectStorage();

$tiers = $os->listTiers();

$bucket = $os->createBucket(['name' => 'my-backups', 'tier' => 'infrequent_access']);
$detail = $os->getBucket($bucket['uuid']); // poll until $detail['status'] === 'active'

// The secret is only returned here
$key = $os->createKey([
    'name' => 'backup-job',
    'tier' => 'infrequent_access',
    'permission' => 'read_write', // or 'read_only'
    'bucket_uuids' => [$bucket['uuid']], // omit for every bucket of the project
]);
echo $key['access_key_id'], ' ', $key['secret_access_key'], ' ', $key['endpoint'];

$os->updateBucket($bucket['uuid'], ['versioning' => 'enabled', 'protected' => true]);
$usage = $os->getUsage(['period' => '2026-09']);

$os->deleteKey($key['uuid']);
$os->deleteBucket($bucket['uuid'], true); // true purges the content first
```

Serve a bucket publicly through the CDN by adding it as an origin of a CDN zone (deleting the
origin stops serving it):

```php
$origin = $client->cdn()->createBucketOrigin($zoneUuid, $bucket['uuid'], 'assets');
```

### Pricing

```php
// Get all pricing information
$pricing = $client->pricing()->get();
```

### DDoS

```php
// List DDoS attacks
$attacks = $client->ddos()->listAttacks();
```

### AI Gateway

```php
// List available models
$models = $client->aiGateway()->listModels();

// Chat completion
$response = $client->aiGateway()->chatCompletion([
    'model' => 'openai/gpt-4o',
    'messages' => [
        ['role' => 'user', 'content' => 'Hello!'],
    ],
]);
echo $response['choices'][0]['message']['content'];
```

## Error Handling

```php
use Cubepath\CubepathClient;
use Cubepath\APIError;

$client = new CubepathClient('your-api-key');

try {
    $client->vps()->get(999);
} catch (APIError $e) {
    if ($e->isNotFound()) {
        echo 'VPS not found';
    } elseif ($e->isRateLimited()) {
        echo 'Rate limited, retries exhausted';
    } elseif ($e->isServerError()) {
        echo 'Server error';
    } else {
        echo "API error {$e->getStatusCode()}: {$e->getDetail()}";
    }
}
```

## Related Projects

- [cubepath-go-sdk](https://github.com/CubePathInc/cubepath-go-sdk) - Go SDK
- [cubepath-node-sdk](https://github.com/CubePathInc/cubepath-node-sdk) - Node.js SDK
- [cubepath-python-sdk](https://github.com/CubePathInc/cubepath-python-sdk) - Python SDK
- [cubecli](https://github.com/CubePathInc/cubecli) - CLI tool
- [terraform-provider-cubepath](https://github.com/CubePathInc/terraform-provider-cubepath) - Terraform provider

## License

MIT
