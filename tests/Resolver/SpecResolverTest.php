<?php

declare(strict_types=1);

namespace App\Tests\Resolver;

use App\Resolver\SpecResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test créé automatiquement en utilisant la commande suivante :
 * php bin/console app:generate-test SpecResolver \
 * -m resolve \
 * --spec="Tester la résolution par chemin direct et relatif avec et sans l'extension .md"
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

        $this->specResolver = new SpecResolver($this->projectDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    /**
     * Test qui commence par enregistrer un contenu dans un fichier temporaire,
     * puis qui vérifie que l'appel au Resolver permet de récupérer ce même contenu.
     */
    #[Test]
    public function testResolveByDirectPathWithExtension(): void
    {
        // ÉTANT DONNÉ
        $filePath = $this->projectDir.'/direct_spec.md';
        file_put_contents($filePath, 'Direct Spec Content');

        // QUAND
        $result = $this->specResolver->resolve($filePath);

        // ALORS
        $this->assertSame('Direct Spec Content', $result);
    }

    #[Test]
    public function testResolveByDirectPathWithoutExtension(): void
    {
        // ÉTANT DONNÉ
        $filePath = $this->projectDir.'/direct_spec_no_ext';
        file_put_contents($filePath.'.md', 'Direct Spec No Ext Content');

        // QUAND
        $result = $this->specResolver->resolve($filePath);

        // ALORS
        $this->assertSame('Direct Spec No Ext Content', $result);
    }

    #[Test]
    public function testResolveByRelativeProjectPathWithExtension(): void
    {
        // ÉTANT DONNÉ
        $relativePath = 'specs/relative_spec.md';
        mkdir($this->projectDir.'/specs', 0777, true);
        file_put_contents($this->projectDir.'/'.$relativePath, 'Relative Spec Content');

        // QUAND
        $result = $this->specResolver->resolve($relativePath);

        // ALORS
        $this->assertSame('Relative Spec Content', $result);
    }

    #[Test]
    public function testResolveByRelativeProjectPathWithoutExtension(): void
    {
        // ÉTANT DONNÉ
        $relativePath = 'specs/relative_spec_no_ext';
        mkdir($this->projectDir.'/specs', 0777, true);
        file_put_contents($this->projectDir.'/'.$relativePath.'.md', 'Relative Spec No Ext Content');

        // QUAND
        $result = $this->specResolver->resolve($relativePath);

        // ALORS
        $this->assertSame('Relative Spec No Ext Content', $result);
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
