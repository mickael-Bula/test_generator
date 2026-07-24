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
            // On passe le DTO dans les options de la requête
            $result = $platform->invoke(
                $targetModel,
                $messageBag,
                ['response_format' => GeneratedTestResult::class]
            );

            /** @var GeneratedTestResult $testResult */
            $testResult = $result->asObject();

            return $testResult->getCleanTestCode();
        } catch (\Throwable $e) {
            throw new TestGenerationException(sprintf('Erreur lors de la génération avec Symfony AI (%s/%s) : %s', $provider, $targetModel, $e->getMessage()), 0, $e);
        }
    }
}
