<?php

namespace App\Messaging;

/**
 * Works out how many SMS parts a message needs, which is what providers bill by.
 */
final readonly class SmsSegments
{
    protected const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    protected const GSM_EXTENDED = "^{}\\[~]|€\f";

    public function __construct(
        public string $encoding,
        public int $length,
        public int $segments,
    ) {}

    public static function for(string $text): self
    {
        $length = 0;
        $gsm = true;

        foreach (mb_str_split($text) as $char) {
            if (mb_strpos(self::GSM_BASIC, $char) !== false) {
                $length++;
            } elseif (mb_strpos(self::GSM_EXTENDED, $char) !== false) {
                $length += 2;
            } else {
                $gsm = false;

                break;
            }
        }

        if (! $gsm) {
            $length = intdiv(strlen(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), 2);

            return new self('ucs2', $length, $length <= 70 ? 1 : (int) ceil($length / 67));
        }

        return new self('gsm7', $length, $length <= 160 ? 1 : (int) ceil($length / 153));
    }

    /**
     * @return array{encoding: string, length: int, segments: int}
     */
    public function toArray(): array
    {
        return [
            'encoding' => $this->encoding,
            'length' => $this->length,
            'segments' => $this->segments,
        ];
    }
}
