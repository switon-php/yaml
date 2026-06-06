<?php

declare(strict_types=1);

namespace Switon\Yaml\Exception;

use Switon\Yaml\Exception as BaseException;

/**
 * Indicates that a Switon YAML subset document is syntactically invalid or
 * violates subset constraints.
 *
 * This exception is reserved for YAML parsing and placeholder interpolation
 * errors as defined in the Switon YAML Subset specification.
 *
 * Use when callers need one component-level failure type for malformed YAML
 * subset input or unsupported subset features.
 */
class YamlParseException extends BaseException
{
}
