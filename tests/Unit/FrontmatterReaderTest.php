<?php

declare(strict_types=1);

namespace Switon\Yaml\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Core\FilesystemInterface;
use Switon\Yaml\Exception\YamlParseException;
use Switon\Yaml\FrontmatterReader;
use Switon\Yaml\YamlReader;
use Switon\Yaml\YamlReaderInterface;

class FrontmatterReaderTest extends TestCase
{
    protected function reader(?FilesystemInterface $filesystem = null): FrontmatterReader
    {
        return new TestFrontmatterReader(new YamlReader(), $filesystem);
    }

    public function testParsesMetaBodyAndId(): void
    {
        $markdown = <<<'MD'
---
id: host
tools:
  - todo_create
---
## Heading

Body line.
MD;

        $doc = $this->reader()->parse($markdown);

        $this->assertNotNull($doc);
        $this->assertSame('host', $doc->meta['id']);
        $this->assertSame(['todo_create'], $doc->meta['tools']);
        $this->assertStringContainsString('Body line.', $doc->body);
    }

    public function testReturnsNullWithoutFrontmatterFence(): void
    {
        $reader = $this->reader();

        $this->assertNull($reader->parse("# Title\n\nNo frontmatter."));
        $this->assertNull($reader->parse("---\nunclosed"));
    }

    public function testReadLeavesIdUnsetWhenFrontmatterOmitsIt(): void
    {
        $markdown = "---\ndescription: x\n---\nbody";
        $filesystem = $this->createStub(FilesystemInterface::class);
        $filesystem->method('read')->willReturn($markdown);

        $doc = $this->reader($filesystem)->read('/tmp/directives/task.md');

        $this->assertNotNull($doc);
        $this->assertArrayNotHasKey('id', $doc->meta);
        $this->assertSame('body', $doc->body);
    }

    public function testPropagatesYamlParseErrors(): void
    {
        $this->expectException(YamlParseException::class);
        $this->reader()->parse("---\nkey:\n\tbad: 1\n---\nbody");
    }
}

/** @internal */
class TestFrontmatterReader extends FrontmatterReader
{
    public function __construct(YamlReaderInterface $yamlReader, ?FilesystemInterface $filesystem = null)
    {
        $this->yamlReader = $yamlReader;
        if ($filesystem !== null) {
            $this->filesystem = $filesystem;
        }
    }
}
