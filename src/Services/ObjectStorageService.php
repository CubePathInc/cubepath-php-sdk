<?php

namespace Cubepath\Services;

use Cubepath\APIError;
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
     *     @type int      $project_id Only this project's buckets (optional)
     *     @type string   $tier       Tier uuid or slug (optional)
     *     @type string[] $tags       Only buckets with every one of these tags: "key" (any value)
     *                                or "key=value", at most 10 (optional)
     * }
     * @return array List of buckets (uuid, name, status, tier, region, endpoint, versioning,
     *               protected, size_bytes, objects_count, monthly_charges, cdn_connected, tags,
     *               object_lock (enabled, default_retention), locked_content_kept,
     *               encryption (null while off, or algorithm "AES256", scope
     *               "all_objects" or "new_objects" and applied_at), ...)
     */
    public function listBuckets(array $filters = []): array
    {
        return $this->client->get(self::withTagFilter('/object-storage/buckets', $filters));
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
     *     @type array  $tags       Labels as ['key' => 'value'], at most 50; key 1 to 128 and
     *                              value 0 to 256 characters (optional)
     *     @type bool   $object_lock Create the bucket with Object Lock (WORM). Only possible now,
     *                              never later; implies versioning and deletion protection (optional)
     *     @type array  $object_lock_default Default retention of new objects, only with object_lock:
     *                              ['mode' => 'governance'|'compliance', 'days' => N] or
     *                              ['mode' => ..., 'years' => N] (optional)
     *     @type bool   $accept_object_lock_terms Must be true with object_lock (optional)
     *     @type bool   $encryption Encryption at rest (AES-256), true when omitted. false creates
     *                              the bucket without it: it can be enabled later
     *                              (enableBucketEncryption), never turned off (optional)
     * }
     * @return array Contains detail, uuid, name, status ("pending"), project_id, tier, region,
     *               endpoint, tags, object_lock (enabled, default_retention)
     */
    public function createBucket(array $params): array
    {
        // Object Lock implies versioning: an explicit versioning false would be refused.
        if (!empty($params['object_lock']) && array_key_exists('versioning', $params) && !$params['versioning']) {
            unset($params['versioning']);
        }
        return $this->client->post('/object-storage/buckets', self::tagsAsObject($params));
    }

    /**
     * Turn on encryption at rest (AES-256) for a bucket created without it. The objects already
     * stored are encrypted in the background; in a versioned bucket only the current versions
     * are. It cannot be turned off afterwards; on an encrypted bucket nothing changes.
     *
     * @param string $uuid
     * @return array Contains detail and reencrypt_job_id (null when there was nothing to encrypt)
     */
    public function enableBucketEncryption(string $uuid): array
    {
        return $this->client->put('/object-storage/buckets/' . rawurlencode($uuid) . '/encryption', ['enabled' => true]);
    }

    /**
     * Change or remove the default retention of a bucket created with Object Lock. Object Lock
     * itself can only be enabled when the bucket is created. A compliance rule can only be kept
     * or lengthened.
     *
     * @param string     $uuid
     * @param array|null $defaultRetention ['mode' => 'governance'|'compliance', 'days' => N] or
     *                                     ['mode' => ..., 'years' => N]; null removes it
     * @param bool       $acceptObjectLockTerms Required (true) when the change turns compliance on
     *                                          or lengthens the retention
     * @return array Contains detail
     */
    public function setBucketObjectLock(string $uuid, ?array $defaultRetention, bool $acceptObjectLockTerms = false): array
    {
        return $this->client->put('/object-storage/buckets/' . rawurlencode($uuid) . '/object-lock', [
            'default_retention' => $defaultRetention,
            'accept_object_lock_terms' => $acceptObjectLockTerms,
        ]);
    }

    /**
     * Change versioning, deletion protection or tags of a bucket.
     *
     * Bucket tags are managed through the API only: S3 bucket tagging calls answer 403.
     *
     * @param string $uuid
     * @param array  $params {
     *     @type string $versioning "enabled" or "suspended" (optional)
     *     @type bool   $protected  Deletion protection (optional)
     *     @type array  $tags       Replaces every tag with ['key' => 'value']; [] removes them all;
     *                              leave it out to keep them (optional)
     * }
     * @return array Contains detail
     */
    public function updateBucket(string $uuid, array $params): array
    {
        return $this->client->patch('/object-storage/buckets/' . rawurlencode($uuid), self::tagsAsObject($params));
    }

    /**
     * Delete a bucket. Without $force only an empty bucket is deleted; with $force its content
     * is purged first. Protected buckets and buckets served by a CDN origin cannot be deleted.
     *
     * On a bucket with Object Lock, $bypassGovernance (only with $force) also deletes the
     * versions under governance retention. Versions under compliance or a legal hold are always
     * kept: the bucket comes back with locked_content_kept set and keeps being billed.
     *
     * @param string $uuid
     * @param bool   $force            Purge the bucket content first
     * @param bool   $bypassGovernance Also delete versions under governance retention
     * @return array Contains detail
     */
    public function deleteBucket(string $uuid, bool $force = false, bool $bypassGovernance = false): array
    {
        $query = [];
        if ($force) {
            $query['force'] = 'true';
        }
        if ($bypassGovernance) {
            $query['bypass_governance'] = 'true';
        }
        $path = '/object-storage/buckets/' . rawurlencode($uuid);
        if (!empty($query)) {
            $path .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
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
     *               tier, region, endpoint, status, expires_at, bypass_governance)
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
     *     @type bool     $bypass_governance read_write keys only: may delete versions under
     *                                  governance retention; cannot be changed later (optional)
     * }
     * @return array Contains detail, uuid, name, access_key_id, secret_access_key, permission,
     *               bucket_scope, project_id, tier, region, endpoint, status ("pending"), expires_at,
     *               bypass_governance
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
     *     @type string   $tier       Tier uuid or slug (optional)
     *     @type string[] $tags       Only buckets with every one of these tags, like listBuckets() (optional)
     * }
     * @return array Contains period, since, until, metrics_available, total_cost, projected_cost,
     *               tiers, buckets (each with its tags), available_months
     */
    public function getUsage(array $filters = []): array
    {
        return $this->client->get(self::withTagFilter('/object-storage/usage', $filters));
    }

    /**
     * Build the query string with the "tags" filter as a repeated tag=... parameter, which is
     * what the API expects (http_build_query would send tags[0]=...).
     */
    private static function withTagFilter(string $path, array $filters): string
    {
        $tags = $filters['tags'] ?? [];
        unset($filters['tags']);
        $parts = [];
        if (!empty($filters)) {
            $parts[] = http_build_query($filters, '', '&', PHP_QUERY_RFC3986);
        }
        foreach ((array) $tags as $tag) {
            $parts[] = 'tag=' . rawurlencode((string) $tag);
        }
        return empty($parts) ? $path : $path . '?' . implode('&', $parts);
    }

    /**
     * Send "tags" as a JSON object: an empty PHP array would be encoded as [] instead of {}.
     */
    private static function tagsAsObject(array $params): array
    {
        if (array_key_exists('tags', $params) && is_array($params['tags'])) {
            $params['tags'] = (object) $params['tags'];
        }
        return $params;
    }

    // --- Charts ---

    /**
     * Chart series of a bucket, served through GraphQL: storage (size_bytes, objects, hourly),
     * traffic (egress_bytes, cdn_bytes, ingress_bytes, class_a_requests, class_b_requests,
     * free_requests) and responses (responses_2xx, responses_3xx, responses_4xx, responses_5xx,
     * responses_429, responses_other). Traffic and responses are totals per step, not rates.
     *
     * @param string $uuid
     * @param string $range H1, H3, H6, H12, H24 (default), D3, D7 or D30
     * @return array Contains uuid, name, storageMeasuredAt, storage, traffic, responses; each part
     *               has start, end, step, series[] (name, unit, points[] of ts, value)
     * @throws APIError 404 when the bucket does not exist
     */
    public function bucketMetrics(string $uuid, string $range = 'H24'): array
    {
        $result = 'start end step series { name unit points { ts value } }';
        $data = $this->client->graphql(
            'query($uuid: ID!, $range: TimeRange!) { objectStorageBucket(uuid: $uuid) { uuid name storageMeasuredAt '
                . "storage(range: \$range) { {$result} } traffic(range: \$range) { {$result} } "
                . "responses(range: \$range) { {$result} } } }",
            ['uuid' => $uuid, 'range' => $range]
        );
        if (empty($data['objectStorageBucket'])) {
            throw new APIError(404, "Bucket {$uuid} not found");
        }
        return $data['objectStorageBucket'];
    }
}
