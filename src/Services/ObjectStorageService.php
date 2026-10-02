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
     *               encryption (null until applied, or algorithm "AES256" and scope
     *               "all_objects" or "new_objects"), ...)
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

    // --- Event notifications ---

    /**
     * List the organization's event destinations. Webhook URLs are only returned masked.
     *
     * @return array List of destinations (uuid, name, type, url_masked, notificator, payload_format,
     *               status, disabled_reason, previous_secret_expires_at, last_success_at,
     *               last_failure_at, last_error, rules_count, created_at)
     */
    public function listEventDestinations(): array
    {
        return $this->client->get('/object-storage/event-destinations');
    }

    /**
     * Create an event destination. The signing secret is only returned here and by
     * rotateEventDestinationSecret(): store it.
     *
     * @param array $params {
     *     @type string $name           Destination name (required)
     *     @type string $type           "webhook" or "notificator" (required)
     *     @type string $url            Webhook URL, https (webhook only)
     *     @type string $notificator_id Cloud Alerts channel (notificator only)
     *     @type string $payload_format "cubepath" (default) or "s3"
     * }
     * @return array Contains destination and signing_secret (null for a channel)
     */
    public function createEventDestination(array $params): array
    {
        return $this->client->post('/object-storage/event-destinations', $params);
    }

    public function getEventDestination(string $uuid): array
    {
        return $this->client->get('/object-storage/event-destinations/' . rawurlencode($uuid));
    }

    /**
     * @param array $params Any of name, url, payload_format, enabled
     */
    public function updateEventDestination(string $uuid, array $params): array
    {
        return $this->client->patch('/object-storage/event-destinations/' . rawurlencode($uuid), $params);
    }

    /**
     * Delete a destination without rules.
     */
    public function deleteEventDestination(string $uuid): array
    {
        return $this->client->delete('/object-storage/event-destinations/' . rawurlencode($uuid));
    }

    /**
     * Issue a new signing secret; the previous one keeps signing for 24 hours.
     *
     * @return array Contains destination, signing_secret and previous_secret_expires_at
     */
    public function rotateEventDestinationSecret(string $uuid): array
    {
        return $this->client->post('/object-storage/event-destinations/' . rawurlencode($uuid) . '/rotate-secret');
    }

    /**
     * Deliver a cubepath.ping now (the destination must be active); the outcome shows up in the
     * delivery history.
     *
     * @return array Contains detail
     */
    public function testEventDestination(string $uuid): array
    {
        return $this->client->post('/object-storage/event-destinations/' . rawurlencode($uuid) . '/test');
    }

    /**
     * A page of the delivery history of a destination, newest first.
     *
     * @param array $filters {
     *     @type string $status "success", "failed" or "dead" (optional)
     *     @type int    $limit  1 to 200, default 50 (optional)
     *     @type int    $before Unix milliseconds: pass next_before of the previous page (optional)
     * }
     * @return array Contains deliveries (ts, ts_ms, event_id, delivery_id, event_type, bucket_uuid,
     *               bucket_name, rule_uuid, object_key, attempt, status, http_status, latency_ms,
     *               error) and next_before (null on the last page)
     */
    public function listEventDeliveries(string $uuid, array $filters = []): array
    {
        return $this->client->get('/object-storage/event-destinations/' . rawurlencode($uuid) . '/deliveries', $filters);
    }

    /**
     * List the event rules of a bucket.
     *
     * @return array List of rules (uuid, name, bucket_uuid, destination, events, prefix, suffix,
     *               enabled, status, error_message, created_at)
     */
    public function listEventRules(string $bucketUuid): array
    {
        return $this->client->get('/object-storage/buckets/' . rawurlencode($bucketUuid) . '/event-rules');
    }

    /**
     * Create an event rule on a bucket. Applied asynchronously: status goes from "pending" to
     * "active".
     *
     * @param array $params {
     *     @type string   $name             Rule name (required)
     *     @type string   $destination_uuid Destination (required)
     *     @type string[] $events           object.created, object.removed, object.tagging (required)
     *     @type string   $prefix           Key prefix (optional)
     *     @type string   $suffix           Key suffix (optional)
     *     @type bool     $enabled          Default true
     * }
     */
    public function createEventRule(string $bucketUuid, array $params): array
    {
        return $this->client->post('/object-storage/buckets/' . rawurlencode($bucketUuid) . '/event-rules', $params);
    }

    public function updateEventRule(string $bucketUuid, string $ruleUuid, array $params): array
    {
        return $this->client->patch(
            '/object-storage/buckets/' . rawurlencode($bucketUuid) . '/event-rules/' . rawurlencode($ruleUuid),
            $params
        );
    }

    public function deleteEventRule(string $bucketUuid, string $ruleUuid): array
    {
        return $this->client->delete(
            '/object-storage/buckets/' . rawurlencode($bucketUuid) . '/event-rules/' . rawurlencode($ruleUuid)
        );
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
