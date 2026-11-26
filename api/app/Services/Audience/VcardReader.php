<?php

namespace App\Services\Audience;

use Generator;
use SplFileObject;

/**
 * Streams a vCard (.vcf) file as rows, one per card, using the same column names a CSV would have.
 */
class VcardReader extends ContactFileReader
{
    /**
     * Columns a card can fill, in the order they are shown.
     */
    protected const COLUMNS = ['First name', 'Last name', 'Email', 'Phone', 'Organization', 'City', 'Birthday', 'Note'];

    /**
     * @var array<int, string>|null
     */
    protected ?array $headers = null;

    public function __construct(protected string $path) {}

    /**
     * Check whether the file starts like a vCard, ignoring a BOM and blank lines.
     */
    public static function looksLike(string $path): bool
    {
        $start = (string) @file_get_contents($path, false, null, 0, 512);
        $start = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $start));

        return stripos($start, 'BEGIN:VCARD') === 0;
    }

    /**
     * Only the columns that at least one card fills.
     *
     * @return array<int, string>
     */
    public function headers(): array
    {
        if ($this->headers === null) {
            $used = [];

            foreach ($this->cards() as $card) {
                $used += array_filter($card, fn ($value) => $value !== '');
            }

            $this->headers = array_values(array_filter(self::COLUMNS, fn ($column) => isset($used[$column])));
        }

        return $this->headers;
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    protected function rows(): Generator
    {
        $headers = $this->headers();

        foreach ($this->cards() as $number => $card) {
            yield $number => array_combine($headers, array_map(fn ($column) => $card[$column] ?? '', $headers));
        }
    }

    /**
     * Yield each card as column values, keyed by card number.
     *
     * @return Generator<int, array<string, string>>
     */
    protected function cards(): Generator
    {
        $number = 0;
        $properties = null;

        foreach ($this->lines() as $line) {
            [$name, $params, $value] = $this->parse($line);

            if ($name === 'BEGIN' && strtoupper($value) === 'VCARD') {
                $properties = [];

                continue;
            }

            if ($name === 'END' && strtoupper($value) === 'VCARD') {
                if ($properties !== null) {
                    yield ++$number => $this->card($properties);
                }

                $properties = null;

                continue;
            }

            if ($properties !== null && $name !== '') {
                $properties[] = [$name, $params, $value];
            }
        }
    }

    /**
     * Yield logical lines, joining folded continuation lines back together.
     *
     * @return Generator<int, string>
     */
    protected function lines(): Generator
    {
        $file = new SplFileObject($this->path);
        $file->setFlags(SplFileObject::DROP_NEW_LINE);

        $current = null;

        foreach ($file as $index => $line) {
            $line = rtrim((string) $line, "\r");

            if ($index === 0) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
            }

            if ($current !== null && $line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
                $current .= substr($line, 1);

                continue;
            }

            if ($current !== null) {
                yield $current;
            }

            $current = $line;
        }

        if ($current !== null) {
            yield $current;
        }
    }

    /**
     * Split a line into its property name, parameters and value.
     *
     * @return array{0: string, 1: array<string, string>, 2: string}
     */
    protected function parse(string $line): array
    {
        $colon = strpos($line, ':');

        if ($colon === false) {
            return ['', [], ''];
        }

        $parts = explode(';', substr($line, 0, $colon));
        $name = strtoupper((string) preg_replace('/^.*\./', '', array_shift($parts)));

        $params = [];

        foreach ($parts as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
            $value === null
                ? $params['TYPE'] = trim(($params['TYPE'] ?? '').','.$key, ',')
                : $params[strtoupper($key)] = $value;
        }

        $value = substr($line, $colon + 1);

        if (strtoupper($params['ENCODING'] ?? '') === 'QUOTED-PRINTABLE') {
            $value = quoted_printable_decode($value);
        }

        return [$name, $params, $value];
    }

    /**
     * Turn a card's properties into column values.
     *
     * @param  array<int, array{0: string, 1: array<string, string>, 2: string}>  $properties
     * @return array<string, string>
     */
    protected function card(array $properties): array
    {
        $first = fn (string $name) => collect($properties)->first(fn ($property) => $property[0] === $name);

        $names = ($n = $first('N')) ? $this->components($n[2]) : [];
        [$firstName, $lastName] = [$names[1] ?? '', $names[0] ?? ''];

        if ($firstName === '' && $lastName === '' && ($fn = $first('FN'))) {
            [$firstName, $lastName] = array_pad(explode(' ', $this->unescape($fn[2]), 2), 2, '');
        }

        $phones = collect($properties)->filter(fn ($property) => $property[0] === 'TEL');
        $phone = $phones->first(fn ($property) => str_contains(strtoupper($property[1]['TYPE'] ?? ''), 'CELL')) ?? $phones->first();

        $address = ($adr = $first('ADR')) ? $this->components($adr[2]) : [];

        return array_map('trim', [
            'First name' => $firstName,
            'Last name' => $lastName,
            'Email' => ($email = $first('EMAIL')) ? $this->unescape($email[2]) : '',
            'Phone' => $phone ? preg_replace('/^tel:/i', '', $this->unescape($phone[2])) : '',
            'Organization' => ($org = $first('ORG')) ? ($this->components($org[2])[0] ?? '') : '',
            'City' => $address[3] ?? '',
            'Birthday' => ($bday = $first('BDAY')) ? $this->unescape($bday[2]) : '',
            'Note' => ($note = $first('NOTE')) ? $this->unescape($note[2]) : '',
        ]);
    }

    /**
     * Split a structured value on unescaped semicolons.
     *
     * @return array<int, string>
     */
    protected function components(string $value): array
    {
        return array_map(fn ($part) => trim($this->unescape($part)), preg_split('/(?<!\\\\);/', $value));
    }

    protected function unescape(string $value): string
    {
        return strtr($value, ['\\n' => "\n", '\\N' => "\n", '\\,' => ',', '\\;' => ';', '\\:' => ':', '\\\\' => '\\']);
    }
}
