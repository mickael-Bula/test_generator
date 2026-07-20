<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\TestCorrectionException;
use App\Exception\TestGenerationException;
use App\Llm\LlmClientInterface;
use App\Service\LlmJsonSanitizer;
use App\Service\PhpTestFileBuilder;
use App\Service\PhpUnitTestRunner;
use App\Service\TestGenerator;
use PHPUnit\Framework\TestCase;

class TestGeneratorIntegrationTest extends TestCase
{
    private string $fixturePath;
    private string $projectDir;

    protected function setUp(): void
    {
        // On récupère la racine du projet pour nos runners et fixtures
        $this->projectDir = dirname(__DIR__, 2);
        $this->fixturePath = $this->projectDir.'/tests/Fixtures/llm_vat_calculator_response.json';
    }

    protected function tearDown(): void
    {
        // Nettoyage des fichiers temporaires générés par les deux tests
        $filesToClean = [
            $this->projectDir.'/tests/Dynamic/VatCalculatorDynamicTest.php',
            $this->projectDir.'/tests/Dynamic/VatCalculatorTest.php',
        ];

        foreach ($filesToClean as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        // Optionnel : supprimer le dossier s'il est vide
        $dynamicDir = $this->projectDir.'/tests/Dynamic';
        if (is_dir($dynamicDir) && 2 === count(scandir($dynamicDir))) {
            rmdir($dynamicDir);
        }
    }

    /**
     * @throws TestCorrectionException
     * @throws TestGenerationException
     * @throws \JsonException
     */
    public function testGeneratorCleansJsonAndValidatesSuccessfully(): void
    {
        // --- ARRANGEMENT ---
        // On charge le contenu de notre faux fichier LLM
        $jsonPayload = file_get_contents($this->fixturePath);

        // Enveloppe standard du format OpenRouter / OpenAI
        $mockedResponseBody = json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => $jsonPayload,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        // On crée un mock de l'interface qui appelle OpenRouter
        $llmClientMock = $this->createMock(LlmClientInterface::class);

        // On configure le comportement attendu du client LLM
        $llmClientMock->expects($this->once())
            ->method('call')
            ->willReturn($jsonPayload);

        // On instancie les vrais services internes (sans les mocker) pour tester leur vraie logique
        $sanitizer = new LlmJsonSanitizer();
        $fileBuilder = new PhpTestFileBuilder($sanitizer);
        $testRunner = new PhpUnitTestRunner($this->projectDir);

        // On injecte le tout dans notre TestGenerator
        $generator = new TestGenerator(
            $llmClientMock,
            $sanitizer,
            $testRunner,
            $fileBuilder
        );

        // ---EXÉCUTION ---
        $generatedCode = $generator->generateForClass('// code de VatCalculator', 'VatCalculator', 'google/gemini-2.5-flash-lite');

        // --- ASSERTIONS ---
        $this->assertStringContainsString('<?php', $generatedCode);
        $this->assertStringContainsString('namespace App\Tests\Dynamic;', $generatedCode);
        $this->assertStringContainsString('class VatCalculatorDynamicTest extends TestCase', $generatedCode);

        // On vérifie que le nettoyage chirurgical des antislashs a bien fonctionné
        $this->assertStringNotContainsString('App\\\Tests', $generatedCode);
    }

    /**
     * Teste que le générateur prend correctement en compte l'option de méthode ciblée.
     *
     * @throws TestCorrectionException
     * @throws TestGenerationException
     * @throws \JsonException
     */
    public function testGeneratorWithSpecificMethodOption(): void
    {
        // --- ARRANGEMENT ---
        // On réutilise la même fixture (qui contient toutes les méthodes),
        // l'important est de valider que la méthode transmet correctement l'information
        $jsonPayload = file_get_contents($this->fixturePath);

        // Variable pour capturer les messages envoyés au client LLM.
        $capturedMessages = [];

        $llmClientMock = $this->createMock(LlmClientInterface::class);

        // On intercepte l'appel à 'call' pour récupérer les arguments transmis
        $llmClientMock->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (array $messages, string $model) use ($jsonPayload, &$capturedMessages) {
                $capturedMessages = $messages; // On capture le tableau de messages

                return $jsonPayload;          // Le mock retourne la fixture attendue
            });

        $sanitizer = new LlmJsonSanitizer();
        $fileBuilder = new PhpTestFileBuilder($sanitizer);
        $testRunner = new PhpUnitTestRunner($this->projectDir);

        $generator = new TestGenerator(
            $llmClientMock,
            $sanitizer,
            $testRunner,
            $fileBuilder
        );

        // --- EXÉCUTION ---
        // On appelle la méthode en fournissant le 3e argument : 'calculateNetAmountFromGross'
        $generator->generateForClass('// code de VatCalculator', 'VatCalculator', 'google/gemini-2.5-flash-lite', 'calculateNetAmountFromGross');

        // --- ASSERTIONS ---
        // 1. On rassemble le contenu de tous les messages capturés (système et utilisateur).
        $allContent = '';
        foreach ($capturedMessages as $message) {
            $allContent .= ($message['content'] ?? '')."\n";
        }

        // 2. On vérifie que les instructions spécifiques à la méthode ciblée sont bien présentes dans le prompt
        $this->assertNotEmpty($allContent, 'Les messages envoyés au LLM ne doivent pas être vides.');
        $this->assertStringContainsString('calculateNetAmountFromGross', $allContent);
        $this->assertStringContainsString('CONCENTRE-TOI', mb_strtoupper($allContent, 'UTF-8'));
    }
}
