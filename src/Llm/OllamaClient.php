<?php

declare(strict_types=1);

namespace App\Llm;

use App\Exception\TestGenerationException;
use App\Service\LlmJsonSanitizer;
use App\Service\OllamaService;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * @noinspection PhpUnused
 */
readonly class OllamaClient implements LlmClientInterface
{
    public function __construct(
        private OllamaService $ollamaService,
        private LlmJsonSanitizer $jsonSanitizer,
    ) {
    }

    /**
     * @throws TestGenerationException
     */
    public function call(array $messages, string $model): string
    {
        try {
            // Conversion du tableau de messages en chaîne textuelle pour Ollama
            $conversation = [];
            foreach ($messages as $message) {
                $prefix = match ($message['role']) {
                    'system' => 'Contexte Système',
                    'user' => 'Utilisateur',
                    'assistant' => 'Assistant',
                    default => 'Note',
                };
                $conversation[] = sprintf('%s: %s', $prefix, $message['content']);
            }

            // Génération du prompt complet pour Ollama.
            $fullPrompt = implode("\n\n", $conversation)."\n\nAssistant (Réponds au format JSON) :";

            // Génération du code de test par le LLM.
            $rawResponse = $this->ollamaService->generateResponse($fullPrompt, $model);

            // Nettoyage de la réponse pour obtenir du JSON valide.
            $rawResponse = $this->jsonSanitizer->sanitizeJson($rawResponse);

            return $this->jsonSanitizer->sanitizeRawJson($rawResponse);
        } catch (HttpExceptionInterface|DecodingExceptionInterface|TransportExceptionInterface $e) {
            throw new TestGenerationException('Erreur de communication API : '.$e->getMessage(), 0, $e);
        }
    }
}
