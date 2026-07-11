<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\TestGenerationException;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class TestGenerator
{
    public function __construct(
        private HttpClientInterface $openRouterClient
    ) {}

    /**
     * @throws TestGenerationException
     */
    public function generateForClass(string $classCode, string $className): string
    {
        try {
            $response = $this->openRouterClient->request('POST', 'chat/completions', [
                'json' => [
                    'model' => 'google/gemini-pro-1.5',
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => "Tu es un expert PHPUnit et Symfony. Génère un test unitaire. Répond TOUJOURS sous forme d'un objet JSON contenant la clé 'test_code'."
                        ],
                        [
                            'role' => 'user',
                            'content' => "Génère le test pour cette classe ({$className}) :\n\n" . $classCode
                        ]
                    ]
                ]
            ]);

            // toArray() peut lever HttpExceptionInterface ou DecodingExceptionInterface
            $data = $response->toArray();

            if (!isset($data['choices'][0]['message']['content'])) {
                throw new TestGenerationException("La structure de réponse d'OpenRouter est invalide.");
            }

            // Décodage du JSON renvoyé par le LLM
            $content = json_decode($data['choices'][0]['message']['content'], true, 512, JSON_THROW_ON_ERROR);

            if (!isset($content['test_code'])) {
                throw new TestGenerationException("Le LLM n'a pas renvoyé la clé 'test_code' dans son JSON.");
            }

            return $content['test_code'];

        } catch (HttpExceptionInterface|DecodingExceptionInterface $e) {
            // Attrape les erreurs 404, 401, 500, etc.
            try {
                $statusCode = $e->getResponse()->getStatusCode();
                $message = sprintf("Erreur HTTP %d renvoyée par OpenRouter : %s", $statusCode, $e->getMessage());
            } catch (TransportExceptionInterface $transportError) {
                // Au cas où getStatusCode() échoue suite à un problème réseau soudain
                $message = "Erreur HTTP renvoyée par OpenRouter, mais impossible de récupérer le code statut : " . $transportError->getMessage();
            }

            throw new TestGenerationException($message, 0, $e);

        } catch (TransportExceptionInterface $e) {
            // Attrape les erreurs réseau globales (Timeout, hôte introuvable au départ...)
            throw new TestGenerationException("Erreur réseau lors de la communication avec OpenRouter : " . $e->getMessage(), 0, $e);

        } catch (\JsonException $e) {
            // Attrape le JSON invalide du LLM ou de la réponse globale
            throw new TestGenerationException("Échec du décodage JSON de la réponse : " . $e->getMessage(), 0, $e);
        }
    }
}
