<?php

declare(strict_types=1);

namespace Switon\Yaml;

/**
 * Parsed Markdown document with YAML frontmatter and body text.
 *
 * @see FrontmatterReaderInterface
 */
class FrontmatterDocument
{
    /**
     * @param array<string, mixed> $meta Parsed YAML frontmatter
     */
    public function __construct(
        public array $meta,
        public string $body,
    ) {
    }
}
