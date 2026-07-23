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
readonly class GeminiClient implements LlmClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter] private string $apiKey,
    ) {
    }

    public function supports(string $provider): bool
    {
        return 'gemini' === strtolower($provider);
    }

    /**
     * @throws TestGenerationException
     */
    public function call(array $messages, string $model): string
    {
        if (empty($this->apiKey)) {
            throw new TestGenerationException("La clé d'API Gemini n'est pas configurée.");
        }

        // Nettoyage strict du nom du modèle : supprime 'models/', 'google/' ainsi que les guillemets/espaces superflus
        $cleanModel = preg_replace('#^(models/|google/)#', '', trim($model, " \t\n\r\0\x0B\"'"));

        // Séparation du message système et des messages de conversation
        $systemInstruction = null;
        $contents = [];

        foreach ($messages as $msg) {
            $role = $msg['role'];
            $content = $msg['content'];

            if ('' === trim($content)) {
                continue;
            }

            if ('system' === $role) {
                $systemInstruction = [
                    'parts' => [['text' => $content]],
                ];
                continue;
            }

            $geminiRole = ('assistant' === $role) ? 'model' : 'user';

            $contents[] = [
                'role' => $geminiRole,
                'parts' => [['text' => $content]],
            ];
        }

        if (empty($contents)) {
            throw new TestGenerationException("Aucun message valide à envoyer à l'API Gemini.");
        }

        // Construction du payload de la requête API Gemini
        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ];

        if (null !== $systemInstruction) {
            $payload['systemInstruction'] = $systemInstruction;
        }

        try {
            // Comme base_uri étant déclaré dans le fichier framework.yaml, on passe un chemin relatif sans slash au début.
            $endpoint = sprintf('v1beta/models/%s:generateContent', $cleanModel);

            $response = $this->httpClient->request('POST', $endpoint, [
                'query' => [
                    'key' => $this->apiKey,
                ],
                'json' => $payload,
            ]);

            // Récupération explicite en cas d'erreur HTTP pour avoir le détail exact de Google
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $errorBody = $response->getContent(false);
                throw new TestGenerationException(sprintf('Erreur API Gemini (%d) sur URL [%s] : %s', $statusCode, $response->getInfo('url'), $errorBody));
            }

            $data = $response->toArray();

            // Extraction du contenu de la réponse
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (null === $text) {
                throw new TestGenerationException('Structure de réponse invalide reçue de Gemini.');
            }

            return trim($text);
        } catch (HttpExceptionInterface|DecodingExceptionInterface|TransportExceptionInterface $e) {
            throw new TestGenerationException('Erreur de communication avec l\'API Gemini : '.$e->getMessage(), 0, $e);
        }
    }
}
