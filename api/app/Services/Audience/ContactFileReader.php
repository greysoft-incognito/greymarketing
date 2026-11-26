<?php

namespace App\Services\Audience;

use Generator;

/**
 * Streams an uploaded contacts file as rows keyed by column name.
 */
abstract class ContactFileReader
{
    /**
     * @return array<int, string>
     */
    abstract public function headers(): array;

    /**
     * Yield each row keyed by its position in the file (line or card number).
     *
     * @return Generator<int, array<string, string>>
     */
    abstract protected function rows(): Generator;

    /**
     * Pick the reader that understands the file at this path.
     */
    public static function for(string $path): self
    {
        return VcardReader::looksLike($path) ? new VcardReader($path) : new CsvReader($path);
    }

    /**
     * Yield chunks of rows, keeping each row's position as its key.
     *
     * @return Generator<int, array<int, array<string, string>>>
     */
    public function chunks(int $size = 500): Generator
    {
        $chunk = [];

        foreach ($this->rows() as $position => $row) {
            $chunk[$position] = $row;

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
}
