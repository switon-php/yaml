<?php

declare(strict_types=1);

namespace Switon\Yaml;

/**
 * Read-only contract for the Switon YAML subset.
 *
 * Behaviour is defined by <code>subset.md</code>.
 *
 * Parse only applies YAML syntax rules. Placeholder interpolation is an
 * explicit second step over parsed string values.
 *
 * Road-signs:
 * - load(): path → filesystem read + parse + interpolate
 * - read(): parse + interpolate convenience entry
 * - parse(): YAML-subset syntax only
 * - interpolate(): placeholder resolution over parsed values
 */
interface YamlReaderInterface
{
    /**
     * Read a YAML file path via {@see \Switon\Core\FilesystemInterface}, then `read()`.
     *
     * @param array<string, string|int|float|bool> $variables
     *
     * @return array<int|string, mixed>
     */
    public function load(string $file, array $variables = []): array;

    /**
     * Parse one Switon YAML subset document and interpolate placeholders in one call.
     *
     * This is the convenience entrypoint for callers that want a fully resolved PHP array.
     *
     * @param array<string, string|int|float|bool> $variables
     *
     * @return array<int|string, mixed>
     */
    public function read(string $yaml, array $variables = []): array;

    /**
     * Parse a Switon YAML subset document into a PHP array.
     *
     * Supports block mappings and sequences plus the inline flow-sequence
     * subset defined by the Switon YAML subset spec. Literal (<code>|</code>) and
     * folded (<code>&gt;</code>) block scalars with optional chomping and indentation
     *
     * @return array<int|string, mixed>
     * indicators are supported; see <code>subset.md</code>.
     */
    public function parse(string $yaml): array;

    /**
     * Interpolate placeholders in a parsed value tree.
     *
     * This step operates on parsed strings and does not preserve whether a
     * value originally came from plain, single-quoted, or double-quoted YAML.
     *
     * @param array<int|string, mixed> $data
     * @param array<string, string|int|float|bool> $variables
     *
     * @return array<int|string, mixed>
     */
    public function interpolate(array $data, array $variables = []): array;
}
