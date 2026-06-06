# Switon YAML Subset

This document defines the **Switon YAML Subset**: a restricted, well‑defined dialect of YAML intended for configuration
and other data files.

The goal is to provide:

- A **predictable**, implementation‑agnostic format that can be parsed by Switon or third‑party libraries.
- A **strict subset** of common YAML syntax, avoiding ambiguous or rarely used features.
- A small, well‑specified surface that is easy to validate, test, and replace.

Any parser claiming compatibility with this subset MUST follow the rules in this document.

---

## 1. General rules

- The document is a **single YAML document**.
- Multi‑document markers (`---`, `...`) are **not allowed**.
- The character encoding is **UTF‑8**.
- **Tabs are not allowed** for indentation (spaces only).
- Lines MAY be empty. Empty lines are ignored.

If any of the above rules is violated, the document is considered **invalid Switon YAML**.

---

## 2. Node types

The Switon YAML Subset supports three kinds of nodes:

- **Mapping** (object / dictionary)
- **Sequence** (array / list)
- **Scalar** (primitive value)

Nodes are combined using indentation. Indentation width is not fixed, but MUST be consistent within a given nesting
level.

Any other node kind (for example tagged values or custom types) is not part of this subset.

For configuration use-cases, a Switon YAML document MUST have a mapping or sequence as the root node. Scalar-only
documents are out of scope for this subset.

---

## 3. Mappings

A **mapping** is a set of key–value pairs.

### 3.1. Mapping entries

- A mapping entry is written as:

  ```text
  key: value
  ```

- A key is a **non‑empty string** without leading or trailing whitespace.
- The colon (`:`) MUST be followed by either:
    - a space and then the value (inline value), or
    - nothing on the same line, in which case the value is provided by an indented block on the following lines.

Example:

```yaml
name: Switon
enabled: true

database:
  host: localhost
  port: 5432
```

### 3.2. Mapping ordering and duplicates

- The relative order of keys in a mapping is preserved.
- If the same key appears more than once in the same mapping, behaviour is **undefined** and such documents SHOULD be
  rejected.

---

## 4. Sequences

A **sequence** is an ordered list of items.

### 4.1. Sequence items

- A sequence item is written as:

  ```text
  - value
  ```

- The dash (`-`) MUST be followed by either:
    - a space and then the value (inline item), or
    - nothing on the same line, in which case the item is provided by an indented block on the following lines.

Example:

```yaml
items:
  - first
  - second
  - third

databases:
  - host: db1
    port: 5432
  - host: db2
    port: 5433
```

### 4.2. Mixed mappings and sequences

- A mapping value MAY be a mapping, a sequence, or a scalar.
- A sequence item MAY be a mapping, a sequence, or a scalar.

### 4.3. Flow sequence subset

- The subset also allows a limited inline sequence form:

  ```yaml
  tags: [core, yaml]
  ```

- A flow sequence MUST use square brackets (`[ ... ]`).
- It MUST be one level only: nested flow collections are not allowed.
- Its items MUST be scalars only.
- Mapping values in flow form (`{ key: value }`) are not part of this subset.

---

## 5. Scalars

A **scalar** is a primitive value. The subset supports:

- `null`
- booleans
- integers
- floats
- strings (plain, single‑quoted, double‑quoted)

### 5.1. Null

The following literals are interpreted as `null`:

- `null`
- `~`

They are case‑sensitive.

### 5.2. Booleans

The following literals are interpreted as booleans:

- `true` → boolean `true`
- `false` → boolean `false`

They are case‑sensitive.

### 5.3. Integers

- A sequence of digits (optionally prefixed by `-`) is interpreted as a decimal integer.

### 5.4. Floats

- A numeric value containing a decimal point is interpreted as a float.
- Only simple decimal notation is supported (no scientific notation).

### 5.5. Strings

#### 5.5.1. Plain strings

- Any unquoted scalar that does not match the rules for null, boolean, or
  number is treated as a plain string.
- Leading and trailing spaces are trimmed.
- A `#` starts a comment when it is preceded by at least one whitespace
  character, or when it is the first non‑space character on the line.

#### 5.5.2. Single‑quoted strings

- Enclosed in single quotes: `'text'`.
- During YAML parsing, contents are treated **literally**: escape sequences are
  not processed and placeholder syntax is preserved as plain text.
- To represent a single quote inside a single‑quoted string it MUST be
  doubled: `''`.

#### 5.5.3. Double‑quoted strings

- Enclosed in double quotes: `"text"`.
- Basic escape sequences MAY be supported (for example `\"`, `\\`, `\n`,
  `\t`), but are not required by this subset.
- Placeholders (see section 6) inside double‑quoted strings are **subject to
  interpolation**.

---

## 6. Placeholders

The subset reserves a simple placeholder syntax inside **string scalar values**:

- `${NAME}`
- `${NAME:-default}`

This section defines the syntax and constraints of placeholders plus the
typical two-step model used by Switon:

1. parse YAML into PHP values;
2. resolve placeholders in the parsed string values.

### 6.1. Placeholder syntax

- A placeholder starts with `${` and ends with `}`.
- The name MUST match:

  ```text
  [A-Za-z][A-Za-z0-9_]*   (one letter, followed by letters, digits, or underscores)
  ```

- A placeholder MAY include a default value using `:-`:

  ```text
  ${NAME:-default}
  ```

- The `default` part is a **literal string**:
    - it MUST NOT contain `${` or `}`;
    - it is not parsed as YAML or as another placeholder.
    - it MAY be empty, for example `${NAME:-}`.

### 6.2. Placeholder locations

- Placeholders MAY appear in plain strings, single‑quoted strings, and
  double‑quoted strings.
- Quote style affects YAML parsing only. A later interpolation step MAY still
  resolve placeholder tokens that were parsed from single-quoted strings.

Example:

```yaml
a: ${NAME}
b: "Value is ${NAME:-default}"
c: '${NAME}'   # literal "${NAME}" during parse
```

In the example above, `c` remains the literal string `${NAME}` after the
parse step. A later interpolation step may still resolve it because the parsed
value is a string containing a placeholder token.

### 6.3. Resolution semantics (informative)

A typical consumer that resolves placeholders SHOULD:

- look up `NAME` in a variable map (`NAME` → value);
- treat `${NAME}` with missing `NAME` as an **error**;
- treat `${NAME:-default}` as `default` when `NAME` is missing or mapped to an empty string;
- treat `${NAME:-}` as the empty string when `NAME` is missing or mapped to an
  empty string;
- only scan string values for placeholders (non‑string values are not
  affected).

For the built-in `YamlReader` interpolation step, placeholder variables are
typically provided as strings, integers, floats, or booleans and converted to
replacement strings before substitution.

---

## 7. Comments

- A comment starts with `#` and continues until the end of the line.
- A full line may be a comment:

  ```yaml
  # This is a comment
  key: value
  ```

- A comment may follow a value on the same line, as long as there is at least
  one whitespace character before `#`:

  ```yaml
  key: value  # comment here
  ```

- A `#` inside a single‑ or double‑quoted string does **not** start a comment.

---

## 8. Block scalars (literal and folded)

The subset supports **literal** (`|`) and **folded** (`>`) block scalars so that typical editor formatting and
OpenAPI‑style YAML remain readable by `YamlReader`.

### 8.1. Indicators and header

- **Literal** preserves line breaks between content lines (after indentation is stripped).
- **Folded** joins consecutive non‑empty lines with a single space; an **empty content line** inserts a newline in the
  result; a content line that **starts with a space** after block indentation is stripped forces a line break before
  that line (YAML “more‑indented” break), then leading spaces on that line are trimmed for the folded segment.

Optional **block header** after `|` or `>` (YAML 1.2 order, both variants):

- **Chomping:** `+` (keep trailing line breaks as produced), `-` (strip all trailing newlines), or omitted (**clip**:
  trim trailing empty lines to at most one final newline when the scalar ended with a line break).
- **Indentation indicator:** a positive decimal integer `N` meaning content lines are indented **`N` spaces more than
  the key line** (or the `-` line for a sequence item). If omitted, the **first non‑empty content line** defines the
  content indentation (must be strictly greater than the key line indent).

Examples: `|-`, `|+`, `|2`, `|2-`, `|-2`, `>+`, `>-`, `>2`.

The header MUST contain only optional chomping, optional digits, optional second chomping (the other order), and spaces;
anything else (e.g. `|bad`) is invalid.

### 8.2. Content lines

- Every non‑empty content line MUST be indented **at least** to the effective **content indentation** (see 8.1).
- **Empty** content lines (blank in the file) are allowed and are preserved in the literal case; in the folded case they
  start a newline as in 8.1.
- A full‑line comment whose indentation is **less than or equal to** the key line’s indentation **ends** the block; that
  line is **not** part of the scalar.
- Sequence items may use `- |` or `- >` with the same rules (`baseIndent` is the indent of that `-` line).

### 8.3. Example (literal)

```yaml
description: |
  First line of text.
  Second line.

  A blank line is preserved in the string.
```

### 8.4. Example (folded)

```yaml
summary: >
  One long sentence split
  across two lines.
```

---

## 9. Unsupported YAML features

The following YAML features are **not** part of the Switon YAML Subset and MUST cause the document to be treated as
invalid under this subset:

- anchors and aliases:
    - `&anchor`, `*anchor`;
- merge keys:
    - `<<: *anchor`;
- multi‑document markers:
    - `---`, `...`;
- tags:
    - `!tag`, `!!type`;
- flow mapping:
    - `{ key: value }`;
- nested flow collections:
    - `[a, [b]]`, `[a, { b: c }]`;
- complex keys (non‑scalar mapping keys);
- tab characters used for indentation.

---

## 10. Compatibility notes

- The Switon YAML Subset is intentionally smaller than full YAML 1.x.
- Many documents valid under this subset will also be valid under common YAML libraries (for example Symfony YAML,
  js‑yaml), but not all YAML documents accepted by those libraries are valid under this subset.
- Implementations MAY internally use any YAML library or custom parser, as long as the observable behaviour matches the
  rules defined in this document.

A practical rule of thumb:

> Any document valid under the Switon YAML Subset should be accepted by a
> general YAML parser, but not every YAML document accepted by a general
> parser is valid under this subset.

---

## 11. Examples (informative)

This section is informative only. It illustrates typical usage patterns that conform to the Switon YAML Subset.

### 11.1. Basic configuration file

```yaml
app:
  name: Switon
  debug: false
  timezone: Asia/Shanghai

database:
  host: localhost
  port: 5432
  options:
    pool_size: 10
    timeout: 1.5

features:
  - users
  - billing
  - reporting

tags: [core, yaml]
```

### 11.2. Comments and blank lines

```yaml
# Application configuration

app:
  name: Switon        # Display name
  debug: true         # Enable debug mode

# Database connection

database:
  host: db.example.com
  port: 5432
```

### 11.3. Placeholders with external variables

```yaml
database:
  host: ${DB_HOST:-localhost}
  port: ${DB_PORT:-5432}
  user: ${DB_USER}
  pass: ${DB_PASS:-}
```

In a typical consumer, the file above would be:

- parsed once as Switon YAML;
- then passed together with a variable map (for example based on environment variables or a `parameters` section) to a
  placeholder interpolation step that resolves the `${...}` expressions according to section 6.
