<?php

declare(strict_types=1);

namespace app\services;

use Yii;
use yii\httpclient\Client;
use yii\mongodb\Connection;

final class ElasticTransferService
{
    private const INDEX = 'badm_sales';

    public function __construct(
        private readonly CsvRowNormalizer $normalizer,
    ) {
    }

    public function transfer(int $batchSize = 500): array
    {
        $this->createIndexIfMissing();

        $collection = $this->mongo()->getCollection('badm_sales_raw');
        $cursor = $collection->find([], ['batchSize' => $batchSize]);

        $processed = 0;
        $indexed = 0;
        $bulk = [];

        foreach ($cursor as $document) {
            $source = $this->normalizer->normalizeForElastic((array)$document);

            if ($source['import_key'] === '') {
                continue;
            }

            $bulk[] = json_encode([
                'index' => [
                    '_index' => self::INDEX,
                    '_id' => $source['import_key'],
                ],
            ], JSON_UNESCAPED_UNICODE);

            $bulk[] = json_encode($source, JSON_UNESCAPED_UNICODE);

            $processed++;

            if (count($bulk) >= $batchSize * 2) {
                $indexed += $this->sendBulk($bulk);
                $bulk = [];
            }
        }

        if ($bulk !== []) {
            $indexed += $this->sendBulk($bulk);
        }

        return [
            'processed' => $processed,
            'indexed' => $indexed,
        ];
    }

    private function createIndexIfMissing(): void
    {
        $baseUrl = rtrim((string)Yii::$app->params['elasticUrl'], '/');
        $client = $this->httpClient();

        $exists = $client
            ->head("{$baseUrl}/" . self::INDEX)
            ->send();

        if ($exists->isOk) {
            return;
        }

        $mapping = [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
            'mappings' => [
                'properties' => [
                    'import_key' => ['type' => 'keyword'],
                    'region' => ['type' => 'keyword'],
                    'city' => ['type' => 'keyword'],
                    'product' => ['type' => 'keyword'],
                    'product_code' => ['type' => 'keyword'],
                    'quantity' => ['type' => 'integer'],
                ],
            ],
        ];

        $response = $client
            ->put("{$baseUrl}/" . self::INDEX, $mapping)
            ->send();

        if (!$response->isOk) {
            throw new \RuntimeException('Cannot create ES index: ' . $response->content);
        }
    }

    private function sendBulk(array $bulk): int
    {
        $baseUrl = rtrim((string)Yii::$app->params['elasticUrl'], '/');
        $body = implode("\n", $bulk) . "\n";

        $response = $this->httpClient()
            ->post("{$baseUrl}/_bulk", $body)
            ->addHeaders([
                'Content-Type' => 'application/x-ndjson',
            ])
            ->send();

        if (!$response->isOk) {
            throw new \RuntimeException('Bulk indexing failed: ' . $response->content);
        }

        $data = $response->data;

        if (!empty($data['errors'])) {
            Yii::error($data, __METHOD__);
            throw new \RuntimeException('Bulk indexing completed with errors. Check Yii logs.');
        }

        return (int)(count($bulk) / 2);
    }

    private function httpClient(): Client
    {
        return new Client([
            'transport' => 'yii\httpclient\CurlTransport',
        ]);
    }

    private function mongo(): Connection
    {
        /** @var Connection $mongodb */
        $mongodb = Yii::$app->mongodb;

        return $mongodb;
    }
}