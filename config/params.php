<?php

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    'elasticUrl' => getenv('ELASTIC_URL') ?: 'http://opensearch:9200',
];
