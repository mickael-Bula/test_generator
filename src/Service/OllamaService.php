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
     * @param array<int, array{role: string, content: string}> $messages
     *
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function generateResponse(array $messages, string $model): string
    {
        $response = $this->httpClient->request('POST', $this->ollamaApiUrl.'/api/chat', [
            'json' => [
                'model' => $model,
                'messages' => $messages, // Tableau des messages structurés (system + user)
                'stream' => false, // Désactive le streaming pour recevoir la réponse d'un bloc
                'format' => 'json', // Force Ollama à répondre un JSON valide au niveau de sa structure
                'options' => [
                    'num_predict' => 1024, // Force Ollama à ne PAS couper le code PHP au milieu
                    'num_ctx' => 4096, // Alloue assez de mémoire de contexte pour le code + historique
                    'temperature' => 0.0,  // Température basse pour limiter les hallucinations de syntaxe
                ],
            ],
            'timeout' => 600.0, // Laisse dix minutes au LLM pour répondre (réponse lente en local).
        ]);

        $data = $response->toArray();

        // L'API /api/chat retourne le texte dans ['message']['content'].
        return $data['message']['content'] ?? '';
    }
}
