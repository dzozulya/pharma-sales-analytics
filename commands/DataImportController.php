<?php

declare(strict_types=1);

namespace app\commands;

use app\services\ElasticTransferService;
use app\services\MongoImportService;
use app\services\SalesReportService;
use yii\console\Controller;
use yii\console\ExitCode;

final class DataImportController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly MongoImportService $mongoImportService,
        private readonly ElasticTransferService $elasticTransferService,
        private readonly SalesReportService $salesReportService,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }
    public function actionMongo(string $filePath = '@app/storage/imports/badm.csv'): int
    {
        $filePath = \Yii::getAlias($filePath);

        $result = $this->mongoImportService->import($filePath);

        $this->stdout("Mongo import completed\n");
        $this->stdout("Processed: {$result['processed']}\n");
        $this->stdout("Inserted: {$result['inserted']}\n");
        $this->stdout("Updated: {$result['updated']}\n");

        return ExitCode::OK;
    }

    public function actionElastic(): int
    {
        $result = $this->elasticTransferService->transfer();

        $this->stdout("Elastic transfer completed\n");
        $this->stdout("Processed: {$result['processed']}\n");
        $this->stdout("Indexed/upserted: {$result['indexed']}\n");

        return ExitCode::OK;
    }

    public function actionReport(): int
    {
        $rows = $this->salesReportService->getRegionProductQuantityReport(limit: 20);

        foreach ($rows as $row) {
            $this->stdout(sprintf(
                "%s | %s | %d\n",
                $row['region'],
                $row['product'],
                $row['quantity']
            ));
        }

        return ExitCode::OK;
    }
}