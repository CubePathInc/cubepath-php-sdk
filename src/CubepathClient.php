<?php

namespace Cubepath;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use Cubepath\Services\VPSService;
use Cubepath\Services\DNSService;
use Cubepath\Services\SSHKeyService;
use Cubepath\Services\FirewallService;
use Cubepath\Services\FloatingIPService;
use Cubepath\Services\NetworkService;
use Cubepath\Services\ProjectService;
use Cubepath\Services\PricingService;
use Cubepath\Services\BaremetalService;
use Cubepath\Services\LoadBalancerService;
use Cubepath\Services\CDNService;
use Cubepath\Services\KubernetesService;
use Cubepath\Services\DDoSService;
use Cubepath\Services\AIGatewayService;
use Cubepath\Services\NatGatewayService;
use Cubepath\Services\ObjectStorageService;

class CubepathClient
{
    const VERSION = '0.5.0';
    const DEFAULT_BASE_URL = 'https://api.cubepath.com';
    const DEFAULT_AI_GATEWAY_BASE_URL = 'https://ai-gateway.cubepath.com';
    const DEFAULT_TIMEOUT = 30;
    const DEFAULT_MAX_RETRIES = 3;
    const DEFAULT_RETRY_WAIT_MIN = 1.0;
    const DEFAULT_RETRY_WAIT_MAX = 30.0;
    const DEFAULT_RATE_LIMIT_PER_SEC = 10;

    private HttpClient $httpClient;
    private string $apiToken;
    private string $baseUrl;
    private string $aiGatewayBaseUrl;
    private string $userAgent;
    private int $maxRetries;
    private float $retryWaitMin;
    private float $retryWaitMax;
    private int $rateLimitPerSec;
    private float $lastRequestTime = 0;
    private float $rateLimitInterval;

    private ?VPSService $vps = null;
    private ?DNSService $dns = null;
    private ?SSHKeyService $sshKeys = null;
    private ?FirewallService $firewall = null;
    private ?FloatingIPService $floatingIPs = null;
    private ?NetworkService $networks = null;
    private ?ProjectService $projects = null;
    private ?PricingService $pricing = null;
    private ?BaremetalService $baremetal = null;
    private ?LoadBalancerService $loadBalancer = null;
    private ?CDNService $cdn = null;
    private ?KubernetesService $kubernetes = null;
    private ?DDoSService $ddos = null;
    private ?AIGatewayService $aiGateway = null;
    private ?NatGatewayService $natGateway = null;
    private ?ObjectStorageService $objectStorage = null;

    public function __construct(string $apiToken, array $options = [])
    {
        if (empty($apiToken)) {
            throw new \InvalidArgumentException('API token is required');
        }

        $this->apiToken = $apiToken;
        $this->baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $this->aiGatewayBaseUrl = rtrim($options['ai_gateway_base_url'] ?? self::DEFAULT_AI_GATEWAY_BASE_URL, '/');
        $this->userAgent = $options['user_agent'] ?? 'cubepath-sdk-php/' . self::VERSION;
        $this->maxRetries = $options['max_retries'] ?? self::DEFAULT_MAX_RETRIES;
        $this->retryWaitMin = $options['retry_wait_min'] ?? self::DEFAULT_RETRY_WAIT_MIN;
        $this->retryWaitMax = $options['retry_wait_max'] ?? self::DEFAULT_RETRY_WAIT_MAX;
        $this->rateLimitPerSec = $options['rate_limit_per_sec'] ?? self::DEFAULT_RATE_LIMIT_PER_SEC;
        $this->rateLimitInterval = 1.0 / $this->rateLimitPerSec;

        $this->httpClient = $options['http_client'] ?? new HttpClient([
            'timeout' => $options['timeout'] ?? self::DEFAULT_TIMEOUT,
            'http_errors' => false,
        ]);
    }

    public function getApiToken(): string
    {
        return $this->apiToken;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getAIGatewayBaseUrl(): string
    {
        return $this->aiGatewayBaseUrl;
    }

    // --- HTTP Methods ---

    public function get(string $path, array $query = []): array
    {
        $options = [];
        if (!empty($query)) {
            $options['query'] = $query;
        }
        return $this->request('GET', $this->baseUrl . $path, $options);
    }

    public function post(string $path, ?array $body = null): array
    {
        $options = [];
        if ($body !== null) {
            $options['json'] = $body;
        }
        return $this->request('POST', $this->baseUrl . $path, $options);
    }

    public function put(string $path, ?array $body = null): array
    {
        $options = [];
        if ($body !== null) {
            $options['json'] = $body;
        }
        return $this->request('PUT', $this->baseUrl . $path, $options);
    }

    public function patch(string $path, ?array $body = null): array
    {
        $options = [];
        if ($body !== null) {
            $options['json'] = $body;
        }
        return $this->request('PATCH', $this->baseUrl . $path, $options);
    }

    public function delete(string $path): array
    {
        return $this->request('DELETE', $this->baseUrl . $path);
    }

    public function getRaw(string $path): string
    {
        return $this->requestRaw('GET', $this->baseUrl . $path);
    }

    /**
     * Perform a request against a custom base URL (used by AIGateway).
     */
    public function requestWithUrl(string $method, string $url, ?array $body = null): array
    {
        $options = [];
        if ($body !== null) {
            $options['json'] = $body;
        }
        return $this->request($method, $url, $options);
    }

    public function requestRawWithUrl(string $method, string $url, ?array $body = null): string
    {
        $options = [];
        if ($body !== null) {
            $options['json'] = $body;
        }
        return $this->requestRaw($method, $url, $options);
    }

    private function request(string $method, string $url, array $options = []): array
    {
        $options = $this->addHeaders($options);
        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            if ($attempt > 0) {
                $wait = $this->calculateBackoff($attempt);
                usleep((int)($wait * 1_000_000));
            }

            $this->rateLimit();

            try {
                $response = $this->httpClient->request($method, $url, $options);
                $statusCode = $response->getStatusCode();
                $body = (string) $response->getBody();

                if ($statusCode >= 200 && $statusCode < 300) {
                    if (empty($body)) {
                        return [];
                    }
                    $decoded = json_decode($body, true);
                    return $decoded ?? [];
                }

                $error = APIError::fromResponse($statusCode, $body);

                if ($this->shouldRetry($statusCode) && $attempt < $this->maxRetries) {
                    $lastException = $error;
                    continue;
                }

                throw $error;
            } catch (ConnectException $e) {
                $lastException = new APIError(0, $e->getMessage(), 'Connection error: ' . $e->getMessage());
                if ($attempt < $this->maxRetries) {
                    continue;
                }
                throw $lastException;
            } catch (APIError $e) {
                throw $e;
            }
        }

        throw $lastException ?? new APIError(0, 'Max retries exceeded');
    }

    private function requestRaw(string $method, string $url, array $options = []): string
    {
        $options = $this->addHeaders($options);
        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            if ($attempt > 0) {
                $wait = $this->calculateBackoff($attempt);
                usleep((int)($wait * 1_000_000));
            }

            $this->rateLimit();

            try {
                $response = $this->httpClient->request($method, $url, $options);
                $statusCode = $response->getStatusCode();
                $body = (string) $response->getBody();

                if ($statusCode >= 200 && $statusCode < 300) {
                    return $body;
                }

                $error = APIError::fromResponse($statusCode, $body);

                if ($this->shouldRetry($statusCode) && $attempt < $this->maxRetries) {
                    $lastException = $error;
                    continue;
                }

                throw $error;
            } catch (ConnectException $e) {
                $lastException = new APIError(0, $e->getMessage(), 'Connection error: ' . $e->getMessage());
                if ($attempt < $this->maxRetries) {
                    continue;
                }
                throw $lastException;
            } catch (APIError $e) {
                throw $e;
            }
        }

        throw $lastException ?? new APIError(0, 'Max retries exceeded');
    }

    private function addHeaders(array $options): array
    {
        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => "Bearer {$this->apiToken}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
        ]);
        $options['http_errors'] = false;
        return $options;
    }

    private function rateLimit(): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->lastRequestTime;
        if ($elapsed < $this->rateLimitInterval) {
            usleep((int)(($this->rateLimitInterval - $elapsed) * 1_000_000));
        }
        $this->lastRequestTime = microtime(true);
    }

    private function shouldRetry(int $statusCode): bool
    {
        return $statusCode === 429 || $statusCode >= 500;
    }

    private function calculateBackoff(int $attempt): float
    {
        $backoff = $this->retryWaitMin * pow(2, $attempt - 1);
        $backoff = min($backoff, $this->retryWaitMax);
        $jitter = $backoff * 0.5 * (mt_rand() / mt_getrandmax());
        return $backoff + $jitter;
    }

    // --- Services ---

    public function vps(): VPSService
    {
        if ($this->vps === null) {
            $this->vps = new VPSService($this);
        }
        return $this->vps;
    }

    public function dns(): DNSService
    {
        if ($this->dns === null) {
            $this->dns = new DNSService($this);
        }
        return $this->dns;
    }

    public function sshKeys(): SSHKeyService
    {
        if ($this->sshKeys === null) {
            $this->sshKeys = new SSHKeyService($this);
        }
        return $this->sshKeys;
    }

    public function firewall(): FirewallService
    {
        if ($this->firewall === null) {
            $this->firewall = new FirewallService($this);
        }
        return $this->firewall;
    }

    public function floatingIPs(): FloatingIPService
    {
        if ($this->floatingIPs === null) {
            $this->floatingIPs = new FloatingIPService($this);
        }
        return $this->floatingIPs;
    }

    public function networks(): NetworkService
    {
        if ($this->networks === null) {
            $this->networks = new NetworkService($this);
        }
        return $this->networks;
    }

    public function projects(): ProjectService
    {
        if ($this->projects === null) {
            $this->projects = new ProjectService($this);
        }
        return $this->projects;
    }

    public function pricing(): PricingService
    {
        if ($this->pricing === null) {
            $this->pricing = new PricingService($this);
        }
        return $this->pricing;
    }

    public function baremetal(): BaremetalService
    {
        if ($this->baremetal === null) {
            $this->baremetal = new BaremetalService($this);
        }
        return $this->baremetal;
    }

    public function loadBalancer(): LoadBalancerService
    {
        if ($this->loadBalancer === null) {
            $this->loadBalancer = new LoadBalancerService($this);
        }
        return $this->loadBalancer;
    }

    public function cdn(): CDNService
    {
        if ($this->cdn === null) {
            $this->cdn = new CDNService($this);
        }
        return $this->cdn;
    }

    public function kubernetes(): KubernetesService
    {
        if ($this->kubernetes === null) {
            $this->kubernetes = new KubernetesService($this);
        }
        return $this->kubernetes;
    }

    public function ddos(): DDoSService
    {
        if ($this->ddos === null) {
            $this->ddos = new DDoSService($this);
        }
        return $this->ddos;
    }

    public function aiGateway(): AIGatewayService
    {
        if ($this->aiGateway === null) {
            $this->aiGateway = new AIGatewayService($this);
        }
        return $this->aiGateway;
    }

    public function natGateway(): NatGatewayService
    {
        if ($this->natGateway === null) {
            $this->natGateway = new NatGatewayService($this);
        }
        return $this->natGateway;
    }

    public function objectStorage(): ObjectStorageService
    {
        if ($this->objectStorage === null) {
            $this->objectStorage = new ObjectStorageService($this);
        }
        return $this->objectStorage;
    }
}
