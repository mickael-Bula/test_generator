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

    public function supports(string $provider): bool
    {
        return 'ollama' === strtolower($provider);
    }

    /**
     * @throws TestGenerationException
     */
    public function call(array $messages, string $model): string
    {
        try {
            // Génération du code de test par le LLM.
            $rawResponse = $this->ollamaService->generateResponse($messages, $model);

            // Nettoyage de la réponse pour obtenir du JSON valide.
            $rawResponse = $this->jsonSanitizer->sanitizeJson($rawResponse);

            return $this->jsonSanitizer->sanitizeRawJson($rawResponse);
        } catch (HttpExceptionInterface|DecodingExceptionInterface|TransportExceptionInterface $e) {
            throw new TestGenerationException('Erreur de communication API : '.$e->getMessage(), 0, $e);
        }
    }
}
