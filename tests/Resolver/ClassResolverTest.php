<?php

declare(strict_types=1);

namespace App\Tests\Resolver;

use App\Resolver\ClassResolver;
use App\Service\VatCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\RandomException;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(ClassResolver::class)]
final class ClassResolverTest extends TestCase
{
    private string $tempDir;
    private ClassResolver $classResolver;

    /**
     * @throws RandomException
     */
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/test_'.bin2hex(random_bytes(8));
        mkdir($this->tempDir.'/src/Service', 0777, true);

        file_put_contents(
            $this->tempDir.'/src/Service/VatCalculator.php',
            "<?php\nnamespace App\Service;\nclass VatCalculator {}\n"
        );

        $this->classResolver = new ClassResolver($this->tempDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    #[Test]
    public function testResolveByExactFqcn(): void
    {
        // ÉTANT DONNÉ
        $fqcn = VatCalculator::class;

        // QUAND
        $result = $this->classResolver->resolve($fqcn);

        // ALORS
        $this->assertSame(VatCalculator::class, $result['className']);
    }

    #[Test]
    public function testResolveByUniqueShortName(): void
    {
        // ÉTANT DONNÉ
        $shortName = 'VatCalculator';

        // QUAND
        $result = $this->classResolver->resolve($shortName);

        // ALORS
        $this->assertSame(VatCalculator::class, $result['className']);
    }

    #[Test]
    public function testResolveNotFoundThrowsException(): void
    {
        // ÉTANT DONNÉ
        $unknown = 'NonExistentClass';

        // ALORS
        $this->expectException(\InvalidArgumentException::class);

        // QUAND
        $this->classResolver->resolve($unknown);
    }

    #[Test]
    public function testResolveAmbiguousShortNameThrowsException(): void
    {
        // ÉTANT DONNÉ
        mkdir($this->tempDir.'/src/Foo', 0777, true);
        mkdir($this->tempDir.'/src/Bar', 0777, true);

        file_put_contents(
            $this->tempDir.'/src/Foo/DuplicateClass.php',
            "<?php\nnamespace App\Foo;\nclass DuplicateClass {}\n"
        );
        file_put_contents(
            $this->tempDir.'/src/Bar/DuplicateClass.php',
            "<?php\nnamespace App\Bar;\nclass DuplicateClass {}\n"
        );

        $shortName = 'DuplicateClass';

        // ALORS
        $this->expectException(\InvalidArgumentException::class);

        // QUAND
        $this->classResolver->resolve($shortName);
    }
}
