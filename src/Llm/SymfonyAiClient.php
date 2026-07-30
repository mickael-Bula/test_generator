<?php

declare(strict_types=1);

namespace App\Llm;

use App\Dto\GeneratedTestResult;
use App\Exception\TestGenerationException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @noinspection PhpUnused
 */
readonly class SymfonyAiClient implements LlmClientInterface
{
    /**
     * @param ServiceLocator<PlatformInterface> $platforms
     */
    public function __construct(
        // On indique d'indexer le ServiceLocator avec la colonne "name" du tag, correspondant aux noms des providers.
        #[AutowireLocator('ai.platform', indexAttribute: 'name')]
        private ServiceLocator $platforms,
        private string $defaultProvider = 'gemini',
        private string $defaultModel = 'gemini-2.5-flash-lite',
    ) {
    }

    public function supports(string $provider): bool
    {
        return $this->platforms->has(strtolower($provider));
    }

    /**
     * @param array<array{role: string, content: string}> $messages
     *
     * @throws TestGenerationException
     */
    public function call(array $messages, string $model): string
    {
        $provider = strtolower($this->defaultProvider);
        $targetModel = '' !== trim($model) ? $model : $this->defaultModel;

        if (!$this->platforms->has($provider)) {
            throw new TestGenerationException(sprintf('Le fournisseur "%s" n\'est pas configuré dans Symfony AI.', $provider));
        }

        /** @var PlatformInterface $platform */
        $platform = $this->platforms->get($provider);

        // Conversion des messages vers le format MessageBag de Symfony AI
        $messageBag = new MessageBag();
        foreach ($messages as $msg) {
            $content = trim($msg['content']);
            if ('' === $content) {
                continue;
            }

            match ($msg['role']) {
                'system' => $messageBag->add(Message::forSystem($content)),
                'assistant' => $messageBag->add(Message::ofAssistant($content)),
                default => $messageBag->add(Message::ofUser($content)),
            };
        }

        try {
            // 1. Invocation de la plateforme
            $result = $platform->invoke(
                $targetModel,
                $messageBag
            );

            // 2. Extraction du texte brut
            $rawContent = trim($result->asText());

            // 3. Nettoyage des balises Markdown (```json ... ``` ou ```php ... ```)
            $cleanContent = preg_replace('/^```(?:json|php)?\s*/i', '', $rawContent);
            $cleanContent = preg_replace('/\s*```$/', '', $cleanContent);
            $cleanContent = trim($cleanContent);

            // 4. Extraction et instanciation du DTO
            $decoded = json_decode($cleanContent, true, 512, JSON_THROW_ON_ERROR);

            $testResult = is_array($decoded) && isset($decoded['test_code'])
                ? new GeneratedTestResult($decoded['test_code']) // Le LLM a bien répondu avec la structure JSON demandée
                : new GeneratedTestResult($cleanContent); // Fallback : Le LLM a renvoyé directement du code PHP brut

            // 5. Retour du code PHP propre
            return $testResult->getCleanTestCode();
        } catch (\Throwable $e) {
            $message = sprintf(
                'Erreur lors de la génération avec Symfony AI (%s/%s) : %s',
                $provider,
                $targetModel,
                $e->getMessage()
            );
            throw new TestGenerationException($message, 0, $e);
        }
    }
}
