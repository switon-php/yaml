<?php

declare(strict_types=1);

namespace Switon\Yaml;

use Switon\Core\Attribute\Autowired;
use Switon\Core\FilesystemInterface;

use function array_is_list;
use function ctype_digit;
use function explode;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function str_repeat;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Default Switon YAML subset emitter for config-style arrays.
 *
 * Use when callers need array → YAML without going through JSON.
 * <code>dump()</code> is the core path; <code>write()</code> is a thin filesystem wrapper.
 *
 * Compact rules (default line width 80):
 * - associative arrays always use block mappings
 * - scalar-only lists use flow <code>[...]</code> when the whole line fits
 * - multiline strings use double-quoted escapes when the line fits, otherwise literal
 *   <code>|</code> / <code>|-</code>
 *
 * Road-signs:
 * - dump(): subset-safe emit with compact heuristics
 * - write(): dump + file write
 * - singleQuoted(): forced single-quote escape for stub builders
 *
 * @see YamlWriterInterface
 * @see YamlReaderInterface
 */
class YamlWriter implements YamlWriterInterface
{
    #[Autowired] protected FilesystemInterface $filesystem;

    /**
     * @param array<int|string, mixed> $data
     */
    public function dump(array $data, int $baseIndent = 0, int $lineWidth = 80): string
    {
        return $this->emitMapping($data, $baseIndent, $lineWidth);
    }

    /**
     * @param array<int|string, mixed> $data
     */
    public function write(string $file, array $data, int $baseIndent = 0, int $lineWidth = 80): void
    {
        $yaml = $this->dump($data, $baseIndent, $lineWidth);
        if ($yaml !== '' && !str_ends_with($yaml, "\n")) {
            $yaml .= "\n";
        }
        $this->filesystem->write($file, $yaml);
    }

    public function singleQuoted(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * @param array<string|int, mixed> $map
     */
    protected function emitMapping(array $map, int $level, int $lineWidth): string
    {
        $pad = str_repeat('  ', $level);
        $lines = [];
        foreach ($map as $k => $v) {
            $key = $this->yamlKey((string)$k);
            if (is_array($v)) {
                if ($v === []) {
                    $lines[] = $pad . $key . ': []';
                    continue;
                }
                if (array_is_list($v)) {
                    if ($this->isScalarList($v)) {
                        $flow = $this->emitFlowSequence($v);
                        $line = $pad . $key . ': ' . $flow;
                        if (strlen($line) <= $lineWidth) {
                            $lines[] = $line;
                            continue;
                        }
                    }
                    $lines[] = $pad . $key . ':';
                    $lines[] = $this->emitSequence($v, $level + 1, $lineWidth);
                    continue;
                }
                $lines[] = $pad . $key . ':';
                $lines[] = $this->emitMapping($v, $level + 1, $lineWidth);
                continue;
            }
            if (is_string($v) && $this->needsMultilineScalar($v)) {
                $compact = $pad . $key . ': ' . $this->doubleQuoted($v);
                if (strlen($compact) <= $lineWidth) {
                    $lines[] = $compact;
                    continue;
                }
                $lines[] = $this->emitLiteralScalar($pad, $key, $v);
                continue;
            }
            $lines[] = $pad . $key . ': ' . $this->scalarInline($v);
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<mixed> $seq
     */
    protected function emitSequence(array $seq, int $level, int $lineWidth): string
    {
        $pad = str_repeat('  ', $level);
        $lines = [];
        foreach ($seq as $item) {
            if (is_array($item) && $item !== [] && !array_is_list($item)) {
                $lines[] = $pad . '-';
                $lines[] = $this->emitMapping($item, $level + 1, $lineWidth);
                continue;
            }
            if (is_array($item) && array_is_list($item)) {
                if ($item !== [] && $this->isScalarList($item)) {
                    $flow = $this->emitFlowSequence($item);
                    $line = $pad . '- ' . $flow;
                    if (strlen($line) <= $lineWidth) {
                        $lines[] = $line;
                        continue;
                    }
                }
                if ($item === []) {
                    $lines[] = $pad . '- []';
                    continue;
                }
                $lines[] = $pad . '-';
                $lines[] = $this->emitSequence($item, $level + 1, $lineWidth);
                continue;
            }
            if (is_string($item) && $this->needsMultilineScalar($item)) {
                $compact = $pad . '- ' . $this->doubleQuoted($item);
                if (strlen($compact) <= $lineWidth) {
                    $lines[] = $compact;
                    continue;
                }
                $lines[] = $this->emitLiteralScalarItem($pad, $item);
                continue;
            }
            $lines[] = $pad . '- ' . $this->scalarInline($item);
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<mixed> $seq
     */
    protected function isScalarList(array $seq): bool
    {
        foreach ($seq as $item) {
            if (is_array($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<mixed> $seq
     */
    protected function emitFlowSequence(array $seq): string
    {
        $parts = [];
        foreach ($seq as $item) {
            $parts[] = $this->scalarFlow($item);
        }

        return '[' . implode(', ', $parts) . ']';
    }

    protected function needsMultilineScalar(string $v): bool
    {
        return str_contains($v, "\n");
    }

    protected function emitLiteralScalar(string $pad, string $key, string $value): string
    {
        return $pad . $key . ': ' . $this->formatLiteralScalar($pad, $value);
    }

    protected function emitLiteralScalarItem(string $pad, string $value): string
    {
        return $pad . '- ' . $this->formatLiteralScalar($pad, $value);
    }

    protected function formatLiteralScalar(string $pad, string $value): string
    {
        if (str_contains($value, "\t") || str_contains($value, "\r")) {
            return $this->doubleQuoted($value);
        }

        $header = str_ends_with($value, "\n") ? '|' : '|-';
        $body = str_ends_with($value, "\n") ? substr($value, 0, -1) : $value;
        $contentPad = $pad . '  ';
        $parts = explode("\n", $body);
        $out = [$header];
        foreach ($parts as $part) {
            // Keep middle blank lines as truly empty (no indent spaces).
            $out[] = $part === '' ? '' : $contentPad . $part;
        }

        $text = implode("\n", $out);
        // Clip (`|`) keeps one trailing newline only when the final content line
        // itself ends with a linebreak in the document.
        if ($header === '|') {
            $text .= "\n";
        }

        return $text;
    }

    protected function yamlKey(string $k): string
    {
        if ($k !== '' && ctype_digit($k)) {
            return "'" . $k . "'";
        }
        if (preg_match('/^[a-zA-Z_]\w*$/', $k) === 1) {
            return $k;
        }

        return $this->singleQuoted($k);
    }

    protected function scalarFlow(mixed $v): string
    {
        if (is_string($v) && str_contains($v, "\n")) {
            return $this->doubleQuoted($v);
        }

        return $this->scalarInline($v);
    }

    protected function scalarInline(mixed $v): string
    {
        if ($v === null) {
            return 'null';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_int($v) || is_float($v)) {
            return (string)$v;
        }
        if (!is_string($v)) {
            return "''";
        }
        if (str_contains($v, "\n") || str_contains($v, "\r") || str_contains($v, "\t")) {
            return $this->doubleQuoted($v);
        }
        if (
            $v === ''
            || str_starts_with($v, ' ')
            || str_starts_with($v, '-')
            || str_contains($v, ': ')
            || str_contains($v, '#')
            || preg_match('/[:@`[\]{},&*!|>%"\'\\\\]/', $v) === 1
            // Keep digit-like / bool-like strings as strings on round-trip (mcp.yml env/headers).
            || preg_match('/^(?:null|true|false|yes|no|on|off|~|[-+]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][-+]?\d+)?)$/i', $v) === 1
        ) {
            return $this->singleQuoted($v);
        }

        return $v;
    }

    protected function doubleQuoted(string $v): string
    {
        $escaped = str_replace(
            ['\\', '"', "\n", "\r", "\t"],
            ['\\\\', '\\"', '\\n', '\\r', '\\t'],
            $v,
        );

        return '"' . $escaped . '"';
    }
}
