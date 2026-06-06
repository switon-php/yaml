# Switon YAML Package

[![YAML CI](https://img.shields.io/github/actions/workflow/status/switon-php/yaml/ci.yml?branch=main&label=YAML%20CI)](https://github.com/switon-php/yaml/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's strict YAML subset parser and placeholder resolver for predictable config documents.

## Highlights

- **Strict YAML subset:** only the supported Switon YAML features are parsed.
- **Single-step read:** YAML can be parsed and interpolated at once.
- **Predictable failures:** parse errors are reported clearly.
- **Config-friendly output:** the result is a plain PHP array for config loading.

## Installation

```bash
composer require switon/yaml
```

## Quick Start

```php
use Switon\Core\Attribute\Autowired;
use Switon\Yaml\YamlReaderInterface;

class ConfigService
{
    #[Autowired] protected YamlReaderInterface $yamlReader;

    public function load(string $yaml): array
    {
        return $this->yamlReader->read($yaml, ['APP_ENV' => 'prod']);
    }
}
```

Docs: https://docs.switon.dev/latest/yaml

## License

MIT.
