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
    #[Test]
    public function testCleanWindowsLineEndings(): void
    {
        // ÉTANT DONNÉ
        $dto = new GeneratedTestResult("line1\r\nline2\rline3");

        // QUAND
        $result = $dto->getCleanTestCode();

        // ALORS
        $this->assertSame("line1\nline2\nline3", $result);
    }

    #[Test]
    public function testRemoveMarkdownTags(): void
    {
        // ÉTANT DONNÉ
        $dto = new GeneratedTestResult("```php\n<?php echo 'hello';\n```");

        // QUAND
        $result = $dto->getCleanTestCode();

        // ALORS
        $this->assertSame("<?php echo 'hello';", $result);
    }

    #[Test]
    public function testFixDoubleBackslashes(): void
    {
        // ÉTANT DONNÉ
        $dto = new GeneratedTestResult('App\\\Entity\\\User');

        // QUAND
        $result = $dto->getCleanTestCode();

        // ALORS
        $this->assertSame('App\Entity\User', $result);
    }

    #[Test]
    public function testAccessTestCodeProperty(): void
    {
        // ÉTANT DONNÉ
        $rawCode = 'raw test code';
        $dto = new GeneratedTestResult($rawCode);

        // QUAND
        $result = $dto->testCode;

        // ALORS
        $this->assertSame($rawCode, $result);
    }
}
