<?php

declare(strict_types=1);

namespace Switon\Yaml\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Switon\Yaml\Exception\YamlParseException;
use Switon\Yaml\YamlReader;
use Switon\Yaml\Tests\Fixtures\YamlReaderProbe;

class YamlReaderTest extends TestCase
{
    public function testIgnoresBlankLinesAndFullLineComments(): void
    {
        $yaml = <<<YAML

# comment line
key: value

YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('value', $data['key']);
    }

    public function testParseReturnsEmptyArrayWhenDocumentHasNoContentLines(): void
    {
        $reader = new YamlReader();

        $this->assertSame([], $reader->parse(''));
        $this->assertSame([], $reader->parse("\n\n# only comments\n"));
    }

    public function testParseReturnsEmptyArrayWhenDocumentHasOnlyWhitespaceLines(): void
    {
        $reader = new YamlReader();

        $this->assertSame([], $reader->parse("   \n  \n"));
    }

    public function testParsesRootLevelSequence(): void
    {
        $yaml = <<<YAML
- alpha
- beta
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame(['alpha', 'beta'], $data);
    }

    public function testParsesSimpleMappingAndScalars(): void
    {
        $yaml = <<<YAML
name: Switon
enabled: true
count: 42
ratio: 0.5
none1: null
none2: ~
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('Switon', $data['name']);
        $this->assertTrue($data['enabled']);
        $this->assertSame(42, $data['count']);
        $this->assertSame(0.5, $data['ratio']);
        $this->assertNull($data['none1']);
        $this->assertNull($data['none2']);
    }

    public function testRejectsScalarOnlyRootDocument(): void
    {
        $reader = new YamlReader();
        $this->expectException(YamlParseException::class);
        $reader->parse("true\n");
    }

    public function testParsesSequence(): void
    {
        $yaml = <<<YAML
items:
  - one
  - two
  - three
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame(['one', 'two', 'three'], $data['items']);
    }

    public function testParsesNestedMappingAndSequence(): void
    {
        $yaml = <<<YAML
root:
  name: Switon
  tags:
    - core
    - yaml
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('Switon', $data['root']['name']);
        $this->assertSame(['core', 'yaml'], $data['root']['tags']);
    }

    public function testParsesSingleAndDoubleQuotedStrings(): void
    {
        $yaml = <<<YAML
single: 'It''s literal'
double: "line\\nnext"
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame("It's literal", $data['single']);
        $this->assertSame("line\nnext", $data['double']);
    }

    public function testInlineCommentsAreIgnored(): void
    {
        $yaml = <<<YAML
key: value  # comment
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('value', $data['key']);
    }

    public function testHashInsideQuotesIsNotAComment(): void
    {
        $yaml = <<<YAML
plain: "a # b"
single: 'a # b'
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('a # b', $data['plain']);
        $this->assertSame('a # b', $data['single']);
    }

    public function testTabsAreRejected(): void
    {
        $yaml = "key:\n\tvalue: 1\n";

        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $reader->parse($yaml);
    }

    public function testUnsupportedMultiDocumentMarkersAreRejected(): void
    {
        $yaml = <<<YAML
---
key: value
YAML;

        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $reader->parse($yaml);
    }

    public function testUnsupportedDocumentEndMarkerIsRejected(): void
    {
        $yaml = "...\nkey: value\n";

        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $reader->parse($yaml);
    }

    public function testUnsupportedAnchorsAndAliasesAreRejected(): void
    {
        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $reader->parse("a: &x 1\n");
    }

    public function testUnsupportedTagsAreRejected(): void
    {
        $yaml = '!tag value';

        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $reader->parse($yaml);
    }

    public function testParsesLiteralBlockScalarWithIndentationIndicatorAtRoot(): void
    {
        $yaml = <<<'YAML'
key: |2
  line
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('line', $data['key']);
    }

    public function testParsesLiteralBlockScalarWithIndentationIndicatorNested(): void
    {
        $yaml = <<<'YAML'
root:
  k: |2
    hi
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('hi', $data['root']['k']);
    }

    public function testRejectsBlockScalarIndentationIndicatorZero(): void
    {
        $yaml = <<<'YAML'
key: |0
  x
YAML;

        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('indentation indicator must be at least 1');
        $reader->parse($yaml);
    }

    public function testRejectsInvalidBlockScalarHeader(): void
    {
        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Invalid block scalar header');
        $reader->parse("key: |bad\n  x\n");
    }

    public function testRejectsBlockScalarHeaderWithDuplicateChomp(): void
    {
        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Invalid block scalar header');
        $reader->parse("key: |+-\n  x\n");
    }

    public function testRejectsBlockScalarHeaderTrailingGarbageAfterIndentDigits(): void
    {
        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Invalid block scalar header');
        $reader->parse("key: |2x\n  hi\n");
    }

    public function testRejectsTabCharacterInsideLiteralBlockScalarBody(): void
    {
        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Tabs are not allowed');

        $reader->parse("key: |\n  ok\n\tbad\n");
    }

    public function testRejectsInconsistentIndentationInsideLiteralBlockScalarBody(): void
    {
        $yaml = <<<'YAML'
x:
  k: |
      sixspaces
    fourspaces
YAML;

        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Inconsistent indentation in block scalar');

        $reader->parse($yaml);
    }

    public function testUnsupportedFlowCollectionsAreRejected(): void
    {
        $yaml = 'map: { a: 1 }';

        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $reader->parse($yaml);
    }

    public function testRejectsNestedFlowSequence(): void
    {
        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Nested flow collections');
        $reader->parse('x: [1, [2]]');
    }

    public function testRejectsFlowSequenceItemThatIsFlowMapping(): void
    {
        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Nested flow collections');
        $reader->parse('x: [{a: 1}]');
    }

    public function testRejectsEmptyFlowSequenceItem(): void
    {
        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Flow sequence items must not be empty');
        $reader->parse('x: [a, , b]');
    }

    public function testRejectsMergeKey(): void
    {
        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Merge keys are not supported');
        $reader->parse("root:\n  <<: *base\n");
    }

    public function testRejectsSequenceItemAtMappingIndent(): void
    {
        $yaml = <<<YAML
a: 1
- not allowed here
YAML;

        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Sequence item not allowed in this context');
        $reader->parse($yaml);
    }

    public function testRejectsMappingLineWithoutColon(): void
    {
        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Invalid mapping entry (missing colon)');
        $reader->parse("key: ok\nnot a mapping line\n");
    }

    public function testRejectsRootIndentWhenFirstContentLineIsIndented(): void
    {
        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Root indentation must start at column 0');
        $reader->parse("  k: v\n");
    }

    public function testRejectsMappingEntryInsideSequenceContext(): void
    {
        $yaml = <<<'YAML'
- a
b: 1
YAML;

        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Mapping entry not allowed in this context');
        $reader->parse($yaml);
    }

    public function testRejectsEmptyMappingKey(): void
    {
        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Mapping key must not be empty');
        $reader->parse(': value' . "\n");
    }

    public function testRejectsInvalidSingleQuotedString(): void
    {
        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Invalid single-quoted string');
        $reader->parse("k: '\n");
    }

    public function testRejectsInvalidDoubleQuotedString(): void
    {
        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Invalid double-quoted string');
        $reader->parse("k: \"\n");
    }

    public function testRejectsAliasTokenAsMappingValue(): void
    {
        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Anchors and aliases are not supported');
        $reader->parse("ref: *anchor\n");
    }

    public function testRejectsInconsistentIndentation(): void
    {
        $yaml = <<<YAML
root:
   a: 1
  b: 2
YAML;

        $reader = new YamlReader();
        $this->expectException(YamlParseException::class);
        $reader->parse($yaml);
    }

    public function testRejectsDuplicateMappingKey(): void
    {
        $yaml = <<<YAML
key: a
key: b
YAML;

        $reader = new YamlReader();
        $this->expectException(YamlParseException::class);
        $reader->parse($yaml);
    }

    public function testParsesFlowSequenceOneLevelScalars(): void
    {
        $yaml = <<<YAML
nums: [1, 2, 3]
mix: [true, false, null, "~", "a,b", 'c']
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame([1, 2, 3], $data['nums']);
        $this->assertSame([true, false, null, '~', 'a,b', 'c'], $data['mix']);
    }

    public function testParsesFloatScalars(): void
    {
        $yaml = <<<YAML
pi: 3.14
neg: -2.5
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame(3.14, $data['pi']);
        $this->assertSame(-2.5, $data['neg']);
    }

    public function testParsesEmptyFlowSequence(): void
    {
        $yaml = 'items: []';

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame([], $data['items']);
    }

    public function testParsesIndentedMappingAfterKeyWithNoInlineValue(): void
    {
        $yaml = <<<'YAML'
parent:
  child: v
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame(['child' => 'v'], $data['parent']);
    }

    public function testParsesBareHyphenSequenceItemWithIndentedMapping(): void
    {
        $yaml = <<<'YAML'
items:
  -
    id: 1
    name: a
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame([['id' => 1, 'name' => 'a']], $data['items']);
    }

    public function testParsesRootBareHyphenFollowedByIndentedMapping(): void
    {
        $yaml = <<<'YAML'
-
  k: v
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame([['k' => 'v']], $data);
    }

    public function testParsesMappingValueNullWhenKeyHasNoIndentedContinuation(): void
    {
        $yaml = <<<'YAML'
lonely:
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertArrayHasKey('lonely', $data);
        $this->assertNull($data['lonely']);
    }

    public function testParsesSequenceMixingScalarItemsAndIndentedObjectItems(): void
    {
        $yaml = <<<'YAML'
items:
  - one
  -
    x: y
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame(['one', ['x' => 'y']], $data['items']);
    }

    public function testParsesFoldedBlockScalarJoinsLinesWithSpace(): void
    {
        $yaml = <<<'YAML'
key: >
  hello
  world
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('hello world', $data['key']);
    }

    public function testParsesFoldedBlockScalarStripChomping(): void
    {
        $yaml = <<<'YAML'
key: >-
  a
  b
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('a b', $data['key']);
    }

    public function testParsesFoldedBlockScalarBlankLineInsertsNewline(): void
    {
        $yaml = <<<'YAML'
key: >
  first
  
  second
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame("first\nsecond", $data['key']);
    }

    public function testParsesLiteralBlockScalarInMapping(): void
    {
        $yaml = <<<'YAML'
text: |
  line one
  line two

  after blank
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame("line one\nline two\n\nafter blank", $data['text']);
    }

    public function testParsesLiteralBlockScalarWithInlineCommentOnIndicatorLine(): void
    {
        $yaml = <<<'YAML'
text: |  # marker
  only
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('only', $data['text']);
    }

    public function testLiteralBlockScalarStopsAtIndentedCommentMatchingMappingLevel(): void
    {
        $yaml = <<<'YAML'
root:
  key: |
    content-line
  # ends block for key; comment shares indent with sibling keys
sibling: next
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('content-line', $data['root']['key']);
        $this->assertSame('next', $data['sibling']);
    }

    public function testParsesLiteralBlockScalarInSequence(): void
    {
        $yaml = <<<'YAML'
items:
  - |
    a
    b
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame(["a\nb"], $data['items']);
    }

    public function testLiteralBlockScalarEmptyWhenFollowedBySiblingKey(): void
    {
        $yaml = <<<'YAML'
root:
  a: |
  b: 1
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('', $data['root']['a']);
        $this->assertSame(1, $data['root']['b']);
    }

    public function testParsesLiteralBlockScalarStripChomping(): void
    {
        $yaml = <<<'YAML'
key: |-
  x
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('x', $data['key']);
    }

    public function testParsesLiteralBlockScalarKeepChompingRetainsTrailingBlankLines(): void
    {
        $yaml = <<<'YAML'
key: |+
  a
  b


YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame("a\nb\n\n", $data['key']);
    }

    public function testParsesLiteralBlockScalarClipChompingCollapsesTrailingBlankLines(): void
    {
        $yaml = <<<'YAML'
key: |
  a
  b


YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame("a\nb\n", $data['key']);
    }

    public function testParsesFoldedBlockScalarKeepChompingVsClip(): void
    {
        $yamlKeep = <<<'YAML'
k1: >+
  a
  b


YAML;
        $yamlClip = <<<'YAML'
k2: >
  a
  b


YAML;

        $reader = new YamlReader();
        $keep = $reader->parse($yamlKeep);
        $clip = $reader->parse($yamlClip);

        $this->assertSame("a b\n\n", $keep['k1']);
        $this->assertSame("a b\n", $clip['k2']);
    }

    public function testParsesLiteralBlockScalarHeaderIndentThenStripSameAsStripThenIndent(): void
    {
        $yamlA = <<<'YAML'
a: |2-
  xx
YAML;
        $yamlB = <<<'YAML'
b: |-2
  xx
YAML;

        $reader = new YamlReader();
        $data = $reader->parse("{$yamlA}\n{$yamlB}\n");

        $this->assertSame('xx', $data['a']);
        $this->assertSame('xx', $data['b']);
    }

    public function testParsesFoldedBlockScalarInSequence(): void
    {
        $yaml = <<<'YAML'
items:
  - >
    one
    two
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame(['one two'], $data['items']);
    }

    public function testParsesFoldedBlockScalarMoreIndentedLineForcesBreak(): void
    {
        $yaml = <<<'YAML'
key: >
  folded line
   more-indented line
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame("folded line\nmore-indented line", $data['key']);
    }

    public function testInterpolateRecursesIntoNestedMappings(): void
    {
        $yaml = <<<YAML
db:
  host: \${DB_HOST}
  port: \${DB_PORT:-3306}
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $resolved = $reader->interpolate($data, [
            'DB_HOST' => '127.0.0.1',
        ]);

        $this->assertSame('127.0.0.1', $resolved['db']['host']);
        $this->assertSame('3306', $resolved['db']['port']);
    }

    public function testInterpolateRaisesOnMalformedPlaceholderToken(): void
    {
        $yaml = <<<'YAML'
v: 'prefix${INCOMPLETE'
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Invalid placeholder syntax');

        $reader->interpolate($data, []);
    }

    public function testInterpolateReplacesPlaceholders(): void
    {
        $yaml = <<<YAML
host: \${DB_HOST}
port: \${DB_PORT:-5432}
name: \${db_host}
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $resolved = $reader->interpolate($data, [
            'DB_HOST' => 'localhost',
            'db_host' => 'db.local',
        ]);

        $this->assertSame('localhost', $resolved['host']);
        $this->assertSame('5432', $resolved['port']);
        $this->assertSame('db.local', $resolved['name']);
    }

    public function testInterpolateReplacesPlaceholdersInStrings(): void
    {
        $yaml = <<<YAML
v1: \${NAME}
v2: "\${NAME}"
v3: '\${NAME}'
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $resolved = $reader->interpolate($data, ['NAME' => 'x']);

        $this->assertSame('x', $resolved['v1']);
        $this->assertSame('x', $resolved['v2']);
        $this->assertSame('x', $resolved['v3']);
    }

    public static function provideInvalidPlaceholderBodySyntax(): iterable
    {
        yield 'underscore after brace' => [<<<'YAML'
v: ${_BAD}
YAML];

        yield 'dot inside name' => [<<<'YAML'
v: ${parameters.db_host}
YAML];
    }

    #[DataProvider('provideInvalidPlaceholderBodySyntax')]
    public function testInterpolateRejectsInvalidPlaceholderNames(string $yaml): void
    {
        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Invalid placeholder syntax');

        $reader->interpolate($data, []);
    }

    public function testInterpolateMissingVariableWithoutDefaultIsError(): void
    {
        $yaml = 'value: ${MISSING}';

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->expectException(YamlParseException::class);
        $reader->interpolate($data, []);
    }

    public function testInterpolateWithEmptyVariablesUsesDefault(): void
    {
        $yaml = 'value: ${NAME:-fallback}';

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $resolved = $reader->interpolate($data, []);

        $this->assertSame('fallback', $resolved['value']);
    }

    public function testInterpolateAllowsEmptyDefaultString(): void
    {
        $yaml = 'value: ${NAME:-}';

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $resolved = $reader->interpolate($data, []);

        $this->assertSame('', $resolved['value']);
    }

    public function testInterpolateLiteralDefaultIsUsedWhenVariableMissingOrEmpty(): void
    {
        $yaml = <<<YAML
one: \${VAR1:-default1}
two: \${VAR2:-default2}
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $resolved = $reader->interpolate($data, [
            'VAR1' => '',
        ]);

        $this->assertSame('default1', $resolved['one']);
        $this->assertSame('default2', $resolved['two']);
    }

    public function testInterpolateAcceptsScalarPlaceholderVariables(): void
    {
        $yaml = <<<YAML
port: \${PORT}
ratio: \${RATIO}
enabled: \${ENABLED}
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $resolved = $reader->interpolate($data, [
            'PORT' => 5432,
            'RATIO' => 1.5,
            'ENABLED' => true,
        ]);

        $this->assertSame('5432', $resolved['port']);
        $this->assertSame('1.5', $resolved['ratio']);
        $this->assertSame('true', $resolved['enabled']);
    }

    public function testInterpolateUsesEmptyDefaultWhenVariableIsEmpty(): void
    {
        $yaml = 'value: ${NAME:-}';

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $resolved = $reader->interpolate($data, [
            'NAME' => '',
        ]);

        $this->assertSame('', $resolved['value']);
    }

    public function testInterpolateSkipsNonScalarPlaceholderVariables(): void
    {
        $reader = new YamlReader();
        $data = $reader->parse('value: ${NAME}');

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Missing placeholder value for NAME');

        $reader->interpolate($data, [
            'NAME' => ['bad'],
        ]);
    }

    public function testInterpolateRejectsNestedPlaceholderInDefault(): void
    {
        $yaml = 'value: ${NAME:-${OTHER}}';

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->expectException(YamlParseException::class);
        $reader->interpolate($data, []);
    }

    public function testInterpolateReplacesMultiplePlaceholdersInOneString(): void
    {
        $yaml = <<<'YAML'
url: ${HOST}${PATH:-/api}
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $resolved = $reader->interpolate($data, [
            'HOST' => 'https://ex.example',
            'PATH' => '/v1',
        ]);

        $this->assertSame('https://ex.example/v1', $resolved['url']);
    }

    public function testInterpolateLeavesNonStringLeavesUnchanged(): void
    {
        $yaml = <<<YAML
count: 3
flag: true
nested:
  deep: 1
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $resolved = $reader->interpolate($data, []);

        $this->assertSame(3, $resolved['count']);
        $this->assertTrue($resolved['flag']);
        $this->assertSame(1, $resolved['nested']['deep']);
    }

    public function testInterpolateNormalizesFalseBooleanToString(): void
    {
        $yaml = <<<'YAML'
v: ${FLAG}
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $resolved = $reader->interpolate($data, ['FLAG' => false]);

        $this->assertSame('false', $resolved['v']);
    }

    public function testInterpolateNormalizesZeroIntegerToString(): void
    {
        $yaml = <<<'YAML'
v: ${N}
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $resolved = $reader->interpolate($data, ['N' => 0]);

        $this->assertSame('0', $resolved['v']);
    }

    public function testInterpolateResolvesPlaceholdersInRootSequenceStrings(): void
    {
        $yaml = <<<'YAML'
- ${A}
- ${B:-fallback}
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $resolved = $reader->interpolate($data, [
            'A' => 'first',
        ]);

        $this->assertSame(['first', 'fallback'], $resolved);
    }

    public function testInterpolateSkipsNullPlaceholderVariable(): void
    {
        $yaml = <<<'YAML'
v: ${NAME}
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Missing placeholder value for NAME');

        $reader->interpolate($data, [
            'NAME' => null,
        ]);
    }

    public function testNullAndBoolLiteralsAreCaseSensitive(): void
    {
        $yaml = <<<'YAML'
n1: NULL
n2: Null
b1: True
b2: FALSE
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('NULL', $data['n1']);
        $this->assertSame('Null', $data['n2']);
        $this->assertSame('True', $data['b1']);
        $this->assertSame('FALSE', $data['b2']);
    }

    public function testHexAndScientificNotationRemainPlainStrings(): void
    {
        $yaml = <<<'YAML'
sci: 1e10
hex: 0x10
plus: +42
dot: .5
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('1e10', $data['sci']);
        $this->assertSame('0x10', $data['hex']);
        $this->assertSame('+42', $data['plus']);
        $this->assertSame('.5', $data['dot']);
    }

    public function testQuotedMappingKeysStripQuotes(): void
    {
        $yaml = <<<'YAML'
aliases:
  '@books': '@runtime/books'
  "@vendor": /tmp/vendor
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('@runtime/books', $data['aliases']['@books']);
        $this->assertSame('/tmp/vendor', $data['aliases']['@vendor']);
        $this->assertArrayNotHasKey("'@books'", $data['aliases']);
    }

    public function testRejectsDuplicateKeyInNestedMapping(): void
    {
        $yaml = <<<'YAML'
root:
  a: 1
  a: 2
YAML;

        $reader = new YamlReader();
        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Duplicate mapping key "a"');
        $reader->parse($yaml);
    }

    public function testParsesClassicMacCarriageReturnLineSeparators(): void
    {
        $yaml = "k1: a\rk2: b";

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame(['k1' => 'a', 'k2' => 'b'], $data);
    }

    public function testFlowSequenceSupportsQuotedItemsWithEmbeddedCommas(): void
    {
        $yaml = <<<'YAML'
tags: ["a, b", 'c,d', plain]
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame(['a, b', 'c,d', 'plain'], $data['tags']);
    }

    public function testMappingInsertionOrderIsPreserved(): void
    {
        $yaml = <<<'YAML'
z: 1
y: 2
x: 3
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame(['z', 'y', 'x'], array_keys($data));
    }

    public function testNumericLookingMappingKeyParses(): void
    {
        $yaml = <<<'YAML'
123: port-name
007: leading-zero
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame('port-name', $data[123]);
        $this->assertSame('leading-zero', $data['007']);
    }

    public function testYamlParseExceptionInterpolatesLineIntoMessage(): void
    {
        $reader = new YamlReader();

        try {
            $reader->parse("root:\n  ok: 1\n\tbad");
            $this->fail('Expected YamlParseException');
        } catch (YamlParseException $e) {
            $this->assertStringContainsString('line 3', $e->getMessage());
        }
    }

    public function testInterpolateRejectsDefaultThatLooksLikeNestedPlaceholder(): void
    {
        $yaml = <<<'YAML'
v: ${NAME:-${OTHER}}
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Invalid placeholder syntax');

        $reader->interpolate($data, []);
    }

    public function testInterpolateAllowsLiteralAfterClosedPlaceholder(): void
    {
        $yaml = <<<'YAML'
v: ${A:-x}}suffix
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $resolved = $reader->interpolate($data, []);

        $this->assertSame('x}suffix', $resolved['v']);
    }

    public function testInterpolateRejectsOpeningBraceInsideDefaultSegment(): void
    {
        $yaml = <<<'YAML'
v: ${N:-pre{post}}
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Invalid placeholder syntax');

        $reader->interpolate($data, []);
    }

    public function testParsesNestedSequenceAtRoot(): void
    {
        $yaml = <<<'YAML'
-
  - a
  - b
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame([['a', 'b']], $data);
    }

    public function testParsesNestedSequenceUnderMappingKey(): void
    {
        $yaml = <<<'YAML'
root:
  -
    - x
    - 'y'
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertSame(['root' => [['x', 'y']]], $data);
    }

    public function testRejectsTabAnywhereOnLine(): void
    {
        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Tabs are not allowed');

        $reader->parse("key: value\twith-tab\n");
    }

    public function testRejectsMappingLineStartingWithDashWithoutSpaceWhenRootIsMap(): void
    {
        $yaml = <<<'YAML'
k: v
-not-a-sequence-item
YAML;

        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Invalid mapping entry (missing colon)');

        $reader->parse($yaml);
    }

    public function testInterpolateRejectsPlaceholderNameStartingWithDigit(): void
    {
        $yaml = <<<'YAML'
v: ${0VAR}
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Invalid placeholder syntax');

        $reader->interpolate($data, ['0VAR' => 'x']);
    }

    public function testRejectsAliasTokenAsSequenceItemValue(): void
    {
        $yaml = <<<'YAML'
items:
  - *ref
YAML;

        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Anchors and aliases are not supported');

        $reader->parse($yaml);
    }

    public function testRejectsAnchorTokenAsSequenceItemValue(): void
    {
        $yaml = <<<'YAML'
items:
  - &anchor 1
YAML;

        $reader = new YamlReader();

        $this->expectException(YamlParseException::class);
        $this->expectExceptionMessage('Anchors and aliases are not supported');

        $reader->parse($yaml);
    }

    public function testParsesNullWhenInlineValueIsOnlyComment(): void
    {
        $yaml = <<<'YAML'
k: # no scalar here
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $this->assertNull($data['k']);
    }

    public function testInterpolateSkipsStringsThatDoNotContainPlaceholderOpen(): void
    {
        $yaml = <<<'YAML'
plain: hello
with: ${A}
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $resolved = $reader->interpolate($data, ['A' => 'x']);

        $this->assertSame('hello', $resolved['plain']);
        $this->assertSame('x', $resolved['with']);
    }

    public function testInterpolateRecursesThroughSequenceItemsAndNestedMappings(): void
    {
        $yaml = <<<'YAML'
items:
  - ${HOST}
  -
    name: ${NAME:-guest}
    enabled: true
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);
        $resolved = $reader->interpolate($data, ['HOST' => '127.0.0.1']);

        $this->assertSame('127.0.0.1', $resolved['items'][0]);
        $this->assertSame('guest', $resolved['items'][1]['name']);
        $this->assertTrue($resolved['items'][1]['enabled']);
    }

    public function testInterpolateSubstitutesValueContainingDollarSign(): void
    {
        $yaml = <<<'YAML'
msg: ${TOKEN}
YAML;

        $reader = new YamlReader();
        $data = $reader->parse($yaml);

        $resolved = $reader->interpolate($data, ['TOKEN' => 'price is $5']);

        $this->assertSame('price is $5', $resolved['msg']);
    }

    public function testReadAppliesInterpolateAfterParseInOneCall(): void
    {
        $yaml = <<<'YAML'
app: ${APP_NAME}
port: 8080
YAML;

        $reader = new YamlReader();
        $data = $reader->read($yaml, ['APP_NAME' => 'demo']);

        $this->assertSame('demo', $data['app']);
        $this->assertSame(8080, $data['port']);
    }

    public function testReadWithExtraUnusedVariablesMatchesParseForPlainDocument(): void
    {
        $yaml = <<<'YAML'
a: 1
b: two
YAML;

        $reader = new YamlReader();
        $parsed = $reader->parse($yaml);
        $read = $reader->read($yaml, ['UNUSED' => 'ignored']);

        $this->assertSame($parsed, $read);
    }

    public function testReadResolvesDefaultsWhenVariablesEmpty(): void
    {
        $yaml = <<<'YAML'
role: ${ROLE:-viewer}
YAML;

        $reader = new YamlReader();

        $this->assertSame(['role' => 'viewer'], $reader->read($yaml, []));
    }

    public function testReadResolvesNestedStructureSameAsParseThenInterpolate(): void
    {
        $yaml = <<<'YAML'
items:
  - ${HOST}
  -
    name: ${NAME:-guest}
YAML;

        $reader = new YamlReader();
        $fromRead = $reader->read($yaml, ['HOST' => '127.0.0.1']);
        $fromChain = $reader->interpolate($reader->parse($yaml), ['HOST' => '127.0.0.1']);

        $this->assertSame($fromChain, $fromRead);
    }

    public function testLoadReadsPathViaFilesystem(): void
    {
        $filesystem = $this->createStub(\Switon\Core\FilesystemInterface::class);
        $filesystem->method('read')->willReturn("name: from-file\nenabled: true\n");

        $reader = (new YamlReaderProbe())->assignFilesystemForTest($filesystem);

        $data = $reader->load('~/ignored.yml');
        $this->assertSame('from-file', $data['name']);
        $this->assertTrue($data['enabled']);
    }

    public function testLoadInterpolatesPlaceholders(): void
    {
        $filesystem = $this->createStub(\Switon\Core\FilesystemInterface::class);
        $filesystem->method('read')->willReturn("host: \${HOST}\n");

        $reader = (new YamlReaderProbe())->assignFilesystemForTest($filesystem);

        $data = $reader->load('cfg.yml', ['HOST' => '127.0.0.1']);
        $this->assertSame('127.0.0.1', $data['host']);
    }
}
