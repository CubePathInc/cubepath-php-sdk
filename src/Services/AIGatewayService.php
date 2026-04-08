<?php

namespace Cubepath\Services;

use Cubepath\CubepathClient;

class AIGatewayService
{
    private CubepathClient $client;

    public function __construct(CubepathClient $client)
    {
        $this->client = $client;
    }

    /**
     * List all available AI models with pricing and capabilities.
     *
     * @return array ModelListResponse with object and data[]
     */
    public function listModels(): array
    {
        $baseUrl = $this->client->getAIGatewayBaseUrl();
        return $this->client->requestWithUrl('GET', $baseUrl . '/models');
    }

    /**
     * Send a chat completion request (non-streaming).
     *
     * @param array $params {
     *     @type string $model    Model in "provider/model_id" format
     *     @type array  $messages Array of {role, content} messages
     *     @type float  $temperature    (optional)
     *     @type float  $top_p          (optional)
     *     @type int    $n              (optional)
     *     @type array  $stop           (optional)
     *     @type int    $max_tokens     (optional)
     *     @type float  $presence_penalty  (optional)
     *     @type float  $frequency_penalty (optional)
     *     @type string $user           (optional)
     *     @type array  $tools          (optional)
     *     @type mixed  $tool_choice    (optional)
     *     @type mixed  $response_format (optional)
     * }
     * @return array ChatCompletionResponse
     */
    public function chatCompletion(array $params): array
    {
        $params['stream'] = false;
        $baseUrl = $this->client->getAIGatewayBaseUrl();
        return $this->client->requestWithUrl('POST', $baseUrl . '/chat/completions', $params);
    }

    /**
     * Send a streaming chat completion request.
     * Returns the raw SSE response body as a string.
     *
     * Note: For full streaming support (chunk-by-chunk processing),
     * use a custom HTTP client with stream handling.
     *
     * @param array $params Same as chatCompletion
     * @return string Raw SSE response
     */
    public function chatCompletionStream(array $params): string
    {
        $params['stream'] = true;
        $baseUrl = $this->client->getAIGatewayBaseUrl();
        return $this->client->requestRawWithUrl('POST', $baseUrl . '/chat/completions', $params);
    }
}
