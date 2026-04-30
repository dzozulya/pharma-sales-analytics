<?php

/** @var yii\web\View $this */
/** @var app\models\CsvUploadForm $model */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

$this->title = 'Sales data import';

$this->registerJsFile('/js/sales-import.js', [
    'depends' => [
        \yii\web\JqueryAsset::class,
        \yii\web\YiiAsset::class,
    ],
]);

$uploadUrl = Url::to(['sales-import/upload']);
$transferUrl = Url::to(['sales-import/transfer-to-elastic']);
$reportUrl = Url::to(['sales-report/index']);

$this->registerJsVar('salesImportConfig', [
    'uploadUrl' => $uploadUrl,
    'transferUrl' => $transferUrl,
    'reportUrl' => $reportUrl,
]);
?>

<div class="sales-import-index">

    <div class="panel panel-default">
        <div class="panel-heading">
            <h1 class="panel-title">
                <?= Html::encode($this->title) ?>
            </h1>
        </div>

        <div class="panel-body">

            <p class="text-muted">
                Upload CSV file, import raw rows into MongoDB and then transfer selected fields to Elasticsearch/OpenSearch.
            </p>

            <?php $form = ActiveForm::begin([
                'id' => 'csv-upload-form',
                'options' => [
                    'enctype' => 'multipart/form-data',
                ],
            ]); ?>

            <?= $form->field($model, 'csvFile')->fileInput([
                'accept' => '.csv,text/csv',
            ]) ?>

            <div class="form-group">
                <?= Html::submitButton('Upload and import to MongoDB', [
                    'class' => 'btn btn-primary',
                    'id' => 'upload-button',
                ]) ?>

                <?= Html::button('Transfer to Elasticsearch', [
                    'class' => 'btn btn-success',
                    'id' => 'transfer-button',
                    'disabled' => true,
                ]) ?>

                <?= Html::a(
                        '<span class="glyphicon glyphicon-stats"></span> Open report',
                        ['sales-report/index'],
                        [
                                'class' => 'btn btn-info btn-lg report-button',
                                'id' => 'report-button',
                                'style' => 'display: none;',
                                'encode' => false,
                        ]
                ) ?>
            </div>

            <?php ActiveForm::end(); ?>

            <div id="upload-progress-wrapper" style="display: none; margin-top: 20px;">
                <div class="progress">
                    <div
                        id="upload-progress-bar"
                        class="progress-bar progress-bar-striped active"
                        role="progressbar"
                        style="width: 0%;"
                    >
                        0%
                    </div>
                </div>
            </div>

            <div id="processing-indicator" style="display: none; margin-top: 15px;">
                <span class="glyphicon glyphicon-refresh glyphicon-spin"></span>
                Processing data, please wait...
            </div>

            <div id="import-result" style="display: none; margin-top: 20px;"></div>

        </div>
    </div>

</div>

<style>
    .glyphicon-spin {
        animation: glyphicon-spin 1s infinite linear;
    }

    @keyframes glyphicon-spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    #import-result pre {
        margin-top: 10px;
        background: #f8f8f8;
        border: 1px solid #ddd;
        padding: 12px;
    }

    .report-button {
        margin-left: 10px;
        padding: 10px 22px;
        font-weight: 600;
        border-radius: 4px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, .12);
    }

    .report-button .glyphicon {
        margin-right: 6px;
    }
</style>