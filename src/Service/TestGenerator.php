<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\TestGenerationException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

readonly class TestGenerator
{
    public function __construct(
        private string              $model,
        private HttpClientInterface $openRouterClient,
        private LlmJsonSanitizer    $jsonSanitizer,
        private PhpTestFileBuilder  $testFileBuilder,
    ) {}

    /**
     * @throws TestGenerationException
     */
    public function generateForClass(string $classCode, string $className): string
    {
        // 1. On donne une consigne stricte sur la structure JSON attendue
        $roleSystemMessage = "Tu es un expert PHPUnit et Symfony. Génère un test unitaire complet. "
            . "Tu dois TOUJOURS répondre sous la forme d'un objet JSON contenant une seule clé nommée 'test_code'. "
            . "La valeur de 'test_code' doit être une chaîne de caractères contenant l'intégralité du code PHP "
            . "valide du fichier de test (commençant par <?php).";

        $roleUserMessage = "Génère le code du test PHPUnit pour la classe {$className} suivante :\n\n" . $classCode;

        try {
            $response = $this->openRouterClient->request('POST', 'chat/completions', [
                'json' => [
                    'model' => $this->model,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $roleSystemMessage,
                        ],
                        [
                            'role' => 'user',
                            'content' => $roleUserMessage,
                        ],
                    ],
                ],
            ]);

            // toArray() peut lever HttpExceptionInterface ou DecodingExceptionInterface
            $data = $response->toArray();

            if (!isset($data['choices'][0]['message']['content'])) {
                throw new TestGenerationException("La structure de réponse d'OpenRouter est invalide.");
            }

            $rawContent = trim($data['choices'][0]['message']['content']);

            // 1. Nettoyage du JSON brut via notre nouveau service dédié
            $fixedJson = $this->jsonSanitizer->sanitizeRawJson($rawContent);

            // 2. Décodage du JSON valide
            $content = json_decode($fixedJson, true, 512, JSON_THROW_ON_ERROR);

            // --- CAS A : Le modèle a respecté la consigne standard et renvoyé la clé 'test_code' ---
            if (isset($content['test_code'])) {
                return $this->jsonSanitizer->sanitizePhpCode($content['test_code']);
            }

            // --- CAS B : Structure éclatée (class, namespace, uses, methods) déléguée au Builder ---
            if (isset($content['methods'], $content['namespace'], $content['class'])) {
                return $this->testFileBuilder->buildFromFragments($content);
            }

            throw new TestGenerationException("Le format JSON généré par le LLM n'est pas reconnu.");

        } catch (HttpExceptionInterface|DecodingExceptionInterface $e) {
            try {
                $statusCode = $e->getResponse()->getStatusCode();
                $message = sprintf(
                    "Erreur HTTP %d renvoyée par OpenRouter : %s",
                    $statusCode,
                    $e->getMessage()
                );
            } catch (TransportExceptionInterface $transportError) {
                $message = "Erreur HTTP renvoyée par OpenRouter, mais impossible de récupérer le code statut : "
                    . $transportError->getMessage();
            }
            throw new TestGenerationException($message, 0, $e);

        } catch (TransportExceptionInterface $e) {
            throw new TestGenerationException(
                "Erreur réseau lors de la communication avec OpenRouter : " . $e->getMessage(),
                0,
                $e
            );

        } catch (\JsonException $e) {
            $context = isset($rawContent)
                ? " | Contenu brut reçu : " . substr($rawContent, 0, 150) . "..."
                : "";

            throw new TestGenerationException(
                "Échec du décodage JSON : " . $e->getMessage() . $context,
                0,
                $e
            );
        }
    }
}
