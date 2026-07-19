<?php

declare(strict_types=1);

namespace App\Llm;

use App\Exception\TestGenerationException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @noinspection PhpUnused
 */
readonly class OpenRouterClient implements LlmClientInterface
{
    public function __construct(
        // Injection du client configuré dans le fichier services.yaml.
        private HttpClientInterface $openRouterClient,
    ) {
    }

    /**
     * @throws TestGenerationException
     */
    public function call(array $messages, string $model): string
    {
        try {
            $response = $this->openRouterClient->request('POST', 'chat/completions', [
                'json' => [
                    'model' => $model,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => $messages,
                ],
            ]);

            $data = $response->toArray();

            return trim($data['choices'][0]['message']['content'] ?? throw new TestGenerationException('Structure invalide.'));
        } catch (HttpExceptionInterface|DecodingExceptionInterface|TransportExceptionInterface $e) {
            throw new TestGenerationException('Erreur de communication API : '.$e->getMessage(), 0, $e);
        }
    }
}
