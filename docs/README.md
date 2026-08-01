# YAML Package {#yaml-package}

`switon/yaml` parses and emits the Switon YAML subset, and can resolve `${NAME}` placeholders in a second pass.

**Key features:**

- **Strict subset** — predictable mappings, sequences, scalars, and block scalars
- **Two-step model** — `parse()` reads YAML syntax, then `interpolate()` resolves placeholders
- **Convenient `read()` path** — parse and interpolate in one call when you already have variables
- **Array → YAML emit** — `YamlWriterInterface::dump()` / `write()` for config-style documents
- **Clear parse failures** — `YamlParseException` marks invalid subset input

---

## Installation {#installation}

**Requirements:** PHP 8.3+, `ext-json`, plus `switon/core`.

```bash
composer require switon/yaml
```

---

## Quick Start {#quick-start}

Inject `YamlReaderInterface` when you are already inside framework wiring.

```php
use Switon\Core\Attribute\Autowired;
use Switon\Yaml\YamlReaderInterface;

final class ConfigLoader
{
    #[Autowired] protected YamlReaderInterface $yamlReader;

    public function load(string $yaml, array $vars): array
    {
        return $this->yamlReader->read($yaml, $vars);
    }
}
```

At bootstrap edges, `new Switon\Yaml\YamlReader()` also works.

### Write Config YAML {#write-config-yaml}

Inject `YamlWriterInterface` when you need array → YAML without going through JSON.

```php
use Switon\Core\Attribute\Autowired;
use Switon\Yaml\YamlWriterInterface;

final class ConfigSaver
{
    #[Autowired] protected YamlWriterInterface $yamlWriter;

    /** @param array<int|string, mixed> $data */
    public function save(string $file, array $data): void
    {
        $this->yamlWriter->write($file, $data);
    }

    /** @param array<int|string, mixed> $data */
    public function toString(array $data): string
    {
        return $this->yamlWriter->dump($data);
    }
}
```

`dump()` returns block-style subset YAML. `write()` dumps and persists via `FilesystemInterface`, adding a trailing newline when the body is non-empty.

---

## Core Concepts {#core-concepts}

### Read, Parse, Interpolate {#read-parse-interpolate}

- `parse()` turns YAML into a PHP array
- `interpolate()` walks parsed string values and resolves placeholders
- `read()` combines both steps

### Dump And Write {#dump-and-write}

- `dump()` turns a PHP array into Switon YAML subset text
- `write()` dumps and writes through the filesystem
- associative arrays always use block mappings
- scalar-only lists use flow `[...]` when the whole line fits the width (default 80)
- multiline strings use double-quoted escapes when they fit, otherwise literal `|` / `|-`
- emitted documents are meant to round-trip through `YamlReaderInterface`
- there is no reverse-interpolation step

### Subset Shape {#subset-shape}

The subset supports:

- mappings
- sequences
- plain, single-quoted, and double-quoted scalars
- integers, floats, booleans, and `null`
- literal and folded block scalars
- one-level flow sequences with scalar items

The subset does not support anchors, aliases, tags, merge keys, or flow mappings.

The normative grammar is documented in `packages/yaml/subset.md`.

### Placeholders {#placeholders}

Placeholders use `${NAME}` or `${NAME:-default}`.

- placeholder names must start with a letter and contain only letters, digits, and underscores
- placeholder values must be `string`, `int`, `float`, or `bool`
- missing values are an error unless a default is present; an empty string also falls back to the default
- interpolation runs over parsed strings, even if the original YAML used quotes

### Errors {#security-and-errors}

Use `YamlParseException` for invalid subset input or invalid placeholder syntax.

---

## Keep in Mind {#keep-in-mind}

- This package is for the Switon YAML subset, not full YAML.
- Parsing and interpolation are separate on purpose.
- Prefer `YamlWriter` over JSON when emitting config YAML.
- Scalar-only documents are out of scope.
- Tabs, multi-document markers, anchors, aliases, tags, and merge keys are rejected.
- Duplicate mapping keys are rejected.
- Plain `null`, `~`, `true`, and `false` are recognized only in lowercase.
- Plain integers and decimal floats are converted; hex and scientific notation stay strings.
- Empty or comment-only input parses to `[]`.

---

## Next {#next}

- **Need setup questions answered quickly:** [FAQ](faq.md#yaml-faq)
- **Need design trade-offs:** [Design Rationale](design.md#design-rationale)
- **Need OpenAPI YAML workflows:** [OpenAPI Package](../../openapi/docs/README.md)
