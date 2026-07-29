<?php

declare(strict_types=1);

namespace App\Tests\RepoMap;

use App\RepoMap\RepoMapBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\RandomException;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(RepoMapBuilder::class)]
final class RepoMapBuilderTest extends TestCase
{
    private string $tempDir;
    private Filesystem $filesystem;
    private RepoMapBuilder $builder;

    /**
     * @throws RandomException
     */
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/test_'.bin2hex(random_bytes(8));
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->tempDir);
        $this->builder = new RepoMapBuilder();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    #[Test]
    public function testExtractMapFromValidPhpFiles(): void
    {
        // ÉTANT DONNÉ
        $fileContent = '<?php namespace Test; class Foo { public function bar(): void {} }';
        file_put_contents($this->tempDir.'/Foo.php', $fileContent);

        // QUAND
        $result = $this->builder->buildMap($this->tempDir);

        // ALORS
        $this->assertSame("Test\Foo\n  - public function bar(): void\n", $result);
    }

    #[Test]
    public function testIgnoreClassesWithoutMethods(): void
    {
        // ÉTANT DONNÉ
        $fileContent = '<?php namespace Test; class EmptyClass {}';
        file_put_contents($this->tempDir.'/EmptyClass.php', $fileContent);

        // QUAND
        $result = $this->builder->buildMap($this->tempDir);

        // ALORS
        $this->assertSame('', $result);
    }

    #[Test]
    public function testIgnoreMalformedPhpFiles(): void
    {
        // ÉTANT DONNÉ
        $fileContent = '<?php invalid php syntax {{{';
        file_put_contents($this->tempDir.'/Bad.php', $fileContent);

        // QUAND
        $result = $this->builder->buildMap($this->tempDir);

        // ALORS
        $this->assertSame('', $result);
    }

    #[Test]
    public function testProcessDirectoryWithNoPhpFiles(): void
    {
        // ÉTANT DONNÉ
        file_put_contents($this->tempDir.'/readme.txt', 'content');

        // QUAND
        $result = $this->builder->buildMap($this->tempDir);

        // ALORS
        $this->assertSame('', $result);
    }
}
