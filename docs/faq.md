# YAML FAQ {#yaml-faq}

Short answers for the most common `yaml` questions.

> **Prerequisites:** [YAML Package](README.md)

## Getting Started {#getting-started}

### Should I use `switon/yaml` or a full YAML library? {#full-yaml-library}

Use `switon/yaml` when you want the Switon YAML subset on purpose. If you need arbitrary YAML features, this package is the wrong fit.

### Do I have to use DI to use this package? {#need-di}

No. Inject `YamlReaderInterface` / `YamlWriterInterface` in framework code, or construct `YamlReader` / `YamlWriter` directly at bootstrap edges. `YamlWriter::write()` still needs a wired `FilesystemInterface`.

### Where is the exact subset defined? {#subset-source}

The normative subset lives in `packages/yaml/subset.md`.

### How do I emit YAML from a PHP array? {#emit-yaml}

Use `YamlWriterInterface::dump()` for a string, or `write()` to persist via the filesystem. Prefer this over stringifying JSON and converting.

## Parsing And Interpolation {#parsing-and-interpolation}

### Why are `parse()` and `interpolate()` separate? {#why-two-steps}

Because syntax parsing and placeholder resolution are different boundaries.

### Which placeholder value types are supported? {#placeholder-types}

Use `string`, `int`, `float`, or `bool`.
Placeholder names must start with a letter and contain only letters, digits, and underscores.

### Are placeholders resolved inside quoted strings? {#placeholder-strings}

Yes. Interpolation runs over parsed string values, regardless of the original quote style.
An empty string also counts as missing when a default is provided.

## Unsupported Features {#unsupported-features}

### Why are anchors, aliases, and flow mappings unsupported? {#unsupported-yaml-features}

Because the package is standardizing a narrow and reviewable YAML shape first.

### Is this package only for `switon.yml`? {#only-switon-yml}

No. It is reusable anywhere the Switon YAML subset is a good fit.

## Errors {#security-and-errors}

### What exception should I expect on invalid YAML? {#parse-error}

`YamlParseException`.

### What happens with duplicate mapping keys or blank documents? {#duplicates-and-empty}

Duplicate keys are rejected, and an empty or comment-only document parses to `[]`.
