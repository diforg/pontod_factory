<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FilamentCodeGenerator
{
    /**
     * Generate the next unique code for a material/brand/color prefix.
     *
     * This generator does not insert the Filament row itself. The caller MUST call
     * this method inside an already-open transaction and insert the Filament
     * immediately in that same transaction so the generated sequence remains atomic.
     *
     * Race on first insertion: when there is no existing row for the prefix, there is
     * no row to lock with FOR UPDATE. Two concurrent transactions can therefore both
     * calculate 0001. The caller is responsible for retrying on SQLSTATE 23000/23505
     * (max 3 attempts) and re-running generation after the unique constraint violation.
     *
     * Sanitization rules:
     * - transliterate to ASCII (Str::ascii) so accents become plain letters
     *   ("Céu" -> "Ceu"); iconv //TRANSLIT is NOT used because Alpine/musl does
     *   not implement it reliably.
     * - uppercase material, brand, and color
     * - spaces become a single hyphen
     * - collapse repeated hyphens
     * - drop all non-alphanumeric characters except hyphen
     * - trim leading/trailing hyphen
     *
     * We intentionally keep the hyphen separator in the prefix, so "Mult Filament"
     * becomes "MULT-FILAMENT" instead of "MULTFILAMENT". The code format is explicitly
     * structured as MATERIAL-BRAND-COLOR-NNNN, and the hyphen is part of the contract.
     *
     * Numeric format:
     * - values below 10000 use 4 digits (0001, 0099, 9999)
     * - values at or above 10000 grow to 5 digits without padding (10000, 12345)
     */
    public function generate(string $material, string $brand, string $color): string
    {
        $prefix = $this->buildPrefix($material, $brand, $color);

        return DB::transaction(function () use ($prefix) {
            $last = DB::table('filaments')
                ->where('code', 'like', $prefix.'-%')
                ->orderByDesc('code')
                ->lockForUpdate()
                ->first(['code']);

            $nextNumber = 1;

            if (is_object($last) && isset($last->code) && is_string($last->code) && $last->code !== '') {
                $suffix = substr($last->code, strlen($prefix) + 1);

                if (ctype_digit($suffix)) {
                    $nextNumber = ((int) $suffix) + 1;
                }
            }

            if ($nextNumber < 10000) {
                return $prefix.'-'.sprintf('%04d', $nextNumber);
            }

            return $prefix.'-'.(string) $nextNumber;
        });
    }

    private function buildPrefix(string $material, string $brand, string $color): string
    {
        $segments = [
            $this->sanitizeSegment($material),
            $this->sanitizeSegment($brand),
            $this->sanitizeSegment($color),
        ];

        return implode('-', array_filter($segments, static fn (string $segment): bool => $segment !== ''));
    }

    private function sanitizeSegment(string $value): string
    {
        $normalized = trim($value);
        $normalized = Str::ascii($normalized);
        $normalized = strtoupper($normalized);
        $normalized = preg_replace('/[^A-Za-z0-9]+/', '-', $normalized);
        $normalized = preg_replace('/-+/', '-', (string) $normalized);

        return trim((string) $normalized, '-');
    }
}
