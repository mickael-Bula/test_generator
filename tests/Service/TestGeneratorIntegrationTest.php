<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\TestCorrectionException;
use App\Exception\TestGenerationException;
use App\Service\LlmJsonSanitizer;
use App\Service\PhpTestFileBuilder;
use App\Service\PhpUnitTestRunner;
use App\Service\TestGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

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
        $dynamicTestFile = $this->projectDir.'/tests/Dynamic/VatCalculatorDynamicTest.php';
        if (file_exists($dynamicTestFile)) {
            unlink($dynamicTestFile);
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

        // On crée un mock du client HTTP de Symfony pour intercepter l'appel à OpenRouter
        $mockResponse = new MockResponse($mockedResponseBody, [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
        $mockHttpClient = new MockHttpClient($mockResponse, 'https://openrouter.ai/api/v1/');

        // On instancie les vrais services internes (sans les mocker) pour tester leur vraie logique
        $sanitizer = new LlmJsonSanitizer();
        $fileBuilder = new PhpTestFileBuilder($sanitizer);
        $testRunner = new PhpUnitTestRunner($this->projectDir);

        // On injecte le tout dans notre TestGenerator
        $generator = new TestGenerator(
            'gpt-4o',
            $mockHttpClient,
            $sanitizer,
            $testRunner,
            $fileBuilder
        );

        // ---EXÉCUTION ---
        $generatedCode = $generator->generateForClass('// code de VatCalculatorDynamic', 'VatCalculatorDynamic');

        // --- ASSERTIONS ---
        $this->assertStringContainsString('<?php', $generatedCode);
        $this->assertStringContainsString('namespace App\Tests\Dynamic;', $generatedCode);
        $this->assertStringContainsString('class VatCalculatorDynamicTest extends TestCase', $generatedCode);

        // On vérifie que le nettoyage chirurgical des antislashs a bien fonctionné
        $this->assertStringNotContainsString('App\\\Tests', $generatedCode);
    }
}
