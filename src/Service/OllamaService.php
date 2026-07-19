<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class OllamaService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $ollamaApiUrl,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function generateResponse(string $prompt, string $model = 'qwen2.5-coder:1.5b'): string
    {
        $response = $this->httpClient->request('POST', $this->ollamaApiUrl.'/api/generate', [
            'json' => [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false, // Désactive le streaming pour recevoir la réponse d'un bloc
                'format' => 'json', // Force Ollama à répondre un JSON valide au niveau de sa structure
            ],
            'timeout' => 600,
        ]);

        $data = $response->toArray();

        return $data['response'] ?? '';
    }
}
