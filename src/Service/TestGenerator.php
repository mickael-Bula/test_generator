<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\TestCorrectionException;
use App\Llm\LlmClientFactory;
use App\RepoMap\RepoMapBuilder;

readonly class TestGenerator
{
    private const MAX_ATTEMPT = 3;

    public function __construct(
        private LlmClientFactory $llmFactory,
        private RepoMapBuilder $repoMapBuilder,
        private PhpUnitTestRunner $testRunner,
        private string $projectDir,
    ) {
    }

    /**
     * @throws TestCorrectionException
     */
    public function generateForClass(
        string $classCode,
        string $className,
        ?string $model = null,
        ?string $methodName = null,
        ?string $existingTestCode = null,
        ?string $specContent = null,
    ): string {
        // On résout le client et le modèle à l'aide de la Factory
        $client = $this->llmFactory->getClient();
        $targetModel = $model ?? $this->llmFactory->getDefaultModel();

        $messages = [
            [
                'role' => 'system',
                'content' => $this->buildSystemMessage(),
            ],
            [
                'role' => 'user',
                'content' => $this->buildUserMessage($classCode, $className, $methodName, $existingTestCode, $specContent),
            ],
        ];

        $attempt = 0;
        $rawContent = '';

        while ($attempt < self::MAX_ATTEMPT) {
            ++$attempt;

            $testCode = $client->call($messages, $targetModel);

            // Exécution du test
            $result = $this->testRunner->runTest($testCode, $className);

            if ($result['success']) {
                return $testCode;
            }

            // ÉCHEC DU TEST (Erreur PHPUnit) : on prépare le message pour la tentative suivante
            $errorMessage = sprintf("L'exécution de PHPUnit a échoué :\n\n%s", $result['output']);

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
     * Construit le prompt système global en y injectant le Repo-Map.
     * Utilisation de <<<'TEXT' (NOWDOC) pour que PHP traite tout le bloc de texte
     * comme une chaîne de caractères strictement littérale : pas d'interprétation des variables.
     * Tout ce qui commence par un $ est ignoré par le parseur PHP.
     *
     * Sans les guillemets (HEREDOC), le parser PHP interprête
     * ce qui commence par $ comme une variable et le traite comme une expression.
     */
    private function buildSystemMessage(): string
    {
        $systemMessage = <<<'TEXT'
Tu es un expert PHPUnit et Symfony. Génère un test unitaire complet.
Tu dois TOUJOURS répondre sous la forme d'un objet JSON contenant une seule clé nommée 'test_code'.
La valeur de 'test_code' doit être une chaîne de caractères contenant l'intégralité du code PHP valide du fichier de test (commençant par <?php).
TEXT;

        // Génération et injection du Repo-Map
        $repoMap = $this->repoMapBuilder->buildMap($this->projectDir.'/src');

        if (!empty($repoMap)) {
            $systemMessage .= "\n\n"
                ."STRUCTURE DU PROJET (REPO-MAP) :\n"
                ."```text\n".$repoMap."\n```\n\n"
                ."CONSIGNE SUR LA REPO-MAP :\n"
                .'- Utilise obligatoirement cette cartographie pour vérifier les namespaces exacts, '
                ."les méthodes et les types de retour des classes dépendantes lors de la création de mocks.\n"
                .'- Ne devine pas les signatures des méthodes externes si elles sont présentes dans la repo-map.';
        }

        $systemMessage .= "\n\n".<<<'TEXT'
⚙️ RÈGLE D'INFÉRENCE DES DEPENDANCES & MOCKS (via Repo-Map) :
- Analyse le constructeur (`__construct`) de la classe cible présente dans la Repo-Map.
- Si le constructeur requiert des services ou interfaces (ex: Repositories, Mailer, Logger) :
  1. Instancie automatiquement des mocks PHPUnit (`$this->createMock(...)`) dans la méthode `setUp()`.
  2. Injecte ces mocks lors de la création du service à tester.
  3. Dans chaque scénario BDD, configure les comportements de ces mocks (`willReturn()`, `expects()`) en fonction des étapes 'Étant donné' / 'Alors'.
- Ne demande pas à la spécification BDD de lister les mocks techniques : déduis-les toi-même à partir de la Repo-Map.
TEXT;

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
        ?string $specContent,
    ): string {
        $testClassName = "{$className}DynamicTest";

        $message = "Génère le code du test PHPUnit pour la classe $className suivante :\n\n$classCode\n\n";
        $message .= "⚠️ CONFIGURATION OBLIGATOIRE DU FICHIER DE TEST :\n";
        $message .= "- Espace de noms (namespace) : App\\Tests\\Dynamic\n";
        $message .= "- Nom de la classe de test : $testClassName\n";

        if (null !== $specContent) {
            $trimmedSpec = trim($specContent);
            $message .= "\n\nSPÉCIFICATIONS DES SCÉNARIOS (FORMAT BDD) :\n";
            $message .= "Voici le cahier des charges des tests rédigé par l'utilisateur :\n";
            $message .= "```markdown\n$trimmedSpec\n```\n\n";
            $message .= "CONSIGNES DE TRADUCTION BDD -> PHPUNIT :\n";
            $message .= "- Génère exactement une méthode de test par scénario BDD décrit ci-dessus.\n";
            $message .= "- Nomme chaque méthode de test de façon explicite d'après le titre du scénario (ex: `testCalculateVatAmountWithNegativeAmountThrowsException`).\n";
            $message .= "- Structure l'intérieur de chaque méthode selon le pattern Arrange/Act/Assert en traduisant fidèlement le 'Étant donné' / 'Lorsque' / 'Alors'.\n";
        }

        if (null !== $existingTestCode) {
            $message .= "\n⚠️ UN FICHIER DE TEST EXISTE DÉJÀ POUR CETTE CLASSE !\n";
            $message .= "Tu dois impérativement FUSIONNER tes nouveaux tests avec le code existant fourni ci-dessous.\n";
            $message .= "Consignes de fusion :\n";

            if (null !== $methodName) {
                $message .= "- **Règle d'Idempotence (Priorité Haute) :** Inspecte minutieusement le code existant ci-dessous. Si des méthodes de test couvrant déjà spécifiquement la méthode `$methodName()` sont présentes :\n";
                $message .= "  a. NE DUPLIQUE PAS les tests. N'ajoute pas de méthodes ayant le même but ou des noms redondants.\n";
                $message .= "  b. Évalue si tes nouvelles propositions de tests apportent une réelle valeur ajoutée (ex. un cas limite oublié). Si oui, mets à jour ou remplace proprement les tests existants de cette méthode.\n";
                $message .= "  c. Si les tests existants pour cette méthode sont déjà complets et optimaux, renvoie le fichier d'origine sans le modifier inutilement.\n";
                $message .= "- **Pour les autres méthodes :** Ne supprime et ne modifie AUCUN des tests existants qui concernent d'autres méthodes de la classe.\n";
            } else {
                $message .= "- Ne supprime et ne modifie AUCUN des tests existants.\n";
            }

            $message .= "- S'il s'agit de nouveaux scénarios à ajouter, insère la ou les nouvelles méthodes de test à la suite.\n";
            $message .= "- Si nécessaire, fusionne proprement le contenu de la méthode `setUp()` sans casser l'existant.\n";
            $message .= "- Combine les déclarations `use` en haut du fichier si tu ajoutes de nouvelles dépendances.\n";
            $message .= "- Conserve temporairement la configuration de classe exigée (class $testClassName).\n\n";
            $message .= "Voici le code du test existant à enrichir :\n```php\n$existingTestCode\n```\n";
        } else {
            $message .= "\nGénère un nouveau fichier de test complet à partir de zéro.\n";

            if (null !== $methodName) {
                $message .= "\nATTENTION : Concentre-toi PRIORITAIREMENT et UNIQUEMENT sur les scénarios de test pour la méthode \"$methodName()\". ";
                $message .= "Ne génère pas de tests pour les autres méthodes afin de rester concis.\n";
            }
        }

        return $message;
    }
}
