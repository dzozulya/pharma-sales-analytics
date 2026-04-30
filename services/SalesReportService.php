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

        if ($region !== null && trim($region) !== '') {
            $filters[] = [
                'wildcard' => [
                    'region_search' => '*' . mb_strtolower(trim($region)) . '*',
                ],
            ];
        }

        if ($product !== null && trim($product) !== '') {
            $filters[] = [
                'wildcard' => [
                    'product_search' => '*' . mb_strtolower(trim($product)) . '*',
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

        $response = (new Client([
            'transport' => 'yii\httpclient\CurlTransport',
        ]))
            ->createRequest()
            ->setMethod('POST')
            ->setUrl("{$baseUrl}/" . self::INDEX . '/_search')
            ->setHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->setContent(json_encode($query, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))
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