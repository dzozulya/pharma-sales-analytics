<?php

declare(strict_types=1);

namespace app\services;

use Yii;
use yii\mongodb\Connection;

final class MongoImportService
{
    public function __construct(
        private readonly CsvRowNormalizer $normalizer = new CsvRowNormalizer(),
    ) {
    }

    public function import(string $filePath): array
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException("CSV file not found: {$filePath}");
        }

        $fileHash = sha1_file($filePath);

        if ($fileHash === false) {
            throw new \RuntimeException("Cannot calculate file hash: {$filePath}");
        }

        $collection = $this->mongo()->getCollection('badm_sales_raw');

        $collection->createIndex(
            ['import_key' => 1],
            ['unique' => true]
        );

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            throw new \RuntimeException("Cannot open CSV file: {$filePath}");
        }

        $header = fgetcsv($handle, 0, ',');

        if ($header === false) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty.');
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);

        $this->normalizer->validateHeader($header);

        $processed = 0;
        $inserted = 0;
        $updated = 0;
        $rowNumber = 1;

        while (($csvRow = fgetcsv($handle, 0, ',')) !== false) {
            $rowNumber++;

            if (count($csvRow) !== count($header)) {
                Yii::warning("Skipped malformed CSV row #{$rowNumber}", __METHOD__);
                continue;
            }

            $row = array_combine($header, $csvRow);

            if ($row === false) {
                continue;
            }

            $document = $this->normalizer->normalizeForMongo($row, $rowNumber, $fileHash);
            $importKey = $document['import_key'];

            unset($document['created_at']);

            $document['updated_at'] = new \MongoDB\BSON\UTCDateTime();

            $result = $collection->update(
                ['import_key' => $importKey],
                [
                    '$set' => $document,
                    '$setOnInsert' => [
                        'created_at' => new \MongoDB\BSON\UTCDateTime(),
                    ],
                ],
                ['upsert' => true]
            );

            $processed++;

            if (($result['upserted'] ?? null) !== null || (int)($result['n'] ?? 0) === 0) {
                $inserted++;
            } else {
                $updated++;
            }
        }

        fclose($handle);

        return [
            'processed' => $processed,
            'inserted' => $inserted,
            'updated' => $updated,
        ];
    }

    private function mongo(): Connection
    {
        /** @var Connection $mongodb */
        $mongodb = Yii::$app->mongodb;

        return $mongodb;
    }
}