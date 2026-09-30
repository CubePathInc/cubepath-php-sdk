<?php

namespace Cubepath\Services;

use Cubepath\APIError;
use Cubepath\CubepathClient;

class ProjectService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    /**
     * List all projects (with their resources).
     *
     * @return array Array of project responses with vps[], networks[], baremetals[]
     */
    public function list(): array
    {
        return $this->client->get('/projects/');
    }

    /**
     * Get a specific project.
     *
     * The API has no single-project endpoint, so the project is looked up in the list.
     *
     * @param int $projectId
     * @return array Project with nested resources (keys project, vps, baremetals, networks)
     * @throws APIError 404 when the project does not exist
     */
    public function get(int $projectId): array
    {
        foreach ($this->list() as $entry) {
            if ((int) ($entry['project']['id'] ?? 0) === $projectId) {
                return $entry;
            }
        }
        throw new APIError(404, "Project {$projectId} not found");
    }

    /**
     * Create a new project.
     *
     * @param string      $name
     * @param string|null $description
     * @return array
     */
    public function create(string $name, ?string $description = null): array
    {
        $params = ['name' => $name];
        if ($description !== null) {
            $params['description'] = $description;
        }
        return $this->client->post('/projects/', $params);
    }

    /**
     * Delete a project.
     *
     * @param int $projectId
     * @return array
     */
    public function delete(int $projectId): array
    {
        return $this->client->delete("/projects/{$projectId}");
    }
}
