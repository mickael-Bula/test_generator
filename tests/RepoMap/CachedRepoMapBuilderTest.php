<?php

declare(strict_types=1);

namespace App\Tests\RepoMap;

use App\RepoMap\CachedRepoMapBuilder;
use App\RepoMap\RepoMapBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Random\RandomException;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(CachedRepoMapBuilder::class)]
final class CachedRepoMapBuilderTest extends TestCase
{
    private RepoMapBuilder&MockObject $repoMapBuilder;
    private CacheItemPoolInterface&MockObject $cache;
    private CachedRepoMapBuilder $builder;
    private string $tempDir;

    /**
     * @throws RandomException
     */
    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir().'/test_'.bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0777, true);

        $this->repoMapBuilder = $this->createMock(RepoMapBuilder::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->builder = new CachedRepoMapBuilder($this->repoMapBuilder, $this->cache);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testBuildMapUsesCacheWhenHashUnchanged(): void
    {
        // ÉTANT DONNÉ
        file_put_contents($this->tempDir.'/Test.php', '<?php class Test {}');
        $currentHash = md5('Test.php:'.filemtime($this->tempDir.'/Test.php'));

        $cachedHashItem = $this->createMock(CacheItemInterface::class);
        $cachedHashItem->method('isHit')->willReturn(true);
        $cachedHashItem->method('get')->willReturn($currentHash);

        $cachedMapItem = $this->createMock(CacheItemInterface::class);
        $cachedMapItem->method('isHit')->willReturn(true);
        $cachedMapItem->method('get')->willReturn('cached_repo_map_content');

        $this->cache->method('getItem')->willReturnMap([
            ['repo_map_hash', $cachedHashItem],
            ['repo_map_content', $cachedMapItem],
        ]);

        $this->repoMapBuilder->expects($this->never())->method('buildMap');

        // QUAND
        $result = $this->builder->buildMap($this->tempDir);

        // ALORS
        $this->assertSame('cached_repo_map_content', $result);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testBuildMapRegeneratesAndCachesWhenCacheIsMiss(): void
    {
        // ÉTANT DONNÉ
        file_put_contents($this->tempDir.'/Test.php', '<?php class Test {}');

        $cachedHashItem = $this->createMock(CacheItemInterface::class);
        $cachedHashItem->method('isHit')->willReturn(false);

        $cachedMapItem = $this->createMock(CacheItemInterface::class);
        $cachedMapItem->method('isHit')->willReturn(false);

        $this->cache->method('getItem')->willReturnMap([
            ['repo_map_hash', $cachedHashItem],
            ['repo_map_content', $cachedMapItem],
        ]);

        $this->repoMapBuilder->expects($this->once())
            ->method('buildMap')
            ->with($this->tempDir)
            ->willReturn('generated_repo_map_content');

        // QUAND
        $result = $this->builder->buildMap($this->tempDir);

        // ALORS
        $this->assertSame('generated_repo_map_content', $result);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testBuildMapInvalidatesCacheWhenDirectoryFilesModified(): void
    {
        // ÉTANT DONNÉ
        file_put_contents($this->tempDir.'/Test.php', '<?php class Test {}');

        $cachedHashItem = $this->createMock(CacheItemInterface::class);
        $cachedHashItem->method('isHit')->willReturn(true);
        $cachedHashItem->method('get')->willReturn('outdated_hash_value');

        $cachedMapItem = $this->createMock(CacheItemInterface::class);
        $cachedMapItem->method('isHit')->willReturn(true);
        $cachedMapItem->method('get')->willReturn('old_cached_map');

        $this->cache->method('getItem')->willReturnMap([
            ['repo_map_hash', $cachedHashItem],
            ['repo_map_content', $cachedMapItem],
        ]);

        $this->repoMapBuilder->expects($this->once())
            ->method('buildMap')
            ->with($this->tempDir)
            ->willReturn('updated_repo_map_content');

        // QUAND
        $result = $this->builder->buildMap($this->tempDir);

        // ALORS
        $this->assertSame('updated_repo_map_content', $result);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Test]
    public function testBuildMapHandlesNonExistentDirectory(): void
    {
        // ÉTANT DONNÉ
        $nonExistentDir = $this->tempDir.'/non_existent_dir';

        $cachedHashItem = $this->createMock(CacheItemInterface::class);
        $cachedHashItem->method('isHit')->willReturn(false);

        $cachedMapItem = $this->createMock(CacheItemInterface::class);
        $cachedMapItem->method('isHit')->willReturn(false);

        $this->cache->method('getItem')->willReturnMap([
            ['repo_map_hash', $cachedHashItem],
            ['repo_map_content', $cachedMapItem],
        ]);

        $this->repoMapBuilder->expects($this->once())
            ->method('buildMap')
            ->with($nonExistentDir)
            ->willReturn('empty_directory_map');

        // QUAND
        $result = $this->builder->buildMap($nonExistentDir);

        // ALORS
        $this->assertSame('empty_directory_map', $result);
    }
}
