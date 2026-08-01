# Switon YAML Package

[![YAML CI](https://img.shields.io/github/actions/workflow/status/switon-php/yaml/ci.yml?branch=main&label=YAML%20CI)](https://github.com/switon-php/yaml/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's strict YAML subset reader/writer and placeholder resolver for predictable config documents.

## Highlights

- **Strict YAML subset:** only the supported Switon YAML features are parsed and emitted.
- **Single-step read:** YAML can be parsed and interpolated at once.
- **Array → YAML dump:** emit config-friendly block YAML without going through JSON.
- **Predictable failures:** parse errors are reported clearly.
- **Config-friendly arrays:** parse results are plain PHP arrays for config loading.

## Installation

```bash
composer require switon/yaml
```

## Quick Start

```php
use Switon\Core\Attribute\Autowired;
use Switon\Yaml\YamlReaderInterface;
use Switon\Yaml\YamlWriterInterface;

class ConfigService
{
    #[Autowired] protected YamlReaderInterface $yamlReader;
    #[Autowired] protected YamlWriterInterface $yamlWriter;

    public function load(string $yaml): array
    {
        return $this->yamlReader->read($yaml, ['APP_ENV' => 'prod']);
    }

    /** @param array<int|string, mixed> $data */
    public function save(string $file, array $data): void
    {
        $this->yamlWriter->write($file, $data);
    }
}
```

Docs: https://github.com/switon-php/yaml/blob/main/docs/README.md

## License

MIT.
