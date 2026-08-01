# Design Rationale {#design-rationale}

`switon/yaml` keeps YAML narrow on purpose: one reviewable subset, explicit interpolation, and clear parse failures.

## See also {#see-also}

- [YAML Package](README.md) — usage, subset shape, and placeholder flow
- [FAQ](faq.md#yaml-faq) — short answers for fit and unsupported syntax

---

## Core Problem {#core-problem}

Config-like YAML becomes hard to trust when the accepted shape is too broad.

- anchors and aliases make documents harder to reason about
- tags and merge keys add behavior that is easy to miss in review
- placeholder handling becomes unclear when parsing and substitution blur together

The package keeps the common shape small and predictable.

## Why A Strict Subset {#strict-subset}

`switon/yaml` is not trying to be a full YAML engine.

It standardizes the part of YAML Switon uses most often:

- mappings
- sequences
- common scalars
- block scalars
- simple placeholder resolution

The trade-off is less YAML compatibility. The gain is easier review and easier replacement.

## Why Parsing And Interpolation Stay Separate {#parsing-interpolation-separate}

Parsing should answer one question: what value tree does this document describe?

Interpolation answers a different one: which parsed strings need runtime values filled in?

Keeping those steps separate makes both phases easier to test and easier to explain.

## Why Dump Lives Beside Read {#why-dump}

Config workflows need array → YAML as a first-class path. Emitting through JSON is awkward and does not guarantee subset shape.

`YamlWriter` dumps subset YAML meant to round-trip through `YamlReader`. Compact output uses a default line width of 80: scalar-only lists may stay on one flow line, associative maps always break to block, and multiline strings prefer double-quoted one-liners when they fit. `write()` is only a thin filesystem wrapper around `dump()`. There is no reverse-interpolation.

## Why Failures Stay Specific {#failures-specific}

`YamlParseException` marks subset violations and invalid placeholder syntax.

That keeps YAML failures easy to recognize without introducing a second error style for this package.

## Deliberate Boundary {#deliberate-boundary}

The package does not own arbitrary YAML compatibility.

- no anchors or aliases
- no tags
- no merge keys
- no flow mappings

That boundary makes the subset easier to standardize across Switon components and app config paths.
