<?php

declare(strict_types=1);

namespace Switon\Yaml;

use Switon\Core\Attribute\Autowired;
use Switon\Core\FilesystemInterface;
use Switon\Core\Json;
use Switon\Core\Strings;
use Switon\Yaml\Exception\YamlParseException;

use function ctype_digit;
use function is_array;
use function is_string;
use function preg_match;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * Default implementation of the Switon YAML subset reader.
 *
 * Behaviour is defined by <code>subset.md</code> and is intentionally
 * limited to a strict, predictable subset of YAML.
 *
 * Parse handles YAML syntax only. Placeholder resolution is a separate
 * <code>interpolate()</code> step that scans parsed string values, including
 * values that originally came from single-quoted scalars.
 *
 * Block scalars (<code>|</code> literal, <code>&gt;</code> folded) support chomping
 * (<code>+</code>/<code>-</code>) and an indentation indicator (digits), matching common
 * formatters; see <code>subset.md</code>.
 *
 * Road-signs:
 * - load(): path via {@see FilesystemInterface} then read()
 * - read(): parse + interpolate pipeline
 * - parse(): subset parser and structural validation
 * - interpolate(): placeholder expansion over parsed arrays
 */
class YamlReader implements YamlReaderInterface
{
    #[Autowired] protected FilesystemInterface $filesystem;

    /**
     * Load one YAML-subset document from a filesystem path.
     */
    public function load(string $file, array $variables = []): array
    {
        return $this->read($this->filesystem->read($file), $variables);
    }

    /**
     * Parse and interpolate one YAML-subset document.
     */
    public function read(string $yaml, array $variables = []): array
    {
        return $this->interpolate($this->parse($yaml), $variables);
    }

    /**
     * Parse one YAML-subset document into a PHP array tree.
     */
    public function parse(string $yaml): array
    {
        $lines = Strings::rawLines($yaml);

        /** @var list<array{indent: int, type: 'map'|'seq', value: mixed, pendingKey: string|null, pendingSeq: bool}> $stack */
        $stack = [];
        $root = null;

        $lineCount = count($lines);
        for ($index = 0; $index < $lineCount; $index++) {
            $rawLine = $lines[$index];
            $lineNumber = $index + 1;
            $line = rtrim($rawLine, "\r\n");

            // Empty or comment-only lines are ignored
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed[0] === '#') {
                continue;
            }

            // Disallow tabs
            if (str_contains($line, "\t")) {
                YamlParseException::raise('Tabs are not allowed in indentation (line {line})', [
                    'line' => $lineNumber,
                ]);
            }

            $indent = $this->countLeadingSpaces($line);

            // Reject unsupported top-level constructs (subset constraints).
            if ($trimmed === '---' || $trimmed === '...') {
                YamlParseException::raise('Multi-document markers are not allowed (line {line})', [
                    'line' => $lineNumber,
                ]);
            }
            if ($trimmed[0] === '!') {
                YamlParseException::raise('Tags are not supported (line {line})', [
                    'line' => $lineNumber,
                ]);
            }

            // Move up stack to current indentation.
            while ($stack !== [] && $indent < $stack[count($stack) - 1]['indent']) {
                array_pop($stack);
            }

            // Root must start at indent 0.
            if ($root === null && $indent !== 0) {
                YamlParseException::raise('Root indentation must start at column 0 (line {line})', [
                    'line' => $lineNumber,
                ]);
            }

            // If we have a pending container to create (mapping key with empty value, or sequence item '-'),
            // and indentation increased, create the child container based on current line kind.
            if ($stack !== []) {
                $topIdx = count($stack) - 1;
                $top = &$stack[$topIdx];

                if ($indent > $top['indent'] && ($top['pendingKey'] !== null || $top['pendingSeq'])) {
                    $childType = str_starts_with($trimmed, '-') ? 'seq' : 'map';
                    $child = [];

                    if ($top['pendingKey'] !== null) {
                        $top['value'][$top['pendingKey']] = $child;
                        $top['pendingKey'] = null;
                    } else {
                        // pending sequence item
                        $top['value'][] = $child;
                        $top['pendingSeq'] = false;
                    }

                    $stack[] = [
                        'indent' => $indent,
                        'type' => $childType,
                        'value' => &$top['value'][array_key_last($top['value'])],
                        'pendingKey' => null,
                        'pendingSeq' => false,
                    ];
                }
            }

            // Establish current context.
            if ($root === null) {
                $rootType = str_starts_with($trimmed, '-') ? 'seq' : 'map';
                $root = [];
                $stack[] = [
                    'indent' => 0,
                    'type' => $rootType,
                    'value' => &$root,
                    'pendingKey' => null,
                    'pendingSeq' => false,
                ];
            }

            $ctx = &$stack[count($stack) - 1];
            if ($indent !== $ctx['indent']) {
                // Indentation must match current container level.
                YamlParseException::raise('Inconsistent indentation (line {line})', [
                    'line' => $lineNumber,
                ]);
            }

            if ($this->isSequenceItemLine($trimmed) || $trimmed === '-') {
                if ($ctx['type'] !== 'seq') {
                    YamlParseException::raise('Sequence item not allowed in this context (line {line})', [
                        'line' => $lineNumber,
                    ]);
                }

                if ($trimmed === '-') {
                    // Expect an indented block on following lines
                    $ctx['pendingSeq'] = true;
                    continue;
                }

                $valuePart = $this->stripTrailingYamlComment(trim(substr($trimmed, 1)));
                if ($valuePart !== '' && ($valuePart[0] === '&' || $valuePart[0] === '*')) {
                    YamlParseException::raise('Anchors and aliases are not supported (line {line})', [
                        'line' => $lineNumber,
                    ]);
                }
                if ($valuePart !== '' && ($valuePart[0] === '|' || $valuePart[0] === '>')) {
                    [$text, $nextLine] = $this->parseBlockScalarValue($valuePart, $lines, $index + 1, $indent, $lineNumber);
                    $ctx['value'][] = $text;
                    $index = $nextLine - 1;
                    continue;
                }
                $ctx['value'][] = $this->parseScalarOrInlineStructure($valuePart, $lineNumber);
                continue;
            }

            // Mapping entry.
            if ($ctx['type'] !== 'map') {
                YamlParseException::raise('Mapping entry not allowed in this context (line {line})', [
                    'line' => $lineNumber,
                ]);
            }

            $pos = strpos($trimmed, ':');
            if ($pos === false) {
                YamlParseException::raise('Invalid mapping entry (missing colon) on line {line}', [
                    'line' => $lineNumber,
                ]);
            }

            $keyRaw = trim(substr($trimmed, 0, $pos));
            if ($keyRaw === '') {
                YamlParseException::raise('Mapping key must not be empty (line {line})', [
                    'line' => $lineNumber,
                ]);
            }

            $key = $this->parseMappingKey($keyRaw, $lineNumber);

            if ($key === '<<') {
                YamlParseException::raise('Merge keys are not supported (line {line})', [
                    'line' => $lineNumber,
                ]);
            }

            if (isset($ctx['value'][$key])) {
                YamlParseException::raise('Duplicate mapping key "{key}" (line {line})', [
                    'key' => $key,
                    'line' => $lineNumber,
                ]);
            }

            $valuePart = $this->stripTrailingYamlComment(trim(substr($trimmed, $pos + 1)));

            if ($valuePart !== '' && ($valuePart[0] === '|' || $valuePart[0] === '>')) {
                [$text, $nextLine] = $this->parseBlockScalarValue($valuePart, $lines, $index + 1, $indent, $lineNumber);
                $ctx['value'][$key] = $text;
                $index = $nextLine - 1;
                continue;
            }

            if ($valuePart !== '' && ($valuePart[0] === '&' || $valuePart[0] === '*')) {
                YamlParseException::raise('Anchors and aliases are not supported (line {line})', [
                    'line' => $lineNumber,
                ]);
            }

            // Unsupported flow mapping (subset constraint).
            if ($valuePart !== '' && $valuePart[0] === '{') {
                YamlParseException::raise('Flow mapping is not supported (line {line})', [
                    'line' => $lineNumber,
                ]);
            }

            if ($valuePart === '') {
                // Value provided by an indented block or treated as null if no block appears.
                $ctx['value'][$key] = null;
                $ctx['pendingKey'] = $key;
                continue;
            }

            $ctx['value'][$key] = $this->parseScalarOrInlineStructure($valuePart, $lineNumber);
        }

        if ($root === null) {
            return [];
        }

        if (!is_array($root)) {
            YamlParseException::raise('Root document must be a mapping or sequence');
        }

        return $root;
    }

    /**
     * Interpolate placeholder variables into a parsed value tree.
     *
     * Guidance: placeholder variables must be string, int, float, or bool; other values are skipped.
     *
     * @param array<int|string, mixed> $data
     *
     * @return array<int|string, mixed>
     */
    public function interpolate(array $data, array $variables = []): array
    {
        $result = $this->interpolateValue($data, $this->normalizePlaceholderVariables($variables));
        return is_array($result) ? $result : $data;
    }

    /**
     * Count the number of leading spaces in one raw line.
     */
    protected function countLeadingSpaces(string $line): int
    {
        $len = strlen($line);
        $count = 0;
        for ($i = 0; $i < $len; $i++) {
            if ($line[$i] === ' ') {
                $count++;
                continue;
            }
            break;
        }
        return $count;
    }

    /**
     * Determine whether a trimmed line starts a non-empty sequence item.
     */
    protected function isSequenceItemLine(string $trimmed): bool
    {
        return str_starts_with($trimmed, '- ');
    }

    /**
     * Strip a trailing YAML inline comment from a mapping/sequence value fragment.
     */
    protected function stripTrailingYamlComment(string $valuePart): string
    {
        $hashPos = $this->findCommentStart($valuePart);
        if ($hashPos !== -1) {
            $valuePart = rtrim(substr($valuePart, 0, $hashPos));
        }

        return trim($valuePart);
    }

    /**
     * Parse one block-scalar header and collect its normalized text payload.
     *
     * @param list<string> $lines
     *
     * @return array{0: string, 1: int}
     */
    protected function parseBlockScalarValue(
        string $valuePart,
        array  $lines,
        int    $startIndex,
        int    $baseIndent,
        int    $lineNumber,
    ): array {
        $header = $this->parseBlockScalarHeader($valuePart, $lineNumber);
        [$rawLines, $nextLine] = $this->readBlockScalarRawLines($lines, $startIndex, $baseIndent, $header['indentAddition']);
        $text = $header['style'] === 'folded'
            ? $this->foldFoldedLines($rawLines)
            : implode("\n", $rawLines);

        return [$this->applyBlockChomp($header['chomp'], $text), $nextLine];
    }

    /**
     * Parse a block-scalar header after <code>|</code> or <code>&gt;</code>.
     *
     * @return array{style: 'literal'|'folded', chomp: 'clip'|'strip'|'keep', indentAddition: int|null}
     */
    protected function parseBlockScalarHeader(string $valuePart, int $lineNumber): array
    {
        $c = $valuePart[0];
        if ($c !== '|' && $c !== '>') {
            YamlParseException::raise('Invalid block scalar (line {line})', ['line' => $lineNumber]);
        }
        $style = $c === '|' ? 'literal' : 'folded';
        $rest = trim(substr($valuePart, 1));
        if ($rest === '') {
            return ['style' => $style, 'chomp' => 'clip', 'indentAddition' => null];
        }

        $chomp = 'clip';
        $indentAddition = null;
        $len = strlen($rest);
        $i = 0;
        if ($rest[$i] === '+' || $rest[$i] === '-') {
            $chomp = $rest[$i] === '+' ? 'keep' : 'strip';
            $i++;
        }

        $digitStart = $i;
        while ($i < $len && ctype_digit($rest[$i])) {
            $i++;
        }
        if ($i > $digitStart) {
            $indentAddition = (int)substr($rest, $digitStart, $i - $digitStart);
            if ($indentAddition < 1) {
                YamlParseException::raise('Block scalar indentation indicator must be at least 1 (line {line})', [
                    'line' => $lineNumber,
                ]);
            }
        }

        if ($i < $len && ($rest[$i] === '+' || $rest[$i] === '-')) {
            if ($chomp !== 'clip') {
                YamlParseException::raise('Invalid block scalar header (line {line})', ['line' => $lineNumber]);
            }
            $chomp = $rest[$i] === '+' ? 'keep' : 'strip';
            $i++;
        }

        while ($i < $len && $rest[$i] === ' ') {
            $i++;
        }
        if ($i < $len) {
            YamlParseException::raise('Invalid block scalar header (line {line})', ['line' => $lineNumber]);
        }

        return ['style' => $style, 'chomp' => $chomp, 'indentAddition' => $indentAddition];
    }

    /**
     * Collect block scalar content lines with block indentation stripped.
     *
     * @param list<string> $lines
     *
     * @return array{0: list<string>, 1: int}
     */
    protected function readBlockScalarRawLines(array $lines, int $startIndex, int $baseIndent, ?int $indentAddition): array
    {
        $contentIndent = $indentAddition !== null ? $baseIndent + $indentAddition : null;
        /** @var list<string> $buffer */
        $buffer = [];
        $n = count($lines);

        for ($i = $startIndex; $i < $n; $i++) {
            $lineNumber = $i + 1;
            $line = rtrim($lines[$i], "\r\n");
            if (str_contains($line, "\t")) {
                YamlParseException::raise('Tabs are not allowed in indentation (line {line})', [
                    'line' => $lineNumber,
                ]);
            }

            $lineIndent = $this->countLeadingSpaces($line);
            $trimmed = trim($line);

            if ($trimmed !== '' && $trimmed[0] === '#' && $lineIndent <= $baseIndent) {
                return [$buffer, $i];
            }

            if ($trimmed === '') {
                $buffer[] = '';
                continue;
            }

            if ($contentIndent === null) {
                if ($lineIndent <= $baseIndent) {
                    return [$buffer, $i];
                }
                $contentIndent = $lineIndent;
                $buffer[] = substr($line, $contentIndent);
                continue;
            }

            if ($lineIndent >= $contentIndent) {
                $buffer[] = substr($line, $contentIndent);
                continue;
            }

            if ($lineIndent <= $baseIndent) {
                return [$buffer, $i];
            }

            YamlParseException::raise('Inconsistent indentation in block scalar (line {line})', [
                'line' => $lineNumber,
            ]);
        }

        return [$buffer, $n];
    }

    /**
     * Fold block lines for <code>&gt;</code> style (YAML-style: blank lines and more-indented lines break).
     *
     * @param list<string> $lines stripped content lines (may start with spaces for “more indented” breaks)
     */
    protected function foldFoldedLines(array $lines): string
    {
        $out = '';
        $buf = [];
        foreach ($lines as $line) {
            if ($line === '') {
                if ($buf !== []) {
                    $out .= implode(' ', $buf);
                    $buf = [];
                }
                $out .= "\n";
                continue;
            }
            if ($line[0] === ' ') {
                if ($buf !== []) {
                    $out .= implode(' ', $buf);
                    $buf = [];
                }
                if ($out !== '') {
                    $out .= "\n";
                }
                $out .= ltrim($line);
                continue;
            }
            $buf[] = rtrim($line);
        }
        if ($buf !== []) {
            $out .= implode(' ', $buf);
        }

        return $out;
    }

    /**
     * Apply YAML block-scalar chomping to the normalized text.
     */
    protected function applyBlockChomp(string $chomp, string $text): string
    {
        if ($chomp === 'strip') {
            return rtrim($text, "\n");
        }
        if ($chomp === 'keep') {
            return $text;
        }
        if ($text === '') {
            return '';
        }
        $hadTrailingNewline = str_ends_with($text, "\n");
        $text = rtrim($text, "\n");
        if ($hadTrailingNewline) {
            return $text . "\n";
        }

        return $text;
    }

    /**
     * Parse one scalar or inline flow-sequence fragment.
     */
    protected function parseScalarOrInlineStructure(string $valuePart, int $line): mixed
    {
        // Strip inline comment
        $hashPos = $this->findCommentStart($valuePart);
        if ($hashPos !== -1) {
            $valuePart = rtrim(substr($valuePart, 0, $hashPos));
        }

        $valuePart = trim($valuePart);
        if ($valuePart === '') {
            return null;
        }

        $first = $valuePart[0];
        $last = $valuePart[strlen($valuePart) - 1];

        // Flow sequence (one level, scalar items only)
        if ($first === '[' && $last === ']') {
            return $this->parseFlowSequence($valuePart, $line);
        }

        if ($first === "'" && $last === "'") {
            return $this->parseSingleQuotedString($valuePart, $line);
        }

        if ($first === '"' && $last === '"') {
            return $this->parseDoubleQuotedString($valuePart, $line);
        }

        return $this->parsePlainScalar($valuePart, $line);
    }

    /**
     * Parse a flow sequence like <code>[a, b, 1, true]</code>.
     *
     * Supported as a strict subset: one level only, scalar items only.
     *
     * @return list<mixed>
     */
    protected function parseFlowSequence(string $valuePart, int $line): array
    {
        $inner = trim(substr($valuePart, 1, -1));
        if ($inner === '') {
            return [];
        }

        $items = [];
        $buf = '';
        $inSingle = false;
        $inDouble = false;
        $len = strlen($inner);

        for ($i = 0; $i < $len; $i++) {
            $ch = $inner[$i];

            if ($ch === "'" && !$inDouble) {
                $inSingle = !$inSingle;
                $buf .= $ch;
                continue;
            }
            if ($ch === '"' && !$inSingle) {
                $inDouble = !$inDouble;
                $buf .= $ch;
                continue;
            }

            if ($ch === ',' && !$inSingle && !$inDouble) {
                $items[] = $this->parseFlowSequenceItem($buf, $line);
                $buf = '';
                continue;
            }

            $buf .= $ch;
        }

        $items[] = $this->parseFlowSequenceItem($buf, $line);

        return $items;
    }

    protected function parseFlowSequenceItem(string $raw, int $line): mixed
    {
        $token = trim($raw);
        if ($token === '') {
            YamlParseException::raise('Flow sequence items must not be empty (line {line})', [
                'line' => $line,
            ]);
        }

        // Disallow nested flow collections in this subset.
        if ($token[0] === '[' || $token[0] === '{') {
            YamlParseException::raise('Nested flow collections are not supported (line {line})', [
                'line' => $line,
            ]);
        }

        // Scalars only (quoted or plain).
        $first = $token[0];
        $last = $token[strlen($token) - 1];
        if ($first === "'" && $last === "'") {
            return $this->parseSingleQuotedString($token, $line);
        }
        if ($first === '"' && $last === '"') {
            return $this->parseDoubleQuotedString($token, $line);
        }

        return $this->parsePlainScalar($token, $line);
    }

    protected function findCommentStart(string $valuePart): int
    {
        $inSingle = false;
        $inDouble = false;
        $len = strlen($valuePart);

        for ($i = 0; $i < $len; $i++) {
            $ch = $valuePart[$i];
            if ($ch === "'" && !$inDouble) {
                $inSingle = !$inSingle;
                continue;
            }
            if ($ch === '"' && !$inSingle) {
                $inDouble = !$inDouble;
                continue;
            }
            if ($ch === '#' && !$inSingle && !$inDouble) {
                return $i;
            }
        }

        return -1;
    }

    /**
     * Parse a mapping key with the same quoted/plain scalar rules as values.
     *
     * Keys must be scalars (string/int/float/bool/null); sequences are rejected.
     */
    protected function parseMappingKey(string $key, int $line): string
    {
        $parsed = $this->parseScalarOrInlineStructure($key, $line);
        if (is_array($parsed)) {
            YamlParseException::raise('Mapping key must be a scalar (line {line})', [
                'line' => $line,
            ]);
        }
        if ($parsed === null) {
            YamlParseException::raise('Mapping key must not be empty (line {line})', [
                'line' => $line,
            ]);
        }
        if (is_bool($parsed)) {
            return $parsed ? 'true' : 'false';
        }

        $asString = (string) $parsed;
        if ($asString === '') {
            YamlParseException::raise('Mapping key must not be empty (line {line})', [
                'line' => $line,
            ]);
        }

        return $asString;
    }

    protected function parseSingleQuotedString(string $value, int $line): string
    {
        if (strlen($value) < 2) {
            YamlParseException::raise('Invalid single-quoted string (line {line})', [
                'line' => $line,
            ]);
        }

        $inner = substr($value, 1, -1);
        // In YAML, '' represents a single quote inside a single-quoted string
        return str_replace("''", "'", $inner);
    }

    protected function parseDoubleQuotedString(string $value, int $line): string
    {
        if (strlen($value) < 2) {
            YamlParseException::raise('Invalid double-quoted string (line {line})', [
                'line' => $line,
            ]);
        }

        $inner = substr($value, 1, -1);
        // Minimal escape handling for common sequences
        return strtr($inner, [
            '\n' => "\n",
            '\r' => "\r",
            '\t' => "\t",
            '\\"' => '"',
            '\\\\' => '\\',
        ]);
    }

    protected function parsePlainScalar(string $value, int $line): mixed
    {
        if ($value === 'null' || $value === '~') {
            return null;
        }

        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }

        // Leading zeros are not decimal ints (YAML 1.2 / PHP numeric-string keys).
        if (ctype_digit($value)) {
            if ($value !== '0' && $value[0] === '0') {
                return $value;
            }

            return (int) $value;
        }
        if ($value[0] === '-' && ctype_digit(substr($value, 1))) {
            $digits = substr($value, 1);
            if ($digits !== '0' && $digits[0] === '0') {
                return $value;
            }

            return (int) $value;
        }

        if (preg_match('/^-?\d+\.\d+$/', $value) === 1) {
            return (float)$value;
        }

        return $value;
    }

    /**
     * @param array<string, string> $variables
     */
    protected function interpolateValue(mixed $value, array $variables): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = $this->interpolateValue($v, $variables);
            }
            return $result;
        }

        if (!is_string($value)) {
            return $value;
        }

        if (!str_contains($value, '${')) {
            return $value;
        }

        return $this->replacePlaceholders($value, $variables);
    }

    /**
     * @param array<string, string|int|float|bool> $variables
     *
     * @return array<string, string>
     */
    protected function normalizePlaceholderVariables(array $variables): array
    {
        $normalized = [];

        foreach ($variables as $name => $value) {
            if (is_string($value)) {
                $normalized[$name] = $value;
                continue;
            }

            if (is_int($value) || is_float($value) || is_bool($value)) {
                $normalized[$name] = Json::stringify($value);
                continue;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $variables
     */
    protected function replacePlaceholders(string $value, array $variables): string
    {
        // Placeholder NAME: allow both env-like (DB_HOST) and parameters-like (db_host),
        // but forbid leading underscore and path-like references (e.g. parameters.db_host).
        // This parser is strict: any "${" occurrence must form a valid placeholder token.
        $tokenPattern = '/\G\$\{([A-Za-z]\w*)(:-([^{}]*))?\}/A';

        $offset = 0;
        $result = '';

        while (true) {
            $pos = strpos($value, '${', $offset);
            if ($pos === false) {
                break;
            }

            $result .= substr($value, $offset, $pos - $offset);

            if (preg_match($tokenPattern, $value, $m, 0, $pos) !== 1) {
                YamlParseException::raise('Invalid placeholder syntax near position {pos}', [
                    'pos' => $pos,
                ]);
            }

            $name = $m[1];
            $default = $m[3] ?? null;

            $result .= $this->resolvePlaceholder($name, $default, $variables);

            $offset = $pos + strlen($m[0]);
        }

        $result .= substr($value, $offset);

        return $result;
    }

    /**
     * @param array<string, string> $variables
     */
    protected function resolvePlaceholder(string $name, ?string $default, array $variables): string
    {
        $exists = array_key_exists($name, $variables);

        if ($exists && $variables[$name] !== '') {
            return $variables[$name];
        }

        if ($default !== null) {
            if (str_contains($default, '${') || str_contains($default, '}')) {
                YamlParseException::raise('Nested placeholders are not allowed in default values ({name})', [
                    'name' => $name,
                ]);
            }

            return $default;
        }

        YamlParseException::raise('Missing placeholder value for {name}', [
            'name' => $name,
        ]);
    }
}
