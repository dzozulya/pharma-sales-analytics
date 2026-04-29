<?php

declare(strict_types=1);

namespace app\models;

use yii\base\Model;

final class SalesReportSearch extends Model
{
    public ?string $region = null;
    public ?string $product = null;

    public function rules(): array
    {
        return [
            [['region', 'product'], 'trim'],
            [['region', 'product'], 'string', 'max' => 255],
        ];
    }

    public function formName(): string
    {
        return '';
    }
}