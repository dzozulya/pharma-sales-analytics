<?php

/** @var yii\web\View $this */
/** @var app\models\SalesReportSearch $searchModel */
/** @var array<int, array{region:string, product:string, quantity:int}> $rows */

use yii\data\ArrayDataProvider;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'Sales report';

$dataProvider = new ArrayDataProvider([
    'allModels' => $rows,
    'pagination' => [
        'pageSize' => 50,
    ],
    'sort' => [
        'attributes' => [
            'region',
            'product',
            'quantity',
        ],
        'defaultOrder' => [
            'quantity' => SORT_DESC,
        ],
    ],
]);
?>

<div class="sales-report-index">

    <div class="panel panel-default">
        <div class="panel-heading">
            <h1 class="panel-title">
                <?= Html::encode($this->title) ?>
            </h1>
        </div>

        <div class="panel-body">
            <p class="text-muted">
                Aggregation by region and product. Quantity is calculated in Elasticsearch/OpenSearch.
            </p>

            <?php $form = ActiveForm::begin([
                'method' => 'get',
                'action' => ['index'],
                'options' => [
                    'class' => 'form-inline',
                ],
            ]); ?>

            <div class="form-group" style="margin-right: 10px;">
                <?= Html::activeTextInput($searchModel, 'region', [
                    'class' => 'form-control',
                    'placeholder' => 'Region, e.g. ВИННИЦКАЯ',
                ]) ?>
            </div>

            <div class="form-group" style="margin-right: 10px;">
                <?= Html::activeTextInput($searchModel, 'product', [
                    'class' => 'form-control',
                    'placeholder' => 'Product contains...',
                ]) ?>
            </div>

            <?= Html::submitButton('Filter', [
                'class' => 'btn btn-primary',
            ]) ?>

            <?= Html::a('Reset', ['index'], [
                'class' => 'btn btn-default',
            ]) ?>

            <?php ActiveForm::end(); ?>
        </div>
    </div>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'tableOptions' => [
            'class' => 'table table-striped table-bordered table-condensed',
        ],
        'summary' => 'Shown {begin}-{end} of {totalCount} rows',
        'columns' => [
            [
                'attribute' => 'region',
                'label' => 'Область',
            ],
            [
                'attribute' => 'product',
                'label' => 'Товар',
                'format' => 'text',
                'contentOptions' => [
                    'style' => 'max-width: 760px; white-space: normal;',
                ],
            ],
            [
                'attribute' => 'quantity',
                'label' => 'Количество',
                'format' => 'integer',
                'contentOptions' => [
                    'class' => 'text-right',
                ],
                'headerOptions' => [
                    'class' => 'text-right',
                ],
            ],
        ],
    ]) ?>

</div>
