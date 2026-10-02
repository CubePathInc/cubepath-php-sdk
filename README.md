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
| `user_agent` | `cubepath-sdk-php/<version>` | Custom User-Agent header |
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

// Rename a project
$client->projects()->update($projectId, 'production');

// Delete a project
$client->projects()->delete($projectId);
```

### SSH Keys

```php
// Add an SSH key
$key = $client->sshKeys()->create('my-key', 'ssh-ed25519 AAAA...');

// List SSH keys
$keys = $client->sshKeys()->list();

// Rename an SSH key
$client->sshKeys()->update($key['id'], 'work-laptop');
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

// Plans per location, and every VPS as a flat list
$plans = $client->vps()->plans();
$servers = $client->vps()->listAll();

// Delete protection and moving to another project
$client->vps()->protection($vpsId, true);
$client->vps()->moveToProject($vpsId, $otherProjectId);

// SSH keys and private network of a running VPS
$client->vps()->addSSHKeys($vpsId, [12, 13]);
$client->vps()->removeSSHKey($vpsId, 13);
$client->vps()->attachNetwork($vpsId, $networkId);
$client->vps()->detachNetwork($vpsId);

// Console
$console = $client->vps()->vncUrl($vpsId);

// Destroy
$client->vps()->destroy($vpsId, true); // release floating IPs
```

#### Availability Groups

VPS in the same availability group run on different hosts.

```php
$groups = $client->vps()->availabilityGroups();

$group = $groups->create([
    'project_id' => $projectId,
    'name' => 'web',
    'location_name' => 'eu-bcn-1',
]);
$groups->addVPS($group['uuid'], $vpsId);
$detail = $groups->get($group['uuid']);
$all = $groups->list($projectId, 'eu-bcn-1');

$groups->removeVPS($group['uuid'], $vpsId);
$groups->delete($group['uuid']);
```

#### VPS Backups

```php
// List backups (limit 50, offset 0 by default)
$backups = $client->vps()->backups()->list($vpsId);
$next = $client->vps()->backups()->list($vpsId, 50, 50);

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

// BMC sensors (temperatures in CELSIUS, fans in RPM; last_seen is the last BMC poll)
$sensors = $client->baremetal()->bmcSensors($baremetalId);

// Reinstall progress (the server status is 'deploying' while it runs) and cancel
$status = $client->baremetal()->reinstallStatus($baremetalId);
$client->baremetal()->cancelReinstall($baremetalId);

// IPMI session, or KVM access on servers with has_kvm
$session = $client->baremetal()->ipmiSession($baremetalId);
$kvm = $client->baremetal()->kvm($baremetalId);

// Models to deploy, OS options of a server, and every server as a flat list
$models = $client->baremetal()->models();
$osOptions = $client->baremetal()->listOS($baremetalId);
$servers = $client->baremetal()->listAll();

// Protection, project, SSH keys and private network
$client->baremetal()->protection($baremetalId, true);
$client->baremetal()->moveToProject($baremetalId, $otherProjectId);
$client->baremetal()->addSSHKeys($baremetalId, [12]);
$client->baremetal()->removeSSHKey($baremetalId, 12);
$client->baremetal()->attachNetwork($baremetalId, $networkId);
$client->baremetal()->detachNetwork($baremetalId);
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

// Move it to another project
$client->networks()->moveToProject($networkId, $otherProjectId);

// BGP peers: a VPS, baremetal server or IP of the network announcing routes
$client->networks()->createBGPPeer($networkId, [
    'peer_type' => 'vps',
    'peer_target' => (string) $vpsId,
    'remote_asn' => 65010,
]);
$peers = $client->networks()->listBGPPeers($networkId); // with session state and received prefixes
$client->networks()->updateBGPPeer($networkId, $peers[0]['id'], ['enabled' => false]);
$client->networks()->deleteBGPPeer($networkId, $peers[0]['id']);
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
    'project_id' => $projectId,
    'name' => 'web-rules',
    'enabled' => true,
    'rules' => [
        ['direction' => 'in', 'protocol' => 'tcp', 'port' => '443', 'source' => '0.0.0.0/0'],
        ['direction' => 'in', 'protocol' => 'tcp', 'port' => '80', 'source' => '0.0.0.0/0'],
    ],
]);

// Replace the groups of a VPS (at most 10, in priority order; [] removes them all)
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

// SOA
$soa = $client->dns()->getSOA($zone['uuid']);
$client->dns()->updateSOA($zone['uuid'], ['refresh' => 7200]);

// Health check of a record, for automatic failover (billed add-on)
$client->dns()->setHealthCheck($zone['uuid'], $recordUuid, [
    'name' => 'web',
    'check_type' => 'https', // http, https, tcp or ping
    'path' => '/health',
]);
$checks = $client->dns()->listHealthChecks($zone['uuid']);
$client->dns()->deleteHealthCheck($zone['uuid'], $recordUuid);

// GeoDNS regions
$regions = $client->dns()->listRegions();
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

// Add several targets at once
$client->loadBalancer()->addTargets($lb['uuid'], $listener['uuid'], [
    ['target_type' => 'vps', 'target_uuid' => (string) $vpsA],
    ['target_type' => 'vps', 'target_uuid' => (string) $vpsB],
]);

// Delete protection and moving to another project
$client->loadBalancer()->protection($lb['uuid'], true);
$client->loadBalancer()->moveToProject($lb['uuid'], $otherProjectId);
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

// Get metrics: summary, requests, bandwidth, cache, status-codes, top-urls, top-countries,
// top-asn, top-user-agents, blocked, pops or file-extensions (raw JSON)
$metrics = $client->cdn()->getMetrics($zone['uuid'], 'bandwidth', [
    'minutes' => 60,
    'country' => 'ES', // optional filters: country, asn, status_range, status, cache_status,
                       // device_type, path_prefix
]);

// Purge cached files (or ['everything' => true]) and follow the purge
$purge = $client->cdn()->purgeCache($zone['uuid'], ['paths' => ['/css/app.css']]);
$purges = $client->cdn()->listPurges($zone['uuid']);

// Token authentication: sign a URL, or rotate the secret
$signed = $client->cdn()->signURL($zone['uuid'], '/videos/intro.mp4', 3600);
echo $signed['signed_url'];
$client->cdn()->rotateTokenSecret($zone['uuid']);
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

// Cluster and node metrics (1h by default: 1h, 3h, 6h, 12h, 24h, 3d, 7d, 30d)
$metrics = $client->kubernetes()->getMetrics($cluster['uuid'], '24h');
$nodeMetrics = $client->kubernetes()->getNodeMetrics($cluster['uuid'], $nodeName);

// Delete protection
$client->kubernetes()->protection($cluster['uuid'], true);
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

// Traffic metrics (H1 by default: H1, H3, H6, H12, H24, D3, D7, D30) and month-to-date usage
$metrics = $client->natGateway()->getMetrics($nat['uuid'], 'H24');
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

### Managed Databases

MySQL, PostgreSQL and Valkey clusters. The plan decides the location. Creating, scaling,
reconfiguring, rotating credentials and deleting run in the background: poll `get()` until the
status is `active` again.

```php
$md = $client->managedDatabases();

// Plans per location (cpu, memory and storage per node; price per node per hour)
$plans = $md->listPlans('postgresql');

$db = $md->create([
    'project_id' => $projectId,
    'name' => 'orders',
    'engine' => 'postgresql', // mysql, postgresql or valkey
    'version' => '17.5.0',
    'plan_uuid' => $plans[0]['plans'][0]['uuid'],
    'replicas' => 2,          // at least 3 for mysql, 2 for postgresql and valkey
]);
$detail = $md->get($db['uuid']); // poll until $detail['status'] === 'active'

$creds = $md->getCredentials($db['uuid']); // host, port, username, password, uri

// Logical databases and users (the user password is only returned here)
$md->createDatabase($db['uuid'], 'app');
$user = $md->createUser($db['uuid'], 'app_user');
echo $user['password'];

// Tuning, scaling, protection, metrics
$config = $md->getConfig($db['uuid']);
$md->updateConfig($db['uuid'], ['max_connections' => 200]);
$md->scale($db['uuid'], ['replicas' => 3]);
$md->rotateCredentials($db['uuid']);
$md->protection($db['uuid'], true);
$metrics = $md->getMetrics($db['uuid'], ['cpu', 'memory'], '24h');

$md->delete($db['uuid']);
```

### DDoS Mitigation

Protection profiles, filtering rules and traffic captures work on IPs with Premium protection.
A network is a single IP or a CIDR such as `203.0.113.0/24`.

```php
$ddos = $client->ddosMitigation();

$ips = $ddos->listIPs(['has_profile' => false]);

// Protection profile (a CIDR sets every IP in it)
$profile = $ddos->getProfile('203.0.113.10');
$ddos->updateProfile('203.0.113.10', ['udp_validation_level' => 2, 'country_mode' => 1]);
$ddos->setProfileCountries('203.0.113.10', ['CN', 'RU']); // with country_mode 1 (block list)
$ddos->deleteProfile('203.0.113.10'); // back to the defaults

// Filtering rules: protocol 6 = TCP, action 0 = DROP
$ddos->createFirewallRule(['network' => '203.0.113.10', 'protocol' => 6, 'dst_port' => 23, 'action' => 0]);
$rules = $ddos->listFirewallRules('203.0.113.10');
$ddos->deleteFirewallRule($rules['rules'][0]['id']);

// Prefix lists
$ddos->createPrefixList('office', 'HQ ranges');
$lists = $ddos->listPrefixLists();
$ddos->addPrefixListEntry($listUuid, '198.51.100.0/24');
$ddos->setProfilePrefixLists('203.0.113.10', [$listUuid]);

// Packet captures and PASS/DROP statistics
$stats = $ddos->getTrafficStats([
    'start_time' => '2026-09-30T10:00:00Z',
    'end_time' => '2026-09-30T11:00:00Z',
    'interval' => '5m',
]);
```

### Cloud Alerts

Notify a Slack, Discord or email channel when a metric of a VPS, baremetal server,
availability group or Object Storage bucket, or the Object Storage usage of the organization,
crosses a threshold.

```php
$alerts = $client->cloudAlerts();

$channel = $alerts->createNotificator([
    'name' => 'ops',
    'type' => 'slack', // slack, discord or email
    'config' => ['webhook_url' => 'https://hooks.slack.com/services/...'],
]);

$alert = $alerts->create([
    'project_id' => $projectId,
    'name' => 'High CPU',
    'target_type' => 'vps',      // vps, baremetal, availability_group, object_storage_bucket or organization
    'target_id' => (string) $vpsId,
    'metric_type' => 'cpu',      // cpu, ram, disk, network_in or network_out
    'operator' => 'gt',          // gt, lt, gte, lte or eq
    'threshold' => 90,
    'duration_seconds' => 300,
    'actions' => [['action_type' => 'notify', 'notificator_id' => $channel['id']]],
]);

// Monthly Object Storage budget: notifies once when 50 USD have been billed this month and
// resets on the 1st (UTC). Only gt and gte; duration and cooldown are ignored.
$budget = $alerts->create([
    'project_id' => $projectId,
    'name' => 'Object Storage budget',
    'target_type' => 'organization',
    'target_id' => (string) $organizationId,
    'metric_type' => 'storage_cost_month', // or storage_egress_gb_month
    'operator' => 'gte',
    'threshold' => 50,
    'actions' => [['action_type' => 'notify', 'notificator_id' => $channel['id']]],
]);

// Bucket size above 500 GiB (create it in the bucket's project). Buckets also support
// storage_egress_gb_month, storage_error_rate_5xx and storage_error_rate_403.
$bucketAlert = $alerts->create([
    'project_id' => $bucket['project_id'],
    'name' => 'Assets bucket size',
    'target_type' => 'object_storage_bucket',
    'target_id' => $bucket['uuid'],
    'metric_type' => 'storage_size_gb',
    'operator' => 'gt',
    'threshold' => 500,
    'actions' => [['action_type' => 'notify', 'notificator_id' => $channel['id']]],
]);

$alerts->update($alert['id'], ['status' => 'disabled']);
$events = $alerts->history($alert['id']);

$alerts->delete($alert['id']);
$alerts->deleteNotificator($channel['id']);
```

### Video Transcoder

Read a video from a URL or an S3 compatible bucket and write the outputs to an S3 compatible
bucket. Jobs run in the background: poll `getJob()` or pass a `webhook_url`.

```php
$tc = $client->transcoder();

$job = $tc->createJob([
    'input' => ['source' => 'url', 'url' => 'https://example.com/video.mp4'],
    'output' => ['s3' => [
        'endpoint' => 'https://s3.example.com',
        'bucket' => 'media',
        'path' => 'out/',
        'access_key' => '...',
        'secret_key' => '...',
    ]],
    'outputs' => [['type' => 'hls'], ['type' => 'thumbnails']],
]);

$job = $tc->getJob($job['uuid']); // queued, analyzing, encoding, finalizing, completed, failed, canceled
$files = $tc->getJobOutputs($job['uuid']);

// Pages of up to 500 jobs, newest first
$page = $tc->listJobs(['limit' => 100, 'offset' => 0]);

$tc->cancelJob($job['uuid']);
```

### Pricing

```php
// Get all pricing information
$pricing = $client->pricing()->get();
```

### DDoS Attacks

```php
// List DDoS attacks, with the breakdown and traffic of one
$attacks = $client->ddos()->listAttacks();
$details = $client->ddos()->getAttackDetails($attackId);
$graph = $client->ddos()->getAttackTrafficGraph($attackId);
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
