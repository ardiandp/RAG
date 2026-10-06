<?php

namespace App\Services;

class TextChunker
{
    /**
     * Pecah teks menjadi potongan dengan panjang mendekati $chunkSize
     * (dalam karakter) dan saling tumpang-tindih $overlap karakter.
     *
     * @return array<int, string>
     */
    public static function chunk(string $text, int $chunkSize = 800, int $overlap = 100): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($text === '') {
            return [];
        }

        $chunks = [];
        $length = mb_strlen($text);
        $start = 0;

        while ($start < $length) {
            $end = min($start + $chunkSize, $length);

            if ($end < $length) {
                $window = mb_substr($text, $start, $chunkSize);
                $break = mb_strrpos($window, ' ');

                if ($break !== false && $break > $chunkSize / 2) {
                    $end = $start + $break;
                }
            }

            $chunk = trim(mb_substr($text, $start, $end - $start));

            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            if ($end >= $length) {
                break;
            }

            $start = $end - $overlap;
        }

        return $chunks;
    }
}
