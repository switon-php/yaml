<?php

declare(strict_types=1);

namespace Switon\Yaml\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Switon\Core\FilesystemInterface;
use Switon\Yaml\Exception\YamlParseException;
use Switon\Yaml\YamlReader;
use Switon\Yaml\YamlWriter;

class YamlWriterTest extends TestCase
{
    protected function writer(?FilesystemInterface $filesystem = null): YamlWriter
    {
        return new TestYamlWriter($filesystem);
    }

    protected function parse(string $yaml): array
    {
        return (new YamlReader())->parse($yaml);
    }

    public function testDumpQuotesKeysAndScalarsAndNestedSequences(): void
    {
        $yaml = $this->writer()->dump([
            'plain' => 'ok',
            'empty' => '',
            'space' => ' leading',
            'dash' => '-dash',
            'newline' => "a\nb",
            'colon' => 'a: b',
            'hash' => 'a#b',
            'quote' => "a'b",
            'bool' => true,
            'null' => null,
            'int' => 1,
            'float' => 1.5,
            'numeric key' => [
                1 => 'one',
                'list' => [
                    ['id' => 1],
                    ['id' => 2],
                ],
                'nested list' => [
                    ['x' => ['y' => 'z']],
                ],
            ],
        ]);

        $this->assertStringContainsString("plain: ok", $yaml);
        $this->assertStringContainsString("empty: ''", $yaml);
        $this->assertStringContainsString("space: ' leading'", $yaml);
        $this->assertStringContainsString("dash: '-dash'", $yaml);
        $this->assertStringContainsString('newline: "a\nb"', $yaml);
        $this->assertStringContainsString("colon: 'a: b'", $yaml);
        $this->assertStringContainsString("hash: 'a#b'", $yaml);
        $this->assertStringContainsString("quote: 'a''b'", $yaml);
        $this->assertStringContainsString("bool: true", $yaml);
        $this->assertStringContainsString("null: null", $yaml);
        $this->assertStringContainsString("int: 1", $yaml);
        $this->assertStringContainsString("float: 1.5", $yaml);
        $this->assertStringContainsString("'numeric key':", $yaml);
        $this->assertStringContainsString("'1': one", $yaml);
        $this->assertStringContainsString("id: 1", $yaml);
        $this->assertStringContainsString("x:", $yaml);
    }

    public function testDumpEmptyMappingIsEmptyString(): void
    {
        $this->assertSame('', $this->writer()->dump([]));
    }

    public function testDumpEmptyNestedListUsesFlowEmpty(): void
    {
        $this->assertSame("tags: []", $this->writer()->dump(['tags' => []]));
    }

    public function testDumpShortScalarListUsesFlow(): void
    {
        $yaml = $this->writer()->dump([
            'scalars' => [null, true, false, 0, 1.5, 'ok', ''],
        ]);

        $this->assertSame("scalars: [null, true, false, 0, 1.5, ok, '']", $yaml);
        $this->assertSame(
            ['scalars' => [null, true, false, 0, 1.5, 'ok', '']],
            $this->parse($yaml),
        );
    }

    public function testDumpLongScalarListUsesBlock(): void
    {
        $items = [];
        for ($i = 0; $i < 20; $i++) {
            $items[] = 'item-' . $i;
        }
        $yaml = $this->writer()->dump(['items' => $items]);

        $this->assertStringStartsWith("items:\n  - item-0\n", $yaml);
        $this->assertSame(['items' => $items], $this->parse($yaml));
    }

    public function testDumpAssociativeArrayAlwaysUsesBlock(): void
    {
        $yaml = $this->writer()->dump([
            'app' => ['name' => 'demo', 'debug' => false],
        ]);

        $this->assertSame(<<<'YAML'
app:
  name: demo
  debug: false
YAML, $yaml);
        $this->assertSame(
            ['app' => ['name' => 'demo', 'debug' => false]],
            $this->parse($yaml),
        );
    }

    public function testDumpNestedMappingsAndSequences(): void
    {
        $yaml = $this->writer()->dump([
            'nested' => [
                ['a' => 1],
                ['b' => [2, 3]],
            ],
        ]);

        $this->assertSame(<<<'YAML'
nested:
  -
    a: 1
  -
    b: [2, 3]
YAML, $yaml);
        $this->assertSame(
            ['nested' => [['a' => 1], ['b' => [2, 3]]]],
            $this->parse($yaml),
        );
    }

    public function testDumpNestedListsPreferFlowItemsWhenShort(): void
    {
        $yaml = $this->writer()->dump([
            'matrix' => [[1, 2], [3]],
        ]);

        $this->assertSame(<<<'YAML'
matrix:
  - [1, 2]
  - [3]
YAML, $yaml);
        $this->assertSame(['matrix' => [[1, 2], [3]]], $this->parse($yaml));
    }

    public function testDumpEmptyListItemUsesFlowEmpty(): void
    {
        $yaml = $this->writer()->dump(['empty_item' => [[]]]);

        $this->assertSame("empty_item:\n  - []", $yaml);
        $this->assertSame(['empty_item' => [[]]], $this->parse($yaml));
    }

    public function testDumpRoundTripsThroughYamlReader(): void
    {
        $data = [
            'app' => [
                'name' => 'demo',
                'debug' => false,
                'ports' => [80, 443],
            ],
            'empty' => [],
            'items' => [
                ['id' => 1, 'label' => 'a'],
                ['id' => 2, 'label' => 'b:c'],
            ],
        ];

        $yaml = $this->writer()->dump($data);

        $this->assertSame($data, $this->parse($yaml));
    }

    public function testDumpRoundTripsQuotedKeys(): void
    {
        $data = [
            '_ok' => 1,
            'a-b' => 2,
            '12' => 3,
            'has space' => 4,
            "o'brien" => 5,
        ];

        $yaml = $this->writer()->dump($data);

        $this->assertSame(<<<'YAML'
_ok: 1
'a-b': 2
'12': 3
'has space': 4
'o''brien': 5
YAML, $yaml);
        $this->assertSame($data, $this->parse($yaml));
    }

    public function testDumpQuotesIntegerLookingKeys(): void
    {
        $yaml = $this->writer()->dump([0 => 'zero', 10 => 'ten']);

        $this->assertSame("'0': zero\n'10': ten", $yaml);
        $this->assertSame(['0' => 'zero', '10' => 'ten'], $this->parse($yaml));
    }

    #[DataProvider('provideQuotedScalars')]
    public function testDumpQuotesSpecialScalars(string $value, string $expectedInline): void
    {
        $yaml = $this->writer()->dump(['v' => $value]);

        $this->assertSame('v: ' . $expectedInline, $yaml);
        $this->assertSame(['v' => $value], $this->parse($yaml));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function provideQuotedScalars(): array
    {
        return [
            'empty' => ['', "''"],
            'leading space' => [' leading', "' leading'"],
            'leading dash' => ['-dash', "'-dash'"],
            'colon space' => ['a: b', "'a: b'"],
            'colon' => ['a:b', "'a:b'"],
            'hash' => ['a#b', "'a#b'"],
            'single quote' => ["a'b", "'a''b'"],
            'at' => ['@x', "'@x'"],
            'brace' => ['{x}', "'{x}'"],
            'bracket' => ['[x]', "'[x]'"],
            'pipe' => ['|', "'|'"],
            'folded' => ['>', "'>'"],
            'percent' => ['%', "'%'"],
            'amp' => ['&', "'&'"],
            'star' => ['*', "'*'"],
            'bang' => ['!', "'!'"],
            'double quote' => ['"', "'\"'"],
            'backslash' => ['\\', "'\\'"],
            'comma' => ['a,b', "'a,b'"],
            'backtick' => ['`x`', "'`x`'"],
        ];
    }

    public function testDumpShortMultilineUsesDoubleQuoted(): void
    {
        $yaml = $this->writer()->dump(['v' => "a\nb"]);

        $this->assertSame('v: "a\nb"', $yaml);
        $this->assertSame(['v' => "a\nb"], $this->parse($yaml));
    }

    public function testDumpLongMultilineUsesLiteralBlock(): void
    {
        $text = "line-one-with-enough-content-to-exceed-width\nline-two-also-long-enough\nline-three";
        $yaml = $this->writer()->dump(['body' => $text], 0, 40);

        $this->assertSame(<<<'YAML'
body: |-
  line-one-with-enough-content-to-exceed-width
  line-two-also-long-enough
  line-three
YAML, $yaml);
        $this->assertSame(['body' => $text], $this->parse($yaml));
    }

    public function testDumpMultilineWithTrailingNewlineUsesClipLiteral(): void
    {
        $text = "a\nb\n";
        $yaml = $this->writer()->dump(['body' => $text], 0, 10);

        $this->assertSame("body: |\n  a\n  b\n", $yaml);
        $this->assertSame(['body' => $text], $this->parse($yaml));
    }

    public function testDumpLeavesPlainScalarsUnquoted(): void
    {
        $yaml = $this->writer()->dump([
            'name' => 'Switon',
            'under_score' => 'ok',
            'path' => 'a/b',
        ]);

        $this->assertSame(<<<'YAML'
name: Switon
under_score: ok
path: a/b
YAML, $yaml);
        $this->assertSame(
            ['name' => 'Switon', 'under_score' => 'ok', 'path' => 'a/b'],
            $this->parse($yaml),
        );
    }

    public function testDumpTrailingSpaceScalarIsNotQuoted(): void
    {
        $yaml = $this->writer()->dump(['trail' => 'x ']);

        $this->assertSame('trail: x ', $yaml);
        // Reader trims plain scalars, so trailing spaces do not round-trip.
        $this->assertSame(['trail' => 'x'], $this->parse($yaml));
    }

    public function testDumpUnsupportedScalarTypesBecomeEmptyQuotedString(): void
    {
        $yaml = $this->writer()->dump(['obj' => new stdClass()]);

        $this->assertSame("obj: ''", $yaml);
        $this->assertSame(['obj' => ''], $this->parse($yaml));
    }

    public function testDumpFalseAndNestedEmpty(): void
    {
        $yaml = $this->writer()->dump([
            'enabled' => false,
            'meta' => [
                'tags' => [],
            ],
        ]);

        $this->assertSame(<<<'YAML'
enabled: false
meta:
  tags: []
YAML, $yaml);
        $this->assertSame(
            ['enabled' => false, 'meta' => ['tags' => []]],
            $this->parse($yaml),
        );
    }

    public function testDumpRespectsBaseIndent(): void
    {
        $yaml = $this->writer()->dump(['a' => 1, 'b' => ['c' => 2]], 2);

        $this->assertSame(<<<'YAML'
    a: 1
    b:
      c: 2
YAML, $yaml);
    }

    public function testDumpBaseIndentOnSequences(): void
    {
        $yaml = $this->writer()->dump(['items' => ['one', 'two']], 1);

        $this->assertSame('  items: [one, two]', $yaml);
    }

    public function testDumpRespectsCustomLineWidth(): void
    {
        $yaml = $this->writer()->dump(['tags' => ['one', 'two', 'three']], 0, 10);

        $this->assertSame(<<<'YAML'
tags:
  - one
  - two
  - three
YAML, $yaml);
    }

    public function testDumpLineWidthBoundaryKeepsFlowWhenEqual(): void
    {
        // "tags: [ab]" length is 10
        $yaml = $this->writer()->dump(['tags' => ['ab']], 0, 10);

        $this->assertSame('tags: [ab]', $yaml);
    }

    public function testDumpLineWidthBoundaryBreaksWhenOneOver(): void
    {
        // "tags: [abc]" length is 11
        $yaml = $this->writer()->dump(['tags' => ['abc']], 0, 10);

        $this->assertSame("tags:\n  - abc", $yaml);
    }

    public function testDumpNestedListTooWideUsesBlockSequence(): void
    {
        $yaml = $this->writer()->dump([
            'matrix' => [
                ['aaaaaaaa', 'bbbbbbbb', 'cccccccc'],
            ],
        ], 0, 20);

        $this->assertSame(<<<'YAML'
matrix:
  -
    - aaaaaaaa
    - bbbbbbbb
    - cccccccc
YAML, $yaml);
        $this->assertSame(
            ['matrix' => [['aaaaaaaa', 'bbbbbbbb', 'cccccccc']]],
            $this->parse($yaml),
        );
    }

    public function testDumpListOfMapsAndScalarsMixed(): void
    {
        $data = [
            'items' => [
                'plain',
                ['id' => 1, 'name' => 'x'],
                null,
                true,
            ],
        ];
        $yaml = $this->writer()->dump($data);

        $this->assertSame(<<<'YAML'
items:
  - plain
  -
    id: 1
    name: x
  - null
  - true
YAML, $yaml);
        $this->assertSame($data, $this->parse($yaml));
    }

    public function testDumpListContainingNestedObjectListsForcesBlock(): void
    {
        $data = [
            'groups' => [
                [['a' => 1], ['b' => 2]],
            ],
        ];
        $yaml = $this->writer()->dump($data);

        $this->assertSame(<<<'YAML'
groups:
  -
    -
      a: 1
    -
      b: 2
YAML, $yaml);
        $this->assertSame($data, $this->parse($yaml));
    }

    public function testDumpShortMultilineInSequenceUsesDoubleQuoted(): void
    {
        $yaml = $this->writer()->dump([
            'notes' => ["a\nb", 'ok'],
        ]);

        $this->assertSame("notes: [\"a\\nb\", ok]", $yaml);
        $this->assertSame(['notes' => ["a\nb", 'ok']], $this->parse($yaml));
    }

    public function testDumpShortMultilineInBlockSequenceUsesDoubleQuotedItem(): void
    {
        // Parent list is not scalar-only, so items emit in block form.
        $yaml = $this->writer()->dump([
            'notes' => [
                ['id' => 1],
                "a\nb",
            ],
        ]);

        $this->assertSame(<<<'YAML'
notes:
  -
    id: 1
  - "a\nb"
YAML, $yaml);
        $this->assertSame(
            ['notes' => [['id' => 1], "a\nb"]],
            $this->parse($yaml),
        );
    }

    public function testDumpLongMultilineInSequenceUsesLiteralItem(): void
    {
        $text = "alpha-line-that-is-quite-long\nbeta-line-that-is-quite-long";
        $yaml = $this->writer()->dump(['notes' => [$text]], 0, 25);

        $this->assertSame(<<<'YAML'
notes:
  - |-
    alpha-line-that-is-quite-long
    beta-line-that-is-quite-long
YAML, $yaml);
        $this->assertSame(['notes' => [$text]], $this->parse($yaml));
    }

    public function testDumpLongMultilineSequenceItemWithTrailingNewline(): void
    {
        $text = "alpha\nbeta\n";
        $yaml = $this->writer()->dump(['notes' => [$text]], 0, 8);

        $this->assertSame("notes:\n  - |\n    alpha\n    beta\n", $yaml);
        $this->assertSame(['notes' => [$text]], $this->parse($yaml));
    }

    public function testDumpTabInStringUsesDoubleQuotedEvenWhenWide(): void
    {
        $text = "a\tb\nmore-content-to-exceed-a-narrow-width-budget";
        $yaml = $this->writer()->dump(['v' => $text], 0, 10);

        // Tab cannot live in a block scalar line; fall back to double-quoted.
        $this->assertSame('v: "a\tb\nmore-content-to-exceed-a-narrow-width-budget"', $yaml);
        $this->assertSame(['v' => $text], $this->parse($yaml));
    }

    public function testDumpCarriageReturnInStringUsesDoubleQuoted(): void
    {
        $yaml = $this->writer()->dump(['v' => "a\rb"], 0, 5);

        $this->assertSame('v: "a\rb"', $yaml);
        $this->assertSame(['v' => "a\rb"], $this->parse($yaml));
    }

    public function testDumpTabWithoutNewlineAsMappingValue(): void
    {
        $yaml = $this->writer()->dump(['v' => "a\tb"]);

        $this->assertSame('v: "a\tb"', $yaml);
        $this->assertSame(['v' => "a\tb"], $this->parse($yaml));
    }

    public function testDumpLiteralPreservesBlankLinesInMiddle(): void
    {
        $text = "a\n\nb";
        $yaml = $this->writer()->dump(['body' => $text], 0, 5);

        $this->assertSame(<<<'YAML'
body: |-
  a

  b
YAML, $yaml);
        $this->assertSame(['body' => $text], $this->parse($yaml));
    }

    public function testDumpLiteralBeforeSiblingKeyRoundTrips(): void
    {
        $data = [
            'body' => "a\nb\n",
            'next' => 1,
        ];
        $yaml = $this->writer()->dump($data, 0, 8);

        $this->assertSame($data, $this->parse($yaml));
        $this->assertStringContainsString("next: 1", $yaml);
    }

    public function testDumpFlowQuotesSpecialScalarItems(): void
    {
        $data = [
            'tags' => ['a:b', ' x', '-y', "a'b", '', null, false],
        ];
        $yaml = $this->writer()->dump($data);

        $this->assertSame("tags: ['a:b', ' x', '-y', 'a''b', '', null, false]", $yaml);
        $this->assertSame($data, $this->parse($yaml));
    }

    public function testDumpDeepNestingRoundTrips(): void
    {
        $data = [
            'a' => [
                'b' => [
                    'c' => [
                        'list' => [1, ['k' => 'v'], [2, 3]],
                        'text' => "short\n",
                    ],
                ],
            ],
        ];
        $yaml = $this->writer()->dump($data);

        $this->assertSame($data, $this->parse($yaml));
    }

    public function testDumpNonListArrayWithGapIsAssociativeBlock(): void
    {
        $data = [
            'weird' => [
                0 => 'a',
                2 => 'b',
            ],
        ];
        $yaml = $this->writer()->dump($data);

        $this->assertSame(<<<'YAML'
weird:
  '0': a
  '2': b
YAML, $yaml);
        $this->assertSame(['weird' => ['0' => 'a', '2' => 'b']], $this->parse($yaml));
    }

    public function testDumpZeroFloatAndNegativeNumbers(): void
    {
        $yaml = $this->writer()->dump([
            'nums' => [0.0, -1, -2.5],
        ]);

        $this->assertSame('nums: [0, -1, -2.5]', $yaml);
        $parsed = $this->parse($yaml);
        // Plain `0` re-parses as int; floats keep a decimal point.
        $this->assertSame(0, $parsed['nums'][0]);
        $this->assertSame(-1, $parsed['nums'][1]);
        $this->assertSame(-2.5, $parsed['nums'][2]);
    }

    public function testDumpLeadingZeroKeyRoundTrips(): void
    {
        $yaml = $this->writer()->dump(['01' => 'padded']);

        $this->assertSame("'01': padded", $yaml);
        $this->assertSame(['01' => 'padded'], $this->parse($yaml));
    }

    public function testDumpEmptyKeyIsEmittedButNotReadable(): void
    {
        $yaml = $this->writer()->dump(['' => 'empty-key']);

        $this->assertSame("'': empty-key", $yaml);
        $this->expectException(YamlParseException::class);
        $this->parse($yaml);
    }

    public function testDumpDoubleQuotedEscapesBackslashAndQuote(): void
    {
        $text = "say \"hi\"\\\nnext";
        $yaml = $this->writer()->dump(['v' => $text]);

        $this->assertSame('v: "say \\"hi\\"\\\\\nnext"', $yaml);
        $this->assertSame(['v' => $text], $this->parse($yaml));
    }

    public function testDumpIndentAffectsFlowBudget(): void
    {
        // Without indent: "tags: [one, two]" fits in 20.
        // With indent 4 (8 spaces): exceeds 20 and becomes block.
        $yaml = $this->writer()->dump(['tags' => ['one', 'two']], 4, 20);

        $this->assertSame(<<<'YAML'
        tags:
          - one
          - two
YAML, $yaml);
    }

    public function testWritePassesLineWidthThrough(): void
    {
        $filesystem = $this->createMock(FilesystemInterface::class);
        $filesystem->expects($this->once())
            ->method('write')
            ->with('/tmp/w.yml', "tags:\n  - one\n  - two\n");

        $this->writer($filesystem)->write('/tmp/w.yml', ['tags' => ['one', 'two']], 0, 10);
    }

    public function testWriteAppendsTrailingNewline(): void
    {
        $filesystem = $this->createMock(FilesystemInterface::class);
        $filesystem->expects($this->once())
            ->method('write')
            ->with('/tmp/config.yml', "name: demo\n");

        $this->writer($filesystem)->write('/tmp/config.yml', ['name' => 'demo']);
    }

    public function testWriteLeavesEmptyDumpEmpty(): void
    {
        $filesystem = $this->createMock(FilesystemInterface::class);
        $filesystem->expects($this->once())
            ->method('write')
            ->with('/tmp/empty.yml', '');

        $this->writer($filesystem)->write('/tmp/empty.yml', []);
    }

    public function testWriteRespectsBaseIndentAndTrailingNewline(): void
    {
        $filesystem = $this->createMock(FilesystemInterface::class);
        $filesystem->expects($this->once())
            ->method('write')
            ->with('/tmp/indented.yml', "  name: demo\n");

        $this->writer($filesystem)->write('/tmp/indented.yml', ['name' => 'demo'], 1);
    }

    public function testWriteDoesNotDoubleTrailingNewline(): void
    {
        $filesystem = $this->createMock(FilesystemInterface::class);
        $filesystem->expects($this->once())
            ->method('write')
            ->with('/tmp/already.yml', "a: 1\n");

        $writer = new class ($filesystem) extends YamlWriter {
            public function __construct(FilesystemInterface $filesystem)
            {
                $this->filesystem = $filesystem;
            }

            public function dump(
                array $data,
                int $baseIndent = 0,
                int $lineWidth = 80,
            ): string {
                return "a: 1\n";
            }
        };

        $writer->write('/tmp/already.yml', ['a' => 1]);
    }

    public function testSingleQuotedEscapesApostrophes(): void
    {
        $this->assertSame("'ok'", $this->writer()->singleQuoted('ok'));
        $this->assertSame("'a''b'", $this->writer()->singleQuoted("a'b"));
        $this->assertSame("''", $this->writer()->singleQuoted(''));
    }
}

/** @internal */
class TestYamlWriter extends YamlWriter
{
    public function __construct(?FilesystemInterface $filesystem = null)
    {
        if ($filesystem !== null) {
            $this->filesystem = $filesystem;
        }
    }
}
