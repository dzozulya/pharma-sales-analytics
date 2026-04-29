<?php

return [
    'mongodb' => [
        'class' => yii\mongodb\Connection::class,
        'dsn' => getenv('MONGODB_DSN') ?: 'mongodb://mongodb:27017/datamind_test',
    ],
];
