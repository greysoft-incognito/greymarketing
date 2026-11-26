<?php

namespace App\Services\Audience;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Cleans contact identifiers so they deduplicate reliably within a workspace.
 */
class ContactNormalizer
{
    public function __construct(protected PhoneNumberUtil $phones) {}

    public function email(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * Format a phone number as E.164, reading local numbers in the given country.
     */
    public function phone(?string $phone, string $country = 'NG'): ?string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        try {
            $number = $this->phones->parse($phone, strtoupper($country));
        } catch (NumberParseException) {
            return null;
        }

        if (! $this->phones->isValidNumber($number)) {
            return null;
        }

        return $this->phones->format($number, PhoneNumberFormat::E164);
    }

    /**
     * Countries whose local phone numbers can be read, with their calling codes.
     *
     * @return array<int, array{code: string, dial_code: string}>
     */
    public function countries(): array
    {
        $regions = $this->phones->getSupportedRegions();

        sort($regions);

        return array_map(fn (string $region) => [
            'code' => $region,
            'dial_code' => '+' . $this->phones->getCountryCodeForRegion($region),
        ], $regions);
    }

    /**
     * @return array<int, string>
     */
    public function countryCodes(): array
    {
        return $this->phones->getSupportedRegions();
    }
}
