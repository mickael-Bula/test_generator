<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PhpUnitTestRunner;
use PHPUnit\Framework\TestCase;

class PhpUnitTestRunnerTest extends TestCase
{
    private PhpUnitTestRunner $runner;

    protected function setUp(): void
    {
        // Utilise le vrai dossier racine du projet/bundle
        $projectDir = dirname(__DIR__, 2);
        $this->runner = new PhpUnitTestRunner($projectDir);
    }

    public function testRunTestExecutesPhpUnitProcessSuccessfully(): void
    {
        // Code PHP d'un test valide qui passe toujours
        $validTestCode = <<<PHP
<?php

namespace App\Tests\Dynamic;

use PHPUnit\Framework\TestCase;

class DummyPassDynamicTest extends TestCase
{
    public function testExample(): void
    {
        \$this->assertTrue(true);
    }
}
PHP;

        // Exécution réelle du sous-processus PHPUnit
        $result = $this->runner->runTest($validTestCode, 'DummyPass');

        // Assertions sur le résultat retourné par le runner
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('output', $result);
        $this->assertTrue($result['success'], 'Le test valide aurait dû passer.');
        $this->assertStringContainsString('OK', $result['output']);
    }

    public function testRunTestCapturesFailure(): void
    {
        // Code PHP d'un test qui échoue intentionnellement
        $failingTestCode = <<<PHP
<?php

namespace App\Tests\Dynamic;

use PHPUnit\Framework\TestCase;

class DummyFailDynamicTest extends TestCase
{
    public function testExample(): void
    {
        \$this->assertTrue(false, 'Échec volontaire');
    }
}
PHP;

        $result = $this->runner->runTest($failingTestCode, 'DummyFail');

        $this->assertFalse($result['success'], 'Le test échoué aurait dû renvoyer false.');
        $this->assertStringContainsString('FAILURES!', $result['output']);
    }
}
