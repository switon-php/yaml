<?php

declare(strict_types=1);

namespace Switon\Yaml;

use Switon\Core\Attribute\Autowired;
use Switon\Core\FilesystemInterface;

use function strlen;
use function str_starts_with;
use function strpos;
use function substr;
use function trim;

/**
 * Default frontmatter splitter backed by {@see YamlReaderInterface}.
 *
 * @see FrontmatterDocument
 */
class FrontmatterReader implements FrontmatterReaderInterface
{
    #[Autowired] protected YamlReaderInterface $yamlReader;
    #[Autowired] protected FilesystemInterface $filesystem;

    public function read(string $file): ?FrontmatterDocument
    {
        return $this->parse($this->filesystem->read($file));
    }

    public function parse(string $markdown): ?FrontmatterDocument
    {
        $split = $this->split($markdown);
        if ($split === null) {
            return null;
        }

        /** @var array<string, mixed> $meta */
        $meta = $this->yamlReader->parse($split['yaml']);

        return new FrontmatterDocument($meta, $split['body']);
    }

    /**
     * @return array{yaml: string, body: string}|null
     */
    protected function split(string $raw): ?array
    {
        if (!str_starts_with($raw, "---\n") && !str_starts_with($raw, "---\r\n")) {
            return null;
        }

        $offset = str_starts_with($raw, "---\r\n") ? 5 : 4;
        $end = strpos($raw, "\n---", $offset);
        if ($end === false) {
            return null;
        }

        $yaml = substr($raw, $offset, $end - $offset);
        $bodyStart = $end + strlen("\n---");
        if (isset($raw[$bodyStart]) && ($raw[$bodyStart] === "\n" || $raw[$bodyStart] === "\r")) {
            if ($raw[$bodyStart] === "\r" && ($raw[$bodyStart + 1] ?? '') === "\n") {
                $bodyStart += 2;
            } else {
                $bodyStart += 1;
            }
        }

        return [
            'yaml' => $yaml,
            'body' => trim(substr($raw, $bodyStart)),
        ];
    }
}
