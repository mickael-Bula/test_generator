<?php

declare(strict_types=1);

namespace App\Tests\RepoMap;

use App\RepoMap\RepoMapVisitor;
use PhpParser\Modifiers;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RepoMapVisitor::class)]
final class RepoMapVisitorTest extends TestCase
{
    #[Test]
    public function testExtractionOfFqcnAndMethodSignatures(): void
    {
        // ÉTANT DONNÉ
        $visitor = new RepoMapVisitor();
        $classNode = new Class_('Foo');
        $classNode->namespacedName = new Name('App\Foo');

        $param1 = new Param(new Variable('a'), null, new Identifier('string'));
        $param2 = new Param(new Variable('b'), null, new Identifier('int'));

        $publicMethod = new ClassMethod('pubMethod', [
            'flags' => Modifiers::PUBLIC,
            'params' => [$param1],
            'returnType' => new Identifier('string'),
        ]);

        $protectedMethod = new ClassMethod('protMethod', [
            'flags' => Modifiers::PROTECTED,
            'params' => [$param2],
            'returnType' => new Identifier('void'),
        ]);

        $privateMethod = new ClassMethod('privMethod', [
            'flags' => Modifiers::PRIVATE,
            'returnType' => new Identifier('bool'),
        ]);

        // QUAND
        $visitor->enterNode($classNode);
        $visitor->enterNode($publicMethod);
        $visitor->enterNode($protectedMethod);
        $visitor->enterNode($privateMethod);
        $visitor->leaveNode($classNode);

        // ALORS
        $expectedMap = [
            'App\Foo' => [
                '  - public function pubMethod(string $a): string',
                '  - protected function protMethod(int $b): void',
                '  - private function privMethod(): bool',
            ],
        ];
        $this->assertSame($expectedMap, $visitor->getMap());
    }

    #[Test]
    public function testCaptureClassWithoutMethods(): void
    {
        // ÉTANT DONNÉ
        $visitor = new RepoMapVisitor();
        $classNode = new Class_('EmptyClass');
        $classNode->namespacedName = new Name('App\EmptyClass');

        // QUAND
        $visitor->enterNode($classNode);
        $visitor->leaveNode($classNode);

        // ALORS
        $this->assertSame(['App\EmptyClass' => []], $visitor->getMap());
    }

    #[Test]
    public function testContextResetOnLeavingClass(): void
    {
        // ÉTANT DONNÉ
        $visitor = new RepoMapVisitor();
        $classNode = new Class_('Foo');
        $classNode->namespacedName = new Name('App\Foo');
        $orphanMethod = new ClassMethod('orphan', [
            'flags' => Modifiers::PUBLIC,
        ]);

        // QUAND
        $visitor->enterNode($classNode);
        $visitor->leaveNode($classNode);
        $visitor->enterNode($orphanMethod);

        // ALORS
        $this->assertSame(['App\Foo' => []], $visitor->getMap());
    }

    #[Test]
    public function testFormattingMethodWithoutReturnTypeAndUntypedParameters(): void
    {
        // ÉTANT DONNÉ
        $visitor = new RepoMapVisitor();
        $classNode = new Class_('Bar');
        $classNode->namespacedName = new Name('App\Bar');
        $param = new Param(new Variable('data'));
        $method = new ClassMethod('process', [
            'flags' => Modifiers::PUBLIC,
            'params' => [$param],
        ]);

        // QUAND
        $visitor->enterNode($classNode);
        $visitor->enterNode($method);

        // ALORS
        $expectedMap = [
            'App\Bar' => [
                '  - public function process($data)',
            ],
        ];
        $this->assertSame($expectedMap, $visitor->getMap());
    }
}
