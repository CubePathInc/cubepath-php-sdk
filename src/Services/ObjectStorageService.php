<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

/**
 * Object Storage (S3 compatible): tiers, buckets, access keys and usage.
 *
 * Buckets and access keys are created asynchronously: they start as "pending" and become
 * "active" a few seconds later (poll getBucket() or listKeys() until then).
 */
class ObjectStorageService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    // --- Tiers ---

    /**
     * List the storage tiers with endpoint, region, prices, free tier and whether they accept
     * new buckets.
     *
     * @return array List of tiers (uuid, slug, name, media, location_name, location_description,
     *               region, endpoint, prices, free_tier, accepting_new)
     */
    public function listTiers(): array
    {
        return $this->client->get('/object-storage/tiers');
    }

    // --- Buckets ---

    /**
     * List the organization's buckets.
     *
     * @param array $filters {
     *     @type int    $project_id Only this project's buckets (optional)
     *     @type string $tier       Tier uuid or slug (optional)
     * }
     * @return array List of buckets (uuid, name, status, tier, region, endpoint, versioning,
     *               protected, size_bytes, objects_count, monthly_charges, cdn_connected, ...)
     */
    public function listBuckets(array $filters = []): array
    {
        return $this->client->get('/object-storage/buckets', $filters);
    }

    /**
     * Get a bucket with its connection details, month usage and the CDN origin serving it.
     *
     * @param string $uuid
     * @return array Bucket fields plus active_at, connection, usage (null when metrics are
     *               unavailable) and cdn (null when no CDN origin serves it)
     */
    public function getBucket(string $uuid): array
    {
        return $this->client->get('/object-storage/buckets/' . rawurlencode($uuid));
    }

    /**
     * Create a bucket. Bucket names are unique across all CubePath customers.
     *
     * @param array $params {
     *     @type string $name       Bucket name, 3 to 63 lowercase letters, numbers and hyphens (required)
     *     @type string $tier       Tier uuid or slug, e.g. "infrequent_access" (required)
     *     @type int    $project_id Project ID (optional, default: the organization's first project)
     *     @type bool   $versioning Create with versioning enabled (optional)
     * }
     * @return array Contains detail, uuid, name, status ("pending"), project_id, tier, region, endpoint
     */
    public function createBucket(array $params): array
    {
        return $this->client->post('/object-storage/buckets', $params);
    }

    /**
     * Change versioning or deletion protection of a bucket.
     *
     * @param string $uuid
     * @param array  $params {
     *     @type string $versioning "enabled" or "suspended" (optional)
     *     @type bool   $protected  Deletion protection (optional)
     * }
     * @return array Contains detail
     */
    public function updateBucket(string $uuid, array $params): array
    {
        return $this->client->patch('/object-storage/buckets/' . rawurlencode($uuid), $params);
    }

    /**
     * Delete a bucket. Without $force only an empty bucket is deleted; with $force its content
     * is purged first. Protected buckets and buckets served by a CDN origin cannot be deleted.
     *
     * @param string $uuid
     * @param bool   $force Purge the bucket content first
     * @return array Contains detail
     */
    public function deleteBucket(string $uuid, bool $force = false): array
    {
        $path = '/object-storage/buckets/' . rawurlencode($uuid);
        if ($force) {
            $path .= '?force=true';
        }
        return $this->client->delete($path);
    }

    // --- Lifecycle rules ---

    /**
     * The lifecycle rules of a bucket and whether they are applied.
     *
     * @param string $uuid
     * @return array Contains bucket_uuid, status (none, pending, active, paused, error), rules,
     *               platform_rules, generation, applied_generation, error, updated_at, notes
     */
    public function getBucketLifecycle(string $uuid): array
    {
        return $this->client->get('/object-storage/buckets/' . rawurlencode($uuid) . '/lifecycle');
    }

    /**
     * Replace every lifecycle rule of a bucket (1 to 100 rules). Expiration rules delete objects
     * permanently. The change is applied asynchronously: poll getBucketLifecycle() until
     * applied_generation reaches the returned generation.
     *
     * Rule: ['id' => 'logs-30d', 'enabled' => true, 'filter' => ['prefix' => 'logs/'],
     * 'expiration' => ['days' => 30]]. Also 'expiration' => ['date' => 'YYYY-MM-DD'] or
     * ['expired_object_delete_marker' => true], 'noncurrent_version_expiration' =>
     * ['noncurrent_days' => 30, 'newer_noncurrent_versions' => 3] and
     * 'abort_incomplete_multipart_upload' => ['days_after_initiation' => 2].
     *
     * @param string $uuid
     * @param array  $rules List of rules
     * @return array Contains detail, generation (absent when nothing changed) and notes
     */
    public function putBucketLifecycle(string $uuid, array $rules): array
    {
        return $this->client->put('/object-storage/buckets/' . rawurlencode($uuid) . '/lifecycle', ['rules' => $rules]);
    }

    /**
     * Remove every lifecycle rule of a bucket.
     *
     * @param string $uuid
     * @return array Contains detail and generation (absent when the bucket had no rules)
     */
    public function deleteBucketLifecycle(string $uuid): array
    {
        return $this->client->delete('/object-storage/buckets/' . rawurlencode($uuid) . '/lifecycle');
    }

    // --- Access keys ---

    /**
     * List the organization's access keys. Secrets are never returned here.
     *
     * @param array $filters {
     *     @type int    $project_id Only this project's keys (optional)
     *     @type string $tier       Tier uuid or slug (optional)
     * }
     * @return array List of keys (uuid, name, access_key_id, permission, bucket_scope, project_id,
     *               tier, region, endpoint, status, expires_at)
     */
    public function listKeys(array $filters = []): array
    {
        return $this->client->get('/object-storage/keys', $filters);
    }

    /**
     * Create an access key for S3 clients. The secret is only returned by this call.
     *
     * @param array $params {
     *     @type string   $name         Key name (required)
     *     @type string   $tier         Tier uuid or slug (required)
     *     @type string   $permission   "read_write" or "read_only" (required)
     *     @type int      $project_id   Project ID (optional)
     *     @type string[] $bucket_uuids Limit the key to these buckets (optional, default: every bucket)
     *     @type string   $expires_at   ISO 8601 expiry (optional)
     * }
     * @return array Contains detail, uuid, name, access_key_id, secret_access_key, permission,
     *               bucket_scope, project_id, tier, region, endpoint, status ("pending"), expires_at
     */
    public function createKey(array $params): array
    {
        return $this->client->post('/object-storage/keys', $params);
    }

    /**
     * Revoke an access key.
     *
     * @param string $uuid
     * @return array Contains detail
     */
    public function deleteKey(string $uuid): array
    {
        return $this->client->delete('/object-storage/keys/' . rawurlencode($uuid));
    }

    // --- Usage ---

    /**
     * Month usage and cost per tier and bucket.
     *
     * @param array $filters {
     *     @type string $period     "YYYY-MM", within the last 12 months (optional, default: current month)
     *     @type int    $project_id (optional)
     *     @type string $tier       Tier uuid or slug (optional)
     * }
     * @return array Contains period, since, until, metrics_available, total_cost, projected_cost,
     *               tiers, buckets, available_months
     */
    public function getUsage(array $filters = []): array
    {
        return $this->client->get('/object-storage/usage', $filters);
    }
}
