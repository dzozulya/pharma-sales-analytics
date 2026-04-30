<?php

declare(strict_types=1);

namespace app\models;

use yii\base\Model;
use yii\web\UploadedFile;

final class CsvUploadForm extends Model
{
    public ?UploadedFile $csvFile = null;

    public function rules(): array
    {
        return [
            [
                ['csvFile'],
                'file',
                'skipOnEmpty' => false,
                'extensions' => ['csv'],
                'checkExtensionByMimeType' => false,
                'maxSize' => 50 * 1024 * 1024,
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'csvFile' => 'CSV file',
        ];
    }
}