<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\MakeTestSpecCommand;
use App\Service\VatCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\RandomException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(MakeTestSpecCommand::class)]
final class MakeTestSpecCommandTest extends TestCase
{
    private string $tempDir;
    private Filesystem $filesystem;
    private CommandTester $commandTester;

    /**
     * @throws RandomException
     */
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir().'/test_'.bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->tempDir.'/spec-templates');

        $command = new MakeTestSpecCommand($this->tempDir);
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    #[Test]
    public function testExecuteGeneratesGlobalSpecFileWithPlaceholdersReplaced(): void
    {
        // ÉTANT DONNÉ
        $this->filesystem->dumpFile(
            $this->tempDir.'/spec-templates/test_spec_class_template.md',
            '# Spec {className} ({methodName})'
        );

        // QUAND
        $this->commandTester->execute([
            'class' => VatCalculator::class,
        ]);

        // ALORS
        $this->assertSame(
            '# Spec VatCalculator (NomDeLaMethode)',
            file_get_contents($this->tempDir.'/tests/Specs/VatCalculatorSpec.md')
        );
    }

    #[Test]
    public function testExecuteGeneratesMethodTargetedSpecFileWithPlaceholdersReplaced(): void
    {
        // ÉTANT DONNÉ
        $this->filesystem->dumpFile(
            $this->tempDir.'/spec-templates/test_spec_template.md',
            '# Spec {className}::{methodName}'
        );

        // QUAND
        $this->commandTester->execute([
            'class' => VatCalculator::class,
            '--method' => 'calculateVatAmount',
        ]);

        // ALORS
        $this->assertSame(
            '# Spec VatCalculator::calculateVatAmount',
            file_get_contents($this->tempDir.'/tests/Specs/VatCalculator_calculateVatAmountSpec.md')
        );
    }

    #[Test]
    public function testExecuteReturnsFailureWhenTemplateDoesNotExist(): void
    {
        // ÉTANT DONNÉ

        // QUAND
        $statusCode = $this->commandTester->execute([
            'class' => VatCalculator::class,
        ]);

        // ALORS
        $this->assertSame(Command::FAILURE, $statusCode);
    }

    #[Test]
    public function testExecuteCreatesSpecsDirectoryWhenItDoesNotExist(): void
    {
        // ÉTANT DONNÉ
        $this->filesystem->dumpFile(
            $this->tempDir.'/spec-templates/test_spec_class_template.md',
            '# Spec {className}'
        );

        // QUAND
        $this->commandTester->execute([
            'class' => VatCalculator::class,
        ]);

        // ALORS
        $this->assertTrue(
            $this->filesystem->exists($this->tempDir.'/tests/Specs/VatCalculatorSpec.md')
        );
    }
}
