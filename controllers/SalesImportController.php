<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\CsvUploadForm;
use app\services\ElasticTransferService;
use app\services\MongoImportService;
use Yii;
use yii\helpers\FileHelper;
use yii\web\Controller;
use yii\web\Response;
use yii\web\UploadedFile;

final class SalesImportController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly MongoImportService $mongoImportService,
        private readonly ElasticTransferService $elasticTransferService,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): string
    {
        return $this->render('index', [
            'model' => new CsvUploadForm(),
        ]);
    }

    public function actionUpload(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $model = new CsvUploadForm();
        $model->csvFile = UploadedFile::getInstance($model, 'csvFile');

        if (!$model->validate()) {
            return [
                'success' => false,
                'message' => 'File validation failed.',
                'errors' => $model->getErrors(),
            ];
        }

        $uploadDir = Yii::getAlias('@app/storage/imports');

        FileHelper::createDirectory($uploadDir);

        $safeBaseName = preg_replace(
            '/[^a-zA-Z0-9А-Яа-яёЁіІїЇєЄ_\-.]/u',
            '_',
            $model->csvFile->baseName
        );

        $fileName = date('Ymd_His') . '_' . $safeBaseName . '.' . $model->csvFile->extension;
        $filePath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

        if (!$model->csvFile->saveAs($filePath)) {
            return [
                'success' => false,
                'message' => 'Cannot save uploaded file.',
            ];
        }

        try {
            $result = $this->mongoImportService->import($filePath);

            return [
                'success' => true,
                'message' => 'CSV imported to MongoDB successfully.',
                'file' => $fileName,
                'result' => $result,
            ];
        } catch (\Throwable $exception) {
            Yii::error($exception, __METHOD__);

            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    public function actionTransferToElastic(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        try {
            $result = $this->elasticTransferService->transfer();

            return [
                'success' => true,
                'message' => 'Data transferred to Elasticsearch/OpenSearch successfully.',
                'result' => $result,
            ];
        } catch (\Throwable $exception) {
            Yii::error($exception, __METHOD__);

            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }
}