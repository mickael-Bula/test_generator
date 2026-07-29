<?php

declare(strict_types=1);

namespace App\Tests\Resolver;

use App\Resolver\SkillResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\RandomException;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(SkillResolver::class)]
class SkillResolverTest extends TestCase
{
    private string $tempDir;
    private string $nativeDir;
    private string $customDir;

    /**
     * @throws RandomException
     */
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/test_skill_resolver_'.bin2hex(random_bytes(8));
        $this->nativeDir = $this->tempDir.'/native';
        $this->customDir = $this->tempDir.'/custom';

        mkdir($this->nativeDir, 0777, true);
        mkdir($this->customDir, 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    #[Test]
    public function testDetectionDesCommandesSymfony(): void
    {
        // ÉTANT DONNÉ
        file_put_contents($this->nativeDir.'/symfony_command.md', 'Symfony Command Skill');
        $resolver = new SkillResolver(null, $this->nativeDir);
        $code = 'class MyCommand extends Command {}';

        // QUAND
        $result = $resolver->resolveForClass('App\Command\MyCommand', $code);

        // ALORS
        $this->assertSame('Symfony Command Skill', $result);
    }

    #[Test]
    public function testDetectionDesOperationsSurLeSystemeDeFichiers(): void
    {
        // ÉTANT DONNÉ
        file_put_contents($this->nativeDir.'/filesystem_test.md', 'Filesystem Skill');
        $resolver = new SkillResolver(null, $this->nativeDir);
        $code = 'file_put_contents("file.txt", "data");';

        // QUAND
        $result = $resolver->resolveForClass('App\Service\FileService', $code);

        // ALORS
        $this->assertSame('Filesystem Skill', $result);
    }

    #[Test]
    public function testDetectionDeLutilisationDePhpParser(): void
    {
        // ÉTANT DONNÉ
        file_put_contents($this->nativeDir.'/php-parser-v5.md', 'PhpParser Skill');
        $resolver = new SkillResolver(null, $this->nativeDir);
        $code = 'use PhpParser\Node;';

        // QUAND
        $result = $resolver->resolveForClass('App\Parser\Visitor', $code);

        // ALORS
        $this->assertSame('PhpParser Skill', $result);
    }

    #[Test]
    public function testConcatenationDeMultiplesSkillsNatifsEtDeduplication(): void
    {
        // ÉTANT DONNÉ
        file_put_contents($this->nativeDir.'/symfony_command.md', 'Symfony Command Skill');
        file_put_contents($this->nativeDir.'/filesystem_test.md', 'Filesystem Skill');
        file_put_contents($this->nativeDir.'/php-parser-v5.md', 'PhpParser Skill');
        $resolver = new SkillResolver(null, $this->nativeDir);
        $code = 'class MyCommand extends Command { public function run() { file_put_contents("a", "b"); use PhpParser\Node; } }';
        $expected = "Symfony Command Skill\n\nFilesystem Skill\n\nPhpParser Skill";

        // QUAND
        $result = $resolver->resolveForClass('App\Command\MyCommand', $code);

        // ALORS
        $this->assertSame($expected, $result);
    }

    #[Test]
    public function testOrdreEtAjoutDesSkillsPersonnalisesDuProjetHote(): void
    {
        // ÉTANT DONNÉ
        file_put_contents($this->nativeDir.'/symfony_command.md', 'Native Skill');
        file_put_contents($this->customDir.'/custom1.md', 'Custom Skill');
        $resolver = new SkillResolver($this->customDir, $this->nativeDir);
        $code = 'class MyCommand extends Command {}';
        $expected = "Native Skill\n\nCustom Skill";

        // QUAND
        $result = $resolver->resolveForClass('App\Command\MyCommand', $code);

        // ALORS
        $this->assertSame($expected, $result);
    }

    #[Test]
    public function testIsolationEtResilienceEnCasDeFichiersOuDossiersManquants(): void
    {
        // ÉTANT DONNÉ
        $resolver = new SkillResolver($this->tempDir.'/non_existent_custom', $this->tempDir.'/non_existent_native');
        $code = 'class MyCommand extends Command {}';

        // QUAND
        $result = $resolver->resolveForClass('App\Command\MyCommand', $code);

        // ALORS
        $this->assertSame('', $result);
    }
}
