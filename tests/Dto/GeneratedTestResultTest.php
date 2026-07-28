<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\GeneratedTestResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GeneratedTestResult::class)]
final class GeneratedTestResultTest extends TestCase
{
    private GeneratedTestResult $generatedTestResult;

    protected function setUp(): void
    {
        $this->generatedTestResult = new GeneratedTestResult("```php\r\nnamespace App\\\Tests;\r\n\r\nuse App\\\Entity\\\User;\r\n```");
    }

    #[Test]
    public function testGetCleanTestCode(): void
    {
        // ÉTANT DONNÉ

        // QUAND
        $result = $this->generatedTestResult->getCleanTestCode();

        // ALORS
        $this->assertSame("namespace App\Tests;\n\nuse App\Entity\User;", $result);
    }

    #[Test]
    public function testTestCodeProperty(): void
    {
        // ÉTANT DONNÉ

        // QUAND
        $rawCode = $this->generatedTestResult->testCode;

        // ALORS
        $this->assertSame("```php\r\nnamespace App\\\Tests;\r\n\r\nuse App\\\Entity\\\User;\r\n```", $rawCode);
    }
}