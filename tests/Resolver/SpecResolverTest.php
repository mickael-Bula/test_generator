<?php

declare(strict_types=1);

namespace App\Tests\Resolver;

use App\Resolver\SpecResolver;
use App\Service\SpecTemplateCleaner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Tests générés avec la commande suivante :
 * php bin/console app:generate-test SpecResolver -m resolve
 */
#[CoversClass(SpecResolver::class)]
final class SpecResolverTest extends TestCase
{
    private string $projectDir;
    private SpecResolver $specResolver;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/spec_resolver_test_'.uniqid('', true);
        mkdir($this->projectDir, 0777, true);

        $specTemplateCleanerMock = $this->createMock(SpecTemplateCleaner::class);

        // Configure le mock pour retourner l'argument transmis
        $specTemplateCleanerMock->method('cleanForLlm')
            ->willReturnCallback(fn (?string $content) => $content);

        $this->specResolver = new SpecResolver($this->projectDir, $specTemplateCleanerMock);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    /**
     *  Test généré sans l'option --spec.
     */
    #[Test]
    public function testResolveReturnsNullWhenOptionIsFalse(): void
    {
        // ÉTANT DONNÉ l'option --spec non présente, specOption vaut false

        // QUAND
        $result = $this->specResolver->resolve(false, 'VatCalculator');

        // ALORS
        $this->assertNull($result);
    }

    /**
     * Quand l'option --spec est présente, mais sans valeur associée,
     * le nom du fichier de spécification est automatiquement résolu.
     */
    #[Test]
    public function testResolveByConventionWhenOptionIsNullAndFileExists(): void
    {
        // ÉTANT DONNÉ
        mkdir($this->projectDir.'/tests/Specs', 0777, true);
        file_put_contents($this->projectDir.'/tests/Specs/VatCalculatorSpec.md', 'Content By Convention');

        // QUAND
        $result = $this->specResolver->resolve(null, 'VatCalculator');

        // ALORS
        $this->assertSame('Content By Convention', $result);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function testResolveByConventionEmitsWarningWhenFileNotFound(): void
    {
        // ÉTANT DONNÉ
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('warning');

        // QUAND
        $result = $this->specResolver->resolve(null, 'UnknownClass', $io);

        // ALORS
        $this->assertNull($result);
    }

    /**
     *  Test généré avec l'option :
     *  --spec="Tester la résolution par chemin direct et relatif avec et sans l'extension .md"
     */
    #[Test]
    public function testResolveByDirectPathWithExtension(): void
    {
        // ÉTANT DONNÉ
        $filePath = $this->projectDir.'/direct_spec.md';
        file_put_contents($filePath, 'Direct Spec Content');

        // QUAND
        $result = $this->specResolver->resolve($filePath, 'VatCalculator');

        // ALORS
        $this->assertSame('Direct Spec Content', $result);
    }

    /**
     *  Test généré avec l'option :
     *  --spec="Tester la résolution par chemin direct et relatif avec et sans l'extension .md"
     */
    #[Test]
    public function testResolveByDirectPathWithoutExtension(): void
    {
        // ÉTANT DONNÉ
        $filePath = $this->projectDir.'/direct_spec_no_ext';
        file_put_contents($filePath.'.md', 'Direct Spec No Ext Content');

        // QUAND
        $result = $this->specResolver->resolve($filePath, 'VatCalculator');

        // ALORS
        $this->assertSame('Direct Spec No Ext Content', $result);
    }

    /**
     *  Test généré avec l'option :
     *  --spec="Tester la résolution par chemin direct et relatif avec et sans l'extension .md"
     */
    #[Test]
    public function testResolveByRelativeProjectPathWithExtension(): void
    {
        // ÉTANT DONNÉ
        $relativePath = 'specs/relative_spec.md';
        mkdir($this->projectDir.'/specs', 0777, true);
        file_put_contents($this->projectDir.'/'.$relativePath, 'Relative Spec Content');

        // QUAND
        $result = $this->specResolver->resolve($relativePath, 'VatCalculator');

        // ALORS
        $this->assertSame('Relative Spec Content', $result);
    }

    /**
     *  Test généré avec l'option :
     *  --spec="Tester la résolution par chemin direct et relatif avec et sans l'extension .md"
     */
    #[Test]
    public function testResolveByRelativeProjectPathWithoutExtension(): void
    {
        // ÉTANT DONNÉ
        $relativePath = 'specs/relative_spec_no_ext';
        mkdir($this->projectDir.'/specs', 0777, true);
        file_put_contents($this->projectDir.'/'.$relativePath.'.md', 'Relative Spec No Ext Content');

        // QUAND
        $result = $this->specResolver->resolve($relativePath, 'VatCalculator');

        // ALORS
        $this->assertSame('Relative Spec No Ext Content', $result);
    }

    /**
     * Test généré avec cette option :
     * --spec="Tester la recherche Finder lorsque le fichier est trouvé, quand il est en double, et quand il n'existe pas"
     */
    #[Test]
    public function testResolveByFinderSearchWhenFileIsFound(): void
    {
        // ÉTANT DONNÉ
        mkdir($this->projectDir.'/sub', 0777, true);
        file_put_contents($this->projectDir.'/sub/finder_spec.md', 'Finder Content');

        // QUAND
        $result = $this->specResolver->resolve('finder_spec.md', 'VatCalculator');

        // ALORS
        $this->assertSame('Finder Content', $result);
    }

    /**
     * Test généré avec cette option :
     * --spec="Tester la recherche Finder lorsque le fichier est trouvé, quand il est en double, et quand il n'existe pas"
     *
     * @throws Exception
     */
    #[Test]
    public function testResolveByFinderSearchWhenDuplicateFilesFound(): void
    {
        // ÉTANT DONNÉ
        mkdir($this->projectDir.'/sub1', 0777, true);
        mkdir($this->projectDir.'/sub2', 0777, true);
        file_put_contents($this->projectDir.'/sub1/dup_spec.md', 'Content 1');
        file_put_contents($this->projectDir.'/sub2/dup_spec.md', 'Content 2');
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('warning');

        // QUAND
        $result = $this->specResolver->resolve('dup_spec.md', 'VatCalculator', $io);

        // ALORS
        $this->assertSame('dup_spec.md', $result);
    }

    /**
     * Test généré avec cette option :
     * --spec="Tester la recherche Finder lorsque le fichier est trouvé, quand il est en double, et quand il n'existe pas"
     *
     * @throws Exception
     */
    #[Test]
    public function testResolveByFinderSearchWhenFileDoesNotExist(): void
    {
        // ÉTANT DONNÉ
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('warning');

        // QUAND
        $result = $this->specResolver->resolve('NonExistentSpec.md', 'VatCalculator', $io);

        // ALORS
        $this->assertSame('NonExistentSpec.md', $result);
    }

    /**
     * Test généré avec cette option :
     * --spec="Vérifier que les avertissements sont bien émis sur $io uniquement quand l'entrée ressemble à un nom de fichier"
     *
     * @throws Exception
     */
    #[Test]
    public function testResolveDoesNotEmitWarningWhenInputDoesNotLookLikeFile(): void
    {
        // ÉTANT DONNÉ
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->never())->method('warning');

        // QUAND
        $result = $this->specResolver->resolve(
            'Traite uniquement la premiere methode',
            'VatCalculator',
            $io
        );

        // ALORS
        $this->assertSame('Traite uniquement la premiere methode', $result);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
