<?php

declare(strict_types=1);

namespace Switon\Yaml;

/**
 * Split YAML frontmatter from Markdown and parse metadata via {@see YamlReaderInterface}.
 */
interface FrontmatterReaderInterface
{
    /**
     * Read a file and parse frontmatter.
     *
     * @throws \Switon\Core\Exception\FileNotFoundException
     */
    public function read(string $file): ?FrontmatterDocument;

    /**
     * Parse frontmatter from a Markdown string (no path-derived id).
     *
     * @return FrontmatterDocument|null null when the text has no valid frontmatter fence
     */
    public function parse(string $markdown): ?FrontmatterDocument;
}
