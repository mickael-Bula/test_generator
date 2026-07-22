<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\TestCorrectionException;
use App\Exception\TestGenerationException;
use App\Llm\LlmClientInterface;
use App\RepoMap\RepoMapBuilder;

readonly class TestGenerator
{
    private const MAX_ATTEMPT = 3;

    public function __construct(
        private LlmClientInterface $llmClient,
        private RepoMapBuilder $repoMapBuilder,
        private LlmJsonSanitizer $jsonSanitizer,
        private PhpUnitTestRunner $testRunner,
        private PhpTestFileBuilder $testFileBuilder,
        private string $projectDir,
    ) {
    }

    /**
     * @throws TestGenerationException|TestCorrectionException
     */
    public function generateForClass(
        string $classCode,
        string $className,
        string $model,
        ?string $methodName = null,
        ?string $existingTestCode = null,
    ): string {
        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemMessage(),
            ],
            [
                'role' => 'user',
                'content' => $this->buildUserMessage($classCode, $className, $methodName, $existingTestCode),
            ],
        ];

        $attempt = 0;
        $rawContent = '';

        while ($attempt < self::MAX_ATTEMPT) {
            ++$attempt;

            try {
                $rawContent = $this->llmClient->call($messages, $model);

                $fixedJson = $this->jsonSanitizer->sanitizeRawJson($rawContent);
                $content = json_decode($fixedJson, true, 512, JSON_THROW_ON_ERROR);

                $testCode = $this->extractTestCodeFromPayload($content);

                // Exécution du test
                $result = $this->testRunner->runTest($testCode, $className);

                if ($result['success']) {
                    return $testCode;
                }

                // ÉCHEC DU TEST (Erreur PHPUnit) : on prépare le message pour la tentative suivante
                $errorMessage = sprintf("L'exécution de PHPUnit a échoué :\n\n%s", $result['output']);
            } catch (\JsonException|TestGenerationException $e) {
                // ÉCHEC DE STRUCTURE/JSON : si on n'a pas atteint le max, on prépare la relance
                if ($attempt >= self::MAX_ATTEMPT) {
                    $context = ' | Contenu brut reçu : '.substr($rawContent, 0, 150).'...';
                    $message = 'Échec critique lors de la tentative finale : '.$e->getMessage().$context;
                    throw new TestGenerationException($message, 0, $e);
                }

                $errorMessage = sprintf("Ta réponse n'était pas un JSON valide ou ne respectait pas le format. Erreur : %s", $e->getMessage());
            }

            // 3. Enrichissement de l'historique (Partagé pour PHPUnit ET erreurs JSON).
            $messages[] = ['role' => 'assistant', 'content' => $rawContent];
            $messages[] = [
                'role' => 'user',
                'content' => $errorMessage."\n\nAnalyse ce problème, corrige ton code et renvoie le JSON attendu.",
            ];
        }

        throw new TestCorrectionException(sprintf('Impossible de générer un test valide pour %s après %d tentatives.', $className, self::MAX_ATTEMPT));
    }

    /**
     * @param array<string, mixed> $content
     *
     * @throws TestGenerationException
     */
    private function extractTestCodeFromPayload(array $content): string
    {
        // Cas 1 : Le LLM a renvoyé un fichier complet
        if (isset($content['test_code'])) {
            return $this->jsonSanitizer->sanitizePhpCode($content['test_code']);
        }

        // Cas 2 : Le LLM a renvoyé des fragments à assembler
        if (isset($content['methods'], $content['namespace'], $content['class'])) {
            return $this->testFileBuilder->buildFromFragments($content);
        }

        // Si le JSON est valide, mais sans les bonnes clés, on l'assimile à un échec de structure.
        throw new TestGenerationException("Le format JSON généré par le LLM n'est pas reconnu.");
    }

    /**
     * Construit le prompt système global en y injectant le Repo-Map.
     */
    private function buildSystemMessage(): string
    {
        $systemMessage = 'Tu es un expert PHPUnit et Symfony. Génère un test unitaire complet. '
            ."Tu dois TOUJOURS répondre sous la forme d'un objet JSON contenant une seule clé nommée 'test_code'. "
            ."La valeur de 'test_code' doit être une chaîne de caractères contenant l'intégralité du code PHP "
            .'valide du fichier de test (commençant par <?php).';

        // Génération et injection du Repo-Map
        $repoMap = $this->repoMapBuilder->buildMap($this->projectDir.'/src');

        if (!empty($repoMap)) {
            $systemMessage .= "\n\nSTRUCTURE DU PROJET (REPO MAP) POUR T'AIDER À MOCKER ET COMPRENDRE LES DÉPENDANCES :\n"
                ."```text\n".$repoMap."\n```";
        }

        return $systemMessage;
    }

    /**
     * Construit le message utilisateur avec le code à tester et les contraintes métier.
     */
    private function buildUserMessage(
        string $classCode,
        string $className,
        ?string $methodName,
        ?string $existingTestCode,
    ): string {
        $roleUserMessage = "Génère le code du test PHPUnit pour la classe {$className} suivante :\n\n".$classCode;

        $roleUserMessage .= "\n\n⚠️ CONFIGURATION OBLIGATOIRE DU FICHIER DE TEST :\n"
            ."- Espace de noms (namespace) : App\\Tests\\Dynamic\n"
            .sprintf("- Nom de la classe de test : %sDynamicTest\n", $className);

        if (null !== $existingTestCode) {
            $roleUserMessage .= "\n⚠️ UN FICHIER DE TEST EXISTE DÉJÀ POUR CETTE CLASSE !\n";
            $roleUserMessage .= "Tu dois impérativement FUSIONNER tes nouveaux tests avec le code existant fourni ci-dessous.\n";
            $roleUserMessage .= "Consignes de fusion :\n";

            if (null !== $methodName) {
                $roleUserMessage .= sprintf(
                    "- **Règle d'Idempotence (Priorité Haute) :** Inspecte minutieusement le code existant ci-dessous. Si des méthodes de test couvrant déjà spécifiquement la méthode `%s()` sont présentes :\n"
                    ."  a. NE DUPLIQUE PAS les tests. N'ajoute pas de méthodes ayant le même but ou des noms redondants.\n"
                    ."  b. Évalue si tes nouvelles propositions de tests apportent une réelle valeur ajoutée (ex. un cas limite oublié). Si oui, mets à jour ou remplace proprement les tests existants de cette méthode.\n"
                    ."  c. Si les tests existants pour cette méthode sont déjà complets et optimaux, renvoie le fichier d'origine sans le modifier inutilement.\n",
                    $methodName
                );
                $roleUserMessage .= "- **Pour les autres méthodes :** Ne supprime et ne modifie AUCUN des tests existants qui concernent d'autres méthodes de la classe.\n";
            } else {
                $roleUserMessage .= "- Ne supprime et ne modifie AUCUN des tests existants.\n";
            }

            $roleUserMessage .= "- S'il s'agit de nouveaux scénarios à ajouter, insère la ou les nouvelles méthodes de test à la suite.\n";
            $roleUserMessage .= "- Si nécessaire, fusionne proprement le contenu de la méthode `setUp()` sans casser l'existant.\n";
            $roleUserMessage .= "- Combine les déclarations `use` en haut du fichier si tu ajoutes de nouvelles dépendances.\n";
            $roleUserMessage .= sprintf("- Conserve temporairement la configuration de classe exigée (class %sDynamicTest).\n", $className);
            $roleUserMessage .= sprintf("\nVoici le code du test existant à enrichir :\n```php\n%s\n```\n", $existingTestCode);
        } else {
            $roleUserMessage .= "\nGénère un nouveau fichier de test complet à partir de zéro.\n";

            if (null !== $methodName) {
                $roleUserMessage .= sprintf(
                    "\n🎯 ATTENTION : Concentre-toi PRIORITAIREMENT et UNIQUEMENT sur les scénarios de test "
                    .'pour la méthode "%s()". Ne génère pas de tests pour les autres méthodes afin de rester concis.\n',
                    $methodName
                );
            }
        }

        return $roleUserMessage;
    }
}
