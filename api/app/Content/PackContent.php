<?php

declare(strict_types=1);

namespace App\Content;

/** The content of a pack: its documents in memory, with the number the runs are frozen with while it is the one in use. */
final class PackContent extends ContentSource
{
    /** @param array<string, string> $documents the text of every document by key, the manifest as `pack` */
    public function __construct(private readonly string $number, private readonly array $documents) {}

    public function number(): string
    {
        return $this->number;
    }

    /**
     * The same content with some documents put over it (a table that was made again, say).
     *
     * @param  array<string, string>  $documents  the text of the documents by key
     */
    public function with(array $documents): self
    {
        return new self($this->number, [...$this->documents, ...$documents]);
    }

    public function rulesetVersion(): string
    {
        return $this->number;
    }

    /** A mark of what the documents say: the same documents give the same mark, whatever order they came in, and any change to one of them changes it. */
    public function fingerprint(): string
    {
        $lines = [];

        foreach ($this->documents as $key => $text) {
            $lines[] = $key.':'.sha1($text);
        }

        sort($lines);

        return hash('sha256', implode("\n", $lines));
    }

    public function slugs(string $folder): array
    {
        $slugs = [];

        foreach (array_keys($this->documents) as $key) {
            if (str_starts_with($key, "{$folder}/")) {
                $slugs[] = substr($key, strlen($folder) + 1);
            }
        }

        sort($slugs);

        return $slugs;
    }

    public function raw(string $key): ?string
    {
        return $this->documents[$key] ?? null;
    }
}
