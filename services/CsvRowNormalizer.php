<?php

declare(strict_types=1);

namespace app\services;

final class CsvRowNormalizer
{
    public const COL_REGION = 'Область';
    public const COL_CITY = 'Город';
    public const COL_PRODUCT = 'Товар';
    public const COL_PRODUCT_CODE = 'Код товара';
    public const COL_QTY = 'Количество';

    public function validateHeader(array $header): void
    {
        $required = [
            self::COL_REGION,
            self::COL_CITY,
            self::COL_PRODUCT,
            self::COL_PRODUCT_CODE,
            self::COL_QTY,
        ];

        $missing = array_values(array_diff($required, $header));

        if ($missing !== []) {
            throw new \RuntimeException(
                'CSV does not contain required columns: ' . implode(', ', $missing)
            );
        }
    }

    public function normalizeForMongo(array $row, int $rowNumber, string $fileHash): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[$this->normalizeHeader((string)$key)] = $this->normalizeValue($value);
        }

        $normalized['row_number'] = $rowNumber;
        $normalized['file_hash'] = $fileHash;
        $normalized['import_key'] = $this->buildImportKey($fileHash, $rowNumber);
        $normalized['quantity'] = $this->toInt($row[self::COL_QTY] ?? 0);
        $normalized['created_at'] = new \MongoDB\BSON\UTCDateTime();
        $normalized['updated_at'] = new \MongoDB\BSON\UTCDateTime();

        return $normalized;
    }

    public function normalizeForElastic(array $mongoDocument): array
    {
        $product = (string)($mongoDocument[self::COL_PRODUCT] ?? '');
        $region = (string)($mongoDocument[self::COL_REGION] ?? '');

        return [
            'import_key' => (string)($mongoDocument['import_key'] ?? ''),
            'region' => $region,
            'region_search' => mb_strtolower($region),
            'city' => (string)($mongoDocument[self::COL_CITY] ?? ''),
            'product' => $product,
            'product_search' => mb_strtolower($product),
            'product_code' => (string)($mongoDocument[self::COL_PRODUCT_CODE] ?? ''),
            'quantity' => $this->toInt($mongoDocument[self::COL_QTY] ?? $mongoDocument['quantity'] ?? 0),
        ];
    }
    private function normalizeHeader(string $header): string
    {
        return trim(preg_replace('/\x{FEFF}/u', '', $header) ?? $header);
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            return $value === '' ? null : $value;
        }

        return $value;
    }

    private function toInt(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }

        $prepared = str_replace([' ', ','], ['', '.'], (string)$value);

        return is_numeric($prepared) ? (int)$prepared : 0;
    }

    private function buildImportKey(string $fileHash, int $rowNumber): string
    {
        return sha1($fileHash . ':' . $rowNumber);
    }
}