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

$bucket = $os->createBucket([
    'name' => 'my-backups',
    'tier' => 'infrequent_access',
    'tags' => ['env' => 'prod', 'team' => 'data'], // optional labels
]);
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

// Tags: replaces every tag ([] removes them all); filter with "key" or "key=value", all must match
$os->updateBucket($bucket['uuid'], ['tags' => ['env' => 'staging']]);
$prod = $os->listBuckets(['tags' => ['env=prod', 'team']]);
$usage = $os->getUsage(['period' => '2026-09']);

// Charts of one bucket (GraphQL): stored size and objects, traffic and responses per step
// over H1, H3, H6, H12, H24 (default), D3, D7 or D30
$metrics = $os->bucketMetrics($bucket['uuid'], 'D7');

$os->deleteKey($key['uuid']);
$os->deleteBucket($bucket['uuid'], true); // true purges the content first
```

Lifecycle rules delete objects in the background, permanently. `putBucketLifecycle()` replaces
every rule and is applied asynchronously (seconds, up to about 12 minutes after a previous change of
the same bucket); objects go within 48 hours of their due date. In a versioned bucket an
expiration only adds a delete marker: add a noncurrent version rule to free space.

```php
$change = $os->putBucketLifecycle($bucket['uuid'], [
    ['id' => 'logs-30d', 'enabled' => true, 'filter' => ['prefix' => 'logs/'], 'expiration' => ['days' => 30]],
    ['id' => 'old-versions', 'enabled' => true, 'noncurrent_version_expiration' => ['noncurrent_days' => 30]],
]);
$lifecycle = $os->getBucketLifecycle($bucket['uuid']); // applied when applied_generation >= generation
$os->deleteBucketLifecycle($bucket['uuid']);
```

#### Object Lock

Object Lock (WORM) keeps object versions from being deleted or overwritten until their
retention date. It can only be enabled when the bucket is created, never later; the bucket
always keeps versioning enabled and is created with deletion protection on.

- `governance`: keys created with `bypass_governance` can still delete a version early (sending
  `x-amz-bypass-governance-retention: true`).
- `compliance`: nobody can delete a version or shorten its retention before the date, CubePath
  included. Only organizations that support enabled for it can use it.

```php
$vault = $os->createBucket([
    'name' => 'veeam-repo',
    'tier' => 'infrequent_access',
    'object_lock' => true, // implies versioning
    'object_lock_default' => ['mode' => 'governance', 'days' => 30], // or 'years' => N
    'accept_object_lock_terms' => true,
]);
var_dump($vault['object_lock']['enabled']);

// Change the default retention (a compliance rule can only be kept or lengthened); the last
// argument accepts the terms, needed when the rule turns compliance on or gets longer
$os->setBucketObjectLock($vault['uuid'], ['mode' => 'governance', 'years' => 1], true);
$os->setBucketObjectLock($vault['uuid'], null); // remove it

// A key that may delete governance versions early (read_write only)
$os->createKey(['name' => 'veeam', 'tier' => 'infrequent_access', 'permission' => 'read_write', 'bypass_governance' => true]);

// Delete: disable protection first. The third argument (with force) also purges governance
// versions. Versions under compliance or a legal hold are kept: the bucket stays with
// locked_content_kept set and keeps being billed until their retention ends.
$os->deleteBucket($vault['uuid'], true, true);
```

#### Replication

A replication copies the new object versions of a source bucket, asynchronously, to one
destination: another CubePath bucket of the same tier, or an external S3 compatible bucket (AWS S3,
Wasabi or another provider) over HTTPS on port 443. Versioning must be enabled on the source (and
on a CubePath destination), and a bucket with Object Lock cannot be a source. A CubePath
destination lives in the same storage cluster, so it is not a disaster recovery copy: use an
external destination for an off site copy. Replication to an external destination is billed as
egress of the source bucket.

```php
// To another bucket of the organization
$repl = $os->createReplication([
    'source_bucket_uuid' => $bucket['uuid'],
    'destination' => ['type' => 'cubepath', 'bucket_uuid' => $backup['uuid']],
    'prefix' => 'img/', // optional; or 'tags' => [['key' => 'backup', 'value' => 'yes']]
]);

// To an external bucket; the secret is never returned
$os->createReplication([
    'source_bucket_uuid' => $bucket['uuid'],
    'destination' => [
        'type' => 'external', 'provider' => 'aws', 'endpoint' => 's3.eu-west-1.amazonaws.com',
        'region' => 'eu-west-1', 'bucket' => 'acme-backup',
        'access_key_id' => getenv('AWS_ACCESS_KEY_ID'), 'secret_access_key' => getenv('AWS_SECRET_ACCESS_KEY'),
    ],
]);

$detail = $os->getReplication($repl['uuid']); // status, health, backfill and metrics
$outgoing = $os->listReplications(['direction' => 'outgoing']);
$os->updateReplication($repl['uuid'], ['enabled' => false]); // pause; true resumes
$os->updateReplication($repl['uuid'], ['prefix' => null]);   // null removes the filter
$os->resyncReplication($repl['uuid'], 7);                    // existing objects older than 7 days; null for all
$os->deleteReplication($repl['uuid']);                       // the replicated data stays
```

To replicate into a bucket of another organization, its owner creates a grant (one use, 1 to 30
days, 7 by default) and shares the token, which is only returned once:

```php
// Owner of the destination bucket
$grant = $os->createReplicationGrant($backup['uuid'], 'for Acme', 7);
echo $grant['token']; // cprg_...
$os->listReplicationGrants($backup['uuid']);
$os->deleteReplicationGrant($grant['uuid']); // revoke it while unused
$os->revokeReplication($incomingUuid);       // stop an incoming replication

// Owner of the source bucket
$os->createReplication([
    'source_bucket_uuid' => $bucket['uuid'],
    'destination' => ['type' => 'cubepath', 'bucket_uuid' => $backupUuid, 'grant_token' => $token],
]);
```

#### Encryption at rest

Every bucket stores its objects encrypted with AES-256 (SSE-S3); there is nothing to configure.
`encryption` on a bucket is `null` until the bucket default is applied, then `algorithm` is
`AES256` and `scope` is `all_objects`, or `new_objects` while objects uploaded before the default
may still be stored unencrypted (they are re-encrypted in the background). SSE-KMS is not
available; SSE-C (your own key in each request) works through any S3 client.

```php
$bucket = $os->getBucket($uuid);
echo $bucket['encryption']['scope'] ?? 'not applied yet';
```

Serve a bucket publicly through the CDN by adding it as an origin of a CDN zone (deleting the
origin stops serving it):

```php
$origin = $client->cdn()->createBucketOrigin($zoneUuid, $bucket['uuid'], 'assets');
```

#### Event Notifications

Send bucket events (`object.created`, `object.removed`, `object.tagging`) to a signed webhook
or to a Cloud Alerts channel. A destination belongs to the organization; a rule on a bucket picks
the events, an optional key prefix and suffix, and the destination. The signing secret is only
returned by `createEventDestination()` and `rotateEventDestinationSecret()`: store it then.
After a rotation the previous secret keeps signing for 24 hours.

```php
$created = $client->objectStorage()->createEventDestination([
    'name' => 'uploads-hook',
    'type' => 'webhook',
    'url'  => 'https://example.com/hooks/storage', // or 'type' => 'notificator', 'notificator_id' => $channelId
]);
$secret = $created['signing_secret']; // whsec_..., shown only now

$rule = $client->objectStorage()->createEventRule($bucket['uuid'], [
    'name'             => 'new-uploads',
    'destination_uuid' => $created['destination']['uuid'],
    'events'           => ['object.created'],
    'prefix'           => 'incoming/',
]); // status "pending" until applied, then "active"

$client->objectStorage()->testEventDestination($created['destination']['uuid']); // sends a cubepath.ping
$page = $client->objectStorage()->listEventDeliveries($created['destination']['uuid'], ['status' => 'failed']);
// Older page: ['before' => $page['next_before']] while next_before is not null (unix milliseconds).
```

Verify every webhook delivery before trusting it, against the raw body. `CubePath-Signature`
holds one or more `v1=<hex>` values (`v1=<new>, v1=<previous>` for 24 hours after a rotation), each the HMAC-SHA256 of `CubePath-Timestamp + "." + body`;
`Webhooks::verifyStorageEventSignature()` compares them in constant time and rejects timestamps
more than 5 minutes away:

```php
use Cubepath\StorageEventSignatureException;
use Cubepath\Webhooks;

try {
    Webhooks::verifyStorageEventSignature(
        $secret,
        $_SERVER['HTTP_CUBEPATH_TIMESTAMP'] ?? '',
        file_get_contents('php://input'),
        $_SERVER['HTTP_CUBEPATH_SIGNATURE'] ?? ''
    );
} catch (StorageEventSignatureException $e) {
    http_response_code(401);
    exit;
}
// Deliveries are at least once: deduplicate by the CubePath-Event-Id header.
http_response_code(204);
```

#### Presigned URLs

This SDK talks to the CubePath API, not to S3. To share one object for a while, sign a
presigned GET URL with the official S3 SDK and one of your access keys: endpoint
`https://eu.cubestorage.io`, region `eu`, path style, SigV4. A URL lasts at most 24 hours
(86400 seconds), the file is always downloaded as an attachment (do not set
`ResponseContentDisposition` or any other `response-*` override: they are refused) and every
download counts as egress of the bucket. Deleting the access key that signed a URL cuts it
before it expires. From a terminal, `cubecli s3 presign <bucket>/<key> --expires 6h` does the
same.

```php
use Aws\S3\S3Client;

$s3 = new S3Client([
    'version' => 'latest',
    'region' => 'eu',
    'endpoint' => 'https://eu.cubestorage.io',
    'use_path_style_endpoint' => true,
    'credentials' => ['key' => $key['access_key_id'], 'secret' => $key['secret_access_key']],
]);
$command = $s3->getCommand('GetObject', ['Bucket' => 'my-backups', 'Key' => 'reports/2026-09.pdf']);
$url = (string) $s3->createPresignedRequest($command, '+24 hours')->getUri();
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
