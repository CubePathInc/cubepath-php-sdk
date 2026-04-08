<?php

namespace Cubepath\Services;

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
        return $this->client->get('/projects');
    }

    /**
     * Get a specific project.
     *
     * @param int $projectId
     * @return array Project with nested resources
     */
    public function get(int $projectId): array
    {
        return $this->client->get("/projects/{$projectId}");
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
        return $this->client->post('/projects', $params);
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
