<?php

declare(strict_types=1);

namespace app\services;

use Yii;
use yii\httpclient\Client;

final class SalesReportService
{
    private const INDEX = 'badm_sales';

    public function getRegionProductQuantityReport(
        ?string $region = null,
        ?string $product = null,
        int $limit = 200
    ): array {
        $filters = [];

        if ($region !== null && $region !== '') {
            $filters[] = [
                'term' => [
                    'region' => $region,
                ],
            ];
        }

        if ($product !== null && $product !== '') {
            $filters[] = [
                'wildcard' => [
                    'product' => '*' . mb_strtolower($product) . '*',
                ],
            ];
        }

        $query = [
            'size' => 0,
            'query' => $filters === []
                ? ['match_all' => (object)[]]
                : ['bool' => ['filter' => $filters]],
            'aggs' => [
                'by_region' => [
                    'terms' => [
                        'field' => 'region',
                        'size' => 100,
                    ],
                    'aggs' => [
                        'by_product' => [
                            'terms' => [
                                'field' => 'product',
                                'size' => $limit,
                                'order' => [
                                    'qty_sum' => 'desc',
                                ],
                            ],
                            'aggs' => [
                                'qty_sum' => [
                                    'sum' => [
                                        'field' => 'quantity',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $baseUrl = rtrim((string)Yii::$app->params['elasticUrl'], '/');

        $response = new Client([
            'transport' => 'yii\httpclient\CurlTransport',
        ])
            ->post("{$baseUrl}/" . self::INDEX . '/_search', $query)
            ->send();

        if (!$response->isOk) {
            throw new \RuntimeException('Report query failed: ' . $response->content);
        }

        $rows = [];

        foreach (($response->data['aggregations']['by_region']['buckets'] ?? []) as $regionBucket) {
            foreach (($regionBucket['by_product']['buckets'] ?? []) as $productBucket) {
                $rows[] = [
                    'region' => (string)$regionBucket['key'],
                    'product' => (string)$productBucket['key'],
                    'quantity' => (int)($productBucket['qty_sum']['value'] ?? 0),
                ];
            }
        }

        return $rows;
    }
}