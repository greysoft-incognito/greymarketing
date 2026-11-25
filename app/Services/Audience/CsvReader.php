<?php

namespace App\Services\Audience;

use Generator;
use SplFileObject;

/**
 * Streams a CSV file as header-keyed rows without loading it into memory.
 */
class CsvReader
{
    protected SplFileObject $file;

    /**
     * @var array<int, string>
     */
    protected array $headers;

    public function __construct(string $path)
    {
        $this->file = new SplFileObject($path);
        $this->file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);

        $headers = $this->file->current() ?: [];
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($headers[0] ?? ''));

        $this->headers = array_map(fn ($header) => trim((string) $header), $headers);
    }

    /**
     * @return array<int, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Yield chunks of rows keyed by header, with their 1-based line numbers as keys.
     *
     * @return Generator<int, array<int, array<string, string>>>
     */
    public function chunks(int $size = 500): Generator
    {
        $chunk = [];

        foreach ($this->rows() as $line => $row) {
            $chunk[$line] = $row;

            if (count($chunk) === $size) {
                yield $chunk;
                $chunk = [];
            }
        }

        if ($chunk) {
            yield $chunk;
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function sample(int $count = 5): array
    {
        $rows = [];

        foreach ($this->rows() as $row) {
            $rows[] = $row;

            if (count($rows) === $count) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    protected function rows(): Generator
    {
        $width = count($this->headers);

        $this->file->rewind();
        $this->file->next();

        while ($this->file->valid()) {
            $values = $this->file->current();
            $line = $this->file->key() + 1;
            $this->file->next();

            if (! is_array($values) || $values === [null]) {
                continue;
            }

            $values = array_pad(array_slice($values, 0, $width), $width, '');

            yield $line => array_combine($this->headers, array_map(fn ($v) => trim((string) $v), $values));
        }
    }
}
