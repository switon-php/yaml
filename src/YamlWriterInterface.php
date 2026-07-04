<?php

declare(strict_types=1);

namespace Switon\Yaml;

/**
 * Emit PHP arrays as Switon YAML subset documents.
 *
 * Output is meant to round-trip through {@see YamlReaderInterface}.
 * No placeholder reverse-interpolation.
 *
 * Compact heuristics (default line width 80): scalar-only lists may use flow
 * <code>[...]</code>; associative arrays stay block; multiline strings prefer
 * double-quoted one-liners when they fit, otherwise literal <code>|</code>.
 *
 * Road-signs:
 * - dump(): array → YAML string
 * - write(): dump + {@see \Switon\Core\FilesystemInterface::write()}
 * - singleQuoted(): always-quote a scalar (stub builders / forced-quote keys)
 *
 * @see YamlWriter
 * @see YamlReaderInterface
 */
interface YamlWriterInterface
{
    /**
     * Dump a PHP array as Switon YAML subset text.
     *
     * @param array<int|string, mixed> $data
     * @param int $lineWidth Preferred max characters per emitted line (compact vs block)
     */
    public function dump(array $data, int $baseIndent = 0, int $lineWidth = 80): string;

    /**
     * Dump <code>$data</code> and write it to <code>$file</code> via the filesystem.
     *
     * Ensures the written file ends with a trailing newline when non-empty.
     *
     * @param array<int|string, mixed> $data
     * @param int $lineWidth Preferred max characters per emitted line (compact vs block)
     */
    public function write(string $file, array $data, int $baseIndent = 0, int $lineWidth = 80): void;

    /**
     * Always emit a YAML single-quoted scalar (<code>'</code> escaped as <code>''</code>).
     */
    public function singleQuoted(string $value): string;
}
