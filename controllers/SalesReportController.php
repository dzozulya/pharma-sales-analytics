<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\SalesReportSearch;
use app\services\SalesReportService;
use Yii;
use yii\web\Controller;

final class SalesReportController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly SalesReportService $salesReportService,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): string
    {
        $searchModel = new SalesReportSearch();

        $searchModel->load(Yii::$app->request->get());
        $searchModel->validate();

        $rows = $this->salesReportService->getRegionProductQuantityReport(
            region: $searchModel->region,
            product: $searchModel->product,
            limit: 200,
        );

        return $this->render('index', [
            'searchModel' => $searchModel,
            'rows' => $rows,
        ]);
    }
}