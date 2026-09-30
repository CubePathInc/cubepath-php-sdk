<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

/**
 * Video Transcoder: read a video from a URL or an S3 compatible bucket and write the requested
 * outputs (files, HLS, thumbnails, GIF) to an S3 compatible bucket.
 *
 * Jobs run asynchronously: queued, analyzing, encoding, finalizing, then completed, failed or
 * canceled (poll getJob()). Secret keys are never returned.
 */
class TranscoderService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    /**
     * Create a transcoding job.
     *
     * @param array $params {
     *     @type array  $input           {source: "url"|"s3", url, s3: {endpoint, region, bucket, path,
     *                                   access_key, secret_key}} (required)
     *     @type array  $output          {s3: {endpoint, region, bucket, path, access_key, secret_key}} (required)
     *     @type array  $outputs         1-20 specs, each {type: "file"|"hls"|"thumbnails"|"gif", ...} (required)
     *     @type string $webhook_url     Called when the job ends (optional)
     *     @type string $idempotency_key The same key returns the original job (optional)
     * }
     * @return array The job (uuid, status, input, output, spec, outputs, progress, ...)
     */
    public function createJob(array $params): array
    {
        return $this->client->post('/transcoder/jobs', $params);
    }

    /**
     * Create up to 1000 jobs sharing the destination and output specs.
     *
     * @param array $params {
     *     @type array  $output         {s3: {...}} (required)
     *     @type array  $outputs        1-20 specs (required)
     *     @type array  $inputs         1-1000 items, each {url} or {s3} or {path} (with input_defaults.s3),
     *                                  plus out_subpath (required)
     *     @type array  $input_defaults {source: "s3", s3: {...}} for path items (optional)
     *     @type string $webhook_url    (optional)
     * }
     * @return array Contains batch_id, job_ids, count
     */
    public function createBatch(array $params): array
    {
        return $this->client->post('/transcoder/jobs/batch', $params);
    }

    /**
     * List jobs, newest first. There is no total: keep paging while a page is full.
     *
     * @param array $filters {
     *     @type string $batch_id Only jobs of this batch (optional)
     *     @type int    $limit    1-500 (optional, default 100)
     *     @type int    $offset   (optional, default 0)
     * }
     * @return array Contains jobs, limit, offset
     */
    public function listJobs(array $filters = []): array
    {
        return $this->client->get('/transcoder/jobs', $filters);
    }

    /**
     * Get a job.
     *
     * @param string $uuid
     * @return array
     */
    public function getJob(string $uuid): array
    {
        return $this->client->get("/transcoder/jobs/{$uuid}");
    }

    /**
     * Files produced by a job and the destination they were written to.
     *
     * @param string $uuid
     * @return array Contains outputs ([{type, bucket, key}]), destination
     */
    public function getJobOutputs(string $uuid): array
    {
        return $this->client->get("/transcoder/jobs/{$uuid}/outputs");
    }

    /**
     * Cancel a job that has not finished.
     *
     * @param string $uuid
     * @return array Contains detail, status
     */
    public function cancelJob(string $uuid): array
    {
        return $this->client->delete("/transcoder/jobs/{$uuid}");
    }
}
