<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\TestCorrectionException;
use App\Exception\TestGenerationException;
use App\Llm\LlmClientFactory;
use App\Llm\LlmClientInterface;
use App\RepoMap\CachedRepoMapBuilder;
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
        // 1. On charge le contenu de la fixture et on extrait le code PHP (comme le fait désormais le client LLM)
        $jsonPayload = file_get_contents($this->fixturePath);
        $data = json_decode($jsonPayload, true, 512, JSON_THROW_ON_ERROR);
        $phpCodePayload = $data['test_code'];

        $repoMapBuilderMock = $this->createMock(CachedRepoMapBuilder::class);
        $repoMapBuilderMock->expects($this->once())
            ->method('buildMap')
            ->willReturn("App\Service\FooService\n  - public function bar(): void");

        $testRunnerMock = $this->createMock(PhpUnitTestRunner::class);
        $testRunnerMock->expects($this->once())
            ->method('runTest')
            ->willReturn(['success' => true, 'output' => 'OK']);

        $llmClientMock = $this->createMock(LlmClientInterface::class);

        // Le client LLM renvoie le code PHP extrait du JSON
        $llmClientMock->expects($this->once())
            ->method('call')
            ->willReturn($phpCodePayload);

        $factoryMock = $this->createMock(LlmClientFactory::class);
        $factoryMock->method('getClient')
            ->willReturn($llmClientMock);

        $generator = new TestGenerator(
            $factoryMock,
            $repoMapBuilderMock,
            $testRunnerMock,
            '/fake/project/dir'
        );

        // --- EXÉCUTION ---
        $generatedCode = $generator->generateForClass('// code de VatCalculator', 'VatCalculator', 'google/gemini-2.5-flash-lite');

        // --- ASSERTIONS ---
        $this->assertStringContainsString('<?php', $generatedCode);
        $this->assertStringContainsString('namespace App\Tests\Dynamic;', $generatedCode);
        $this->assertStringContainsString('class VatCalculatorDynamicTest extends TestCase', $generatedCode);

        // On vérifie que le nettoyage des antislashs PHP a bien fonctionné
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

        // Mock du RepoMapBuilder qui renvoie une carte fictive
        $repoMapBuilderMock = $this->createMock(CachedRepoMapBuilder::class);
        $repoMapBuilderMock->expects($this->once())
            ->method('buildMap')
            ->willReturn("App\Service\FooService\n  - public function bar(): void");

        // Mock du runner pour simuler un retour valide sans rien exécuter sur le disque.
        $testRunnerMock = $this->createMock(PhpUnitTestRunner::class);
        $testRunnerMock->expects($this->once())
            ->method('runTest')
            ->willReturn(['success' => true, 'output' => 'OK']);

        // Variable pour capturer les messages envoyés au client LLM.
        $capturedMessages = [];

        $llmClientMock = $this->createMock(LlmClientInterface::class);

        // On intercepte l'appel à 'call' pour récupérer les arguments transmis
        $llmClientMock->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (array $messages) use ($jsonPayload, &$capturedMessages) {
                $capturedMessages = $messages; // On capture le tableau de messages

                return $jsonPayload;          // Le mock retourne la fixture attendue
            });

        $factoryMock = $this->createMock(LlmClientFactory::class);
        $factoryMock->method('getClient')
            ->willReturn($llmClientMock);

        $generator = new TestGenerator(
            $factoryMock,
            $repoMapBuilderMock,
            $testRunnerMock,
            '/fake/project/dir'
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

    /**
     * @throws TestCorrectionException
     * @throws TestGenerationException
     */
    public function testGenerateForClassInjectsRepoMapAndExecutesTest(): void
    {
        // --- ARRANGEMENT ---
        $capturedMessages = [];
        $dummyTestCode = '<?php class DummyTest {}';

        // 1. Mock du RepoMapBuilder qui renvoie une carte fictive
        $repoMapBuilderMock = $this->createMock(CachedRepoMapBuilder::class);
        $repoMapBuilderMock->expects($this->once())
            ->method('buildMap')
            ->willReturn("App\Service\FooService\n  - public function bar(): void");

        // 2. Mock du client LLM : renvoie une fausse réponse JSON contenant du code PHP
        $llmClientMock = $this->createMock(LlmClientInterface::class);

        $llmClientMock->expects($this->once())
            ->method('call')
            ->willReturnCallback(function (array $messages) use (&$capturedMessages, $dummyTestCode) {
                $capturedMessages = $messages;

                return $dummyTestCode;
            });

        $factoryMock = $this->createMock(LlmClientFactory::class);
        $factoryMock->method('getClient')
            ->willReturn($llmClientMock);

        // 3. Mock du TestRunner : évite d'exécuter un vrai test PHPUnit
        $testRunnerMock = $this->createMock(PhpUnitTestRunner::class);
        $testRunnerMock->expects($this->once())
            ->method('runTest')
            ->with($dummyTestCode, 'DummyClass') // Vérifie que le code extrait est bien transmis
            ->willReturn(['success' => true, 'output' => 'OK (1 test, 1 assertion)']);

        // --- EXECUTION ---
        // Instanciation de TestGenerator avec ses mocks
        $generator = new TestGenerator(
            $factoryMock,
            $repoMapBuilderMock,
            $testRunnerMock, // Injection du mock du runner
            '/fake/project/dir'
        );

        // Exécution de la méthode
        $generator->generateForClass('class DummyClass {}', 'DummyClass', 'qwen2.5-coder:14b');

        // --- ASSERTIONS ---
        // Assertions sur les messages système envoyés au LLM
        $systemMessage = $capturedMessages[0]['content'] ?? '';

        $this->assertStringContainsString('STRUCTURE DU PROJET (REPO-MAP)', $systemMessage);
        $this->assertStringContainsString('App\Service\FooService', $systemMessage);
        $this->assertStringContainsString('public function bar(): void', $systemMessage);
    }
}
