<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

class SSHKeyService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    /**
     * List all SSH keys.
     *
     * @return array
     */
    public function list(): array
    {
        return $this->client->get('/sshkey/user/sshkeys');
    }

    /**
     * Create a new SSH key.
     *
     * @param string $name   Key name/label
     * @param string $sshKey Public key content (OpenSSH format)
     * @return array Contains detail, ssh_key_id, name, key_type, fingerprint
     */
    public function create(string $name, string $sshKey): array
    {
        return $this->client->post('/sshkey/create', [
            'name' => $name,
            'ssh_key' => $sshKey,
        ]);
    }

    /**
     * Delete an SSH key.
     *
     * @param int $keyId
     * @return array
     */
    public function delete(int $keyId): array
    {
        return $this->client->delete("/sshkey/{$keyId}");
    }

    /**
     * Rename an SSH key.
     *
     * @param int    $keyId
     * @param string $name
     * @return array Contains detail, sshkey
     */
    public function update(int $keyId, string $name): array
    {
        return $this->client->put("/sshkey/{$keyId}", [
            'name' => $name,
        ]);
    }
}
