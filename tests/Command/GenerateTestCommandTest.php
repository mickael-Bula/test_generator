<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\GenerateTestCommand;
use App\Exception\TestCorrectionException;
use App\Llm\LlmClientFactory;
use App\Resolver\ClassResolver;
use App\Resolver\SpecResolver;
use App\Service\TestGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Random\RandomException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[CoversClass(GenerateTestCommand::class)]
final class GenerateTestCommandTest extends TestCase
{
    private string $tempDir;
    private TestGenerator&MockObject $testGenerator;
    private LlmClientFactory&MockObject $llmFactory;
    private ClassResolver&MockObject $classResolver;
    private SpecResolver&MockObject $specResolver;
    private GenerateTestCommand $command;

    /**
     * @throws RandomException
     */
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/test_'.bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0777, true);

        $this->testGenerator = $this->createMock(TestGenerator::class);
        $this->llmFactory = $this->createMock(LlmClientFactory::class);
        $this->classResolver = $this->createMock(ClassResolver::class);
        $this->specResolver = $this->createMock(SpecResolver::class);

        $this->command = new GenerateTestCommand(
            $this->testGenerator,
            $this->llmFactory,
            $this->tempDir,
            $this->classResolver,
            $this->specResolver
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $filesystem = new Filesystem();
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $file) {
                @chmod($file->getPathname(), 0777);
            }
            $filesystem->remove($this->tempDir);
        }
    }

    #[Test]
    public function testConfigureCommandHasArgumentAndOptions(): void
    {
        // ÉTANT DONNÉ
        $command = $this->command;

        // QUAND
        $definition = $command->getDefinition();

        // ALORS
        $this->assertTrue($definition->hasArgument('class'));
        $this->assertTrue($definition->getArgument('class')->isRequired());
        $this->assertTrue($definition->hasOption('method'));
        $this->assertTrue($definition->hasOption('model'));
        $this->assertTrue($definition->hasOption('spec'));
    }

    #[Test]
    public function testExecuteFailsWhenClassCannotBeResolvedOrFileDoesNotExist(): void
    {
        // ÉTANT DONNÉ
        $this->classResolver->method('resolve')
            ->willThrowException(new \InvalidArgumentException('Class not found'));
        $commandTester = new CommandTester($this->command);

        // QUAND
        $statusCode = $commandTester->execute(['class' => 'NonExistentClass']);

        // ALORS
        $this->assertSame(Command::FAILURE, $statusCode);
    }

    #[Test]
    public function testExecuteGeneratesNewTestFileSuccessfully(): void
    {
        // ÉTANT DONNÉ
        $srcDir = $this->tempDir.'/src/Service';
        mkdir($srcDir, 0777, true);
        $filePath = $srcDir.'/Foo.php';
        file_put_contents($filePath, '<?php namespace App\Service; class Foo {}');

        $this->classResolver->method('resolve')
            ->willReturn([
                'className' => 'App\Service\Foo',
                'filePath' => $filePath,
            ]);
        $this->llmFactory->method('getDefaultModel')
            ->willReturn('default-model');
        $this->specResolver->method('resolve')
            ->willReturn(null);
        $this->testGenerator->method('generateForClass')
            ->willReturn("<?php\nnamespace App\Tests\Command;\nclass FooDynamicTest {}");

        $commandTester = new CommandTester($this->command);

        // QUAND
        $statusCode = $commandTester->execute(['class' => 'Foo']);

        // ALORS
        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertFileExists($this->tempDir.'/tests/Service/FooTest.php');
    }

    #[Test]
    public function testExecuteExistingTestFileAndCancelledByUser(): void
    {
        // ÉTANT DONNÉ
        $srcDir = $this->tempDir.'/src/Service';
        $testsDir = $this->tempDir.'/tests/Service';
        mkdir($srcDir, 0777, true);
        mkdir($testsDir, 0777, true);

        $filePath = $srcDir.'/Foo.php';
        $testPath = $testsDir.'/FooTest.php';
        file_put_contents($filePath, '<?php namespace App\Service; class Foo {}');
        file_put_contents($testPath, '<?php namespace App\Tests\Service; class FooTest {}');

        $this->classResolver->method('resolve')
            ->willReturn([
                'className' => 'App\Service\Foo',
                'filePath' => $filePath,
            ]);
        $this->llmFactory->method('getDefaultModel')
            ->willReturn('default-model');

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['no']);

        // QUAND
        $statusCode = $commandTester->execute(['class' => 'Foo']);

        // ALORS
        $this->assertSame(Command::SUCCESS, $statusCode);
    }

    #[Test]
    public function testExecuteExistingTestFileCleanAndMerged(): void
    {
        // ÉTANT DONNÉ
        $srcDir = $this->tempDir.'/src/Service';
        $testsDir = $this->tempDir.'/tests/Service';
        mkdir($srcDir, 0777, true);
        mkdir($testsDir, 0777, true);

        $filePath = $srcDir.'/Foo.php';
        $testPath = $testsDir.'/FooTest.php';
        file_put_contents($filePath, '<?php namespace App\Service; class Foo {}');
        file_put_contents($testPath, "<?php\nnamespace App\Tests\Service;\nclass FooTest {}");

        $this->classResolver->method('resolve')->willReturn([
            'className' => 'App\Service\Foo',
            'filePath' => $filePath,
        ]);
        $this->llmFactory->method('getDefaultModel')->willReturn('default-model');
        $this->testGenerator->method('generateForClass')
            ->willReturn("<?php\nnamespace App\Tests\Command;\nclass FooDynamicTest {}");

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['yes']);

        // QUAND
        $statusCode = $commandTester->execute(['class' => 'Foo']);

        // ALORS
        $this->assertSame(Command::SUCCESS, $statusCode);
    }

    #[Test]
    public function testExecuteExistingTestFileDirtyAndCancelled(): void
    {
        // ÉTANT DONNÉ
        (new Process(['git', 'init'], $this->tempDir))->mustRun();
        (new Process(['git', 'config', 'user.email', 'test@example.com'], $this->tempDir))->mustRun();
        (new Process(['git', 'config', 'user.name', 'Test'], $this->tempDir))->mustRun();

        $srcDir = $this->tempDir.'/src/Service';
        $testsDir = $this->tempDir.'/tests/Service';
        mkdir($srcDir, 0777, true);
        mkdir($testsDir, 0777, true);

        $filePath = $srcDir.'/Foo.php';
        $testPath = $testsDir.'/FooTest.php';
        file_put_contents($filePath, '<?php namespace App\Service; class Foo {}');
        file_put_contents($testPath, '<?php namespace App\Tests\Service; class FooTest {}');

        (new Process(['git', 'add', '.'], $this->tempDir))->mustRun();
        (new Process(['git', 'commit', '-m', 'Initial commit'], $this->tempDir))->mustRun();

        file_put_contents($testPath, '<?php namespace App\Tests\Service; class FooTest { /* dirty */ }');

        $this->classResolver->method('resolve')
            ->willReturn([
                'className' => 'App\Service\Foo',
                'filePath' => $filePath,
            ]);
        $this->llmFactory->method('getDefaultModel')
            ->willReturn('default-model');

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['yes', 'no']);

        // QUAND
        $statusCode = $commandTester->execute(['class' => 'Foo']);

        // ALORS
        $this->assertSame(Command::FAILURE, $statusCode);
    }

    #[Test]
    public function testExecuteHandlesGeneratorOrLlmException(): void
    {
        // ÉTANT DONNÉ
        $srcDir = $this->tempDir.'/src/Service';
        mkdir($srcDir, 0777, true);
        $filePath = $srcDir.'/Foo.php';
        file_put_contents($filePath, '<?php namespace App\Service; class Foo {}');

        $this->classResolver->method('resolve')
            ->willReturn([
                'className' => 'App\Service\Foo',
                'filePath' => $filePath,
            ]);
        $this->llmFactory->method('getDefaultModel')
            ->willReturn('default-model');
        $this->testGenerator->method('generateForClass')
            ->willThrowException(new TestCorrectionException('LLM error'));

        $commandTester = new CommandTester($this->command);

        // QUAND
        $statusCode = $commandTester->execute(['class' => 'Foo']);

        // ALORS
        $this->assertSame(Command::FAILURE, $statusCode);
    }
}
