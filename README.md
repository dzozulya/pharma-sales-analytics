# Pharma Sales Analytics

Міні-модуль для імпорту, індексації та аналітики продажів фармацевтичної продукції.

Проєкт реалізовано на Yii2 basic. Основна ідея: завантажити CSV-файл з продажами, зберегти сирі дані в MongoDB, перенести потрібні поля в Elasticsearch/OpenSearch і побудувати агрегований звіт по області та товару.

## Що робить проєкт

1. Завантажує CSV-файл через UI або консольну команду.
2. Зберігає всі сирі рядки CSV у MongoDB.
3. Переносить вибрані поля з MongoDB в Elasticsearch/OpenSearch.
4. Не створює дублікати при повторному запуску імпорту або індексації.
5. Будує звіт з групуванням по області та товару.
6. Показує результат у мінімалістичному Yii2 UI.

## Технології

- PHP 8.3
- Yii2 basic
- MongoDB
- Elasticsearch/OpenSearch
- Docker
- Nginx
- jQuery Ajax
- Bootstrap / Yii2 GridView

## Бізнес-сценарій

В аналітиці часто потрібно регулярно завантажувати файли продажів від дистрибʼюторів, зберігати первинні дані та будувати аналітичні звіти.

У цьому проєкті MongoDB використовується як raw storage для імпортованих рядків, а Elasticsearch/OpenSearch — як аналітичний індекс для швидких агрегацій.

Загальний flow:

```text
CSV upload → MongoDB raw storage → Elasticsearch/OpenSearch index → Aggregated report
```

## Використані колонки

Для індексації в Elasticsearch/OpenSearch використовуються 5 колонок:

| CSV column | Index field |
| --- | --- |
| Область | region |
| Город | city |
| Товар | product |
| Код товара | product_code |
| Количество | quantity |

Для звіту використовуються:

- `Область`
- `Товар`
- сума по `Количество`

## Архітектура

Основна бізнес-логіка винесена в сервісний шар:

| Class | Responsibility |
| --- | --- |
| `CsvRowNormalizer` | Нормалізація CSV-рядків і підготовка даних для MongoDB та Elasticsearch/OpenSearch |
| `MongoImportService` | Імпорт CSV у MongoDB |
| `ElasticTransferService` | Перенесення даних з MongoDB в Elasticsearch/OpenSearch |
| `SalesReportService` | Побудова агрегованого звіту |
| `CsvUploadForm` | Валідація CSV-файлу при завантаженні через UI |
| `SalesReportSearch` | Модель фільтрів для UI-звіту |

Контролери залишаються тонкими. Вони приймають HTTP-запити, викликають потрібний сервіс і повертають результат.

Сервіси передаються в контролери через constructor injection. Створення сервісів через `new` у контролерах не використовується.

## Структура проєкту

```text
commands/
  DataImportController.php

controllers/
  SalesImportController.php
  SalesReportController.php

models/
  CsvUploadForm.php
  SalesReportSearch.php

services/
  CsvRowNormalizer.php
  MongoImportService.php
  ElasticTransferService.php
  SalesReportService.php

views/
  sales-import/
    index.php
  sales-report/
    index.php

web/
  js/
    sales-import.js

config/
  db-components.php
  params.php

storage/
  imports/
```

## Чому немає окремої ActiveRecord-моделі для CSV-рядків

CSV-рядки не редагуються вручну і для них не потрібен CRUD.

MongoDB використовується як сховище сирих імпортованих документів. Elasticsearch/OpenSearch використовується як аналітичний індекс.

Тому робота з цими сховищами винесена в окремі сервіси. Це дозволяє не перевантажувати проєкт зайвими моделями і залишити код простим для підтримки.

## Ідемпотентність

Повторний запуск імпорту або індексації не створює дублікати.

Для кожного рядка формується стабільний ключ:

```php
$importKey = sha1($fileHash . ':' . $rowNumber);
```

Де:

- `$fileHash` — хеш CSV-файлу;
- `$rowNumber` — номер рядка у файлі.

У MongoDB для поля `import_key` створюється унікальний індекс.

В Elasticsearch/OpenSearch це саме значення використовується як `_id` документа.

Тому при повторному запуску:

- у MongoDB документ оновлюється через `upsert`;
- в Elasticsearch/OpenSearch документ переіндексовується з тим самим `_id`;
- дублікати не створюються.

## Встановлення

Склонувати прект з GitHub:

```bash
git clone https://github.com/dzozulya/pharma-sales-analytics
```


Підняти контейнери:

```bash
docker compose up -d --build
```

Перевірити контейнери:

```bash
docker compose ps
```

Встановити залежності всередині контейнера:

```bash
docker compose exec app composer install
```

## Налаштування MongoDB та OpenSearch

MongoDB DSN задається через змінну оточення:

```env
MONGODB_DSN=mongodb://mongodb:27017/datamind_test
```

URL Elasticsearch/OpenSearch:

```env
ELASTIC_URL=http://opensearch:9200
```

Приклад `config/db-components.php`:

```php
<?php

return [
    'mongodb' => [
        'class' => yii\mongodb\Connection::class,
        'dsn' => getenv('MONGODB_DSN') ?: 'mongodb://mongodb:27017/datamind_test',
    ],
];
```

Приклад `config/params.php`:

```php
<?php

return [
    'elasticUrl' => getenv('ELASTIC_URL') ?: 'http://opensearch:9200',
];
```

Важливо: `db-components.php` має бути підключений і в `config/web.php`, і в `config/console.php`, щоб UI та консольні команди використовували одну MongoDB базу.

## Зберігання CSV-файлів

Усі завантажені CSV-файли зберігаються в директорії:

```text
storage/imports
```

У Docker-контейнері цей шлях відповідає:

```text
/app/storage/imports
```

З хост-машини файли доступні тут:

```text
./storage/imports
```
За умовченням виректорія з тестовим файлом вже додана до проекту

Якщо у `docker-compose.yml` є volume:

```yaml
volumes:
  - ./:/app
```

то окремий volume для `storage/imports` не потрібен.

## UI-імпорт

Відкрити сторінку імпорту:

```text
http://localhost:8080/sales-import/index
```

Або, якщо prettyUrl не увімкнено:

```text
http://localhost:8080/index.php?r=sales-import%2Findex
```

На сторінці можна:

1. вибрати CSV-файл;
2. завантажити його через Ajax;
3. бачити індикатор завантаження;
4. імпортувати рядки в MongoDB;
5. запустити перенесення даних в Elasticsearch/OpenSearch;
6. перейти до агрегованого звіту.

Після імпорту буде показано результат:

```json
{
  "processed": 3540,
  "inserted": 3540,
  "updated": 0
}
```

При повторному імпорті того самого файлу:

```json
{
  "processed": 3540,
  "inserted": 0,
  "updated": 3540
}
```

## Ліміти завантаження файлів

Для завантаження CSV через UI потрібно збільшити ліміти Nginx та PHP.

Приклад для Nginx:

```nginx
client_max_body_size 50M;
```

Приклад `docker/php/uploads.ini`:

```ini
upload_max_filesize=50M
post_max_size=50M
max_execution_time=300
memory_limit=512M
```

Також у `CsvUploadForm` потрібно явно задати максимальний розмір файлу:

```php
'maxSize' => 50 * 1024 * 1024,
```

## Перенесення даних в Elasticsearch/OpenSearch

Після імпорту CSV в MongoDB потрібно запустити перенесення в Elasticsearch/OpenSearch.

Через UI це робиться кнопкою:

```text
Transfer to Elasticsearch
```

Через консоль:

```bash
docker compose exec app php yii data-import/elastic
```

Очікуваний результат:

```text
Elastic transfer completed
Processed: 3540
Indexed/upserted: 3540
```

## Звіт

Відкрити сторінку звіту:

```text
http://localhost:8080/sales-report/index
```

Або, якщо prettyUrl не увімкнено:

```text
http://localhost:8080/index.php?r=sales-report%2Findex
```

Звіт показує агреговані дані:

```text
Область | Товар | Количество
```

Дані групуються по області та товару, а поле `Количество` сумується в Elasticsearch/OpenSearch.

У звіті доступна фільтрація по області та товару.

## Консольні команди

Імпорт CSV у MongoDB:

```bash
docker compose exec app php yii data-import/mongo @app/storage/imports/badm.csv
```

Якщо в `DataImportController` заданий шлях за замовчуванням, можна запускати так:

```bash
docker compose exec app php yii data-import/mongo
```

Перенесення з MongoDB в Elasticsearch/OpenSearch:

```bash
docker compose exec app php yii data-import/elastic
```

Консольний звіт:

```bash
docker compose exec app php yii data-import/report
```

## Перевірка MongoDB

Перевірити кількість документів у MongoDB:

```bash
docker compose exec mongodb mongosh datamind_test --eval 'db.badm_sales_raw.countDocuments()'
```

Очікуваний результат:

```text
3540
```

Перевірити, який MongoDB DSN бачить Yii console:

```bash
docker compose exec app php -r 'require "/app/vendor/autoload.php"; require "/app/vendor/yiisoft/yii2/Yii.php"; $config=require "/app/config/console.php"; new yii\console\Application($config); echo Yii::$app->mongodb->dsn.PHP_EOL;'
```

## Перевірка OpenSearch

Кількість документів в індексі:

```bash
curl http://localhost:9200/badm_sales/_count
```

Очікуваний результат:

```json
{
  "count": 3540
}
```

Подивитись один документ:

```bash
curl "http://localhost:9200/badm_sales/_search?pretty=true&size=1"
```

В `_source` мають бути поля:

```json
{
  "import_key": "...",
  "region": "...",
  "city": "...",
  "product": "...",
  "product_code": "...",
  "quantity": 123
}
```

Ручна перевірка агрегації:

```bash
curl -X POST http://localhost:9200/badm_sales/_search?pretty=true \
  -H 'Content-Type: application/json' \
  -d '{
    "size": 0,
    "aggs": {
      "by_region": {
        "terms": {
          "field": "region",
          "size": 20
        },
        "aggs": {
          "by_product": {
            "terms": {
              "field": "product",
              "size": 20
            },
            "aggs": {
              "qty_sum": {
                "sum": {
                  "field": "quantity"
                }
              }
            }
          }
        }
      }
    }
  }'
```

## Очищення даних

Видалити індекс OpenSearch:

```bash
curl -X DELETE http://localhost:9200/badm_sales
```

Очистити MongoDB колекцію:

```bash
docker compose exec mongodb mongosh datamind_test --eval 'db.badm_sales_raw.drop()'
```

Після цього можна повторити імпорт та індексацію.



## Обробка помилок

Якщо CSV не містить необхідних колонок, імпорт буде зупинено з повідомленням про відсутні поля.

Обовʼязкові колонки:

- `Область`
- `Город`
- `Товар`
- `Код товара`
- `Количество`

Некоректні рядки CSV пропускаються, а інформація про них записується в Yii logs.

## Основні припущення

1. CSV-файл має кодування UTF-8.
2. Роздільник CSV — кома.
3. Колонка кількості називається `Количество`.
4. MongoDB зберігає всі сирі дані з CSV.
5. Elasticsearch/OpenSearch використовується для аналітичного звіту.
6. UI-імпорт потрібен для ручного сценарію.
7. Консольні команди потрібні для dev/admin/cron сценаріїв.
8. Для тестового завдання достатньо мінімального UI без авторизації.

## Можливі покращення

У production-версії можна додати:

- чергу для імпорту великих файлів;
- окремий журнал імпортів;
- статуси імпорту: `pending`, `processing`, `completed`, `failed`;
- реальний прогрес обробки рядків після завантаження файлу;
- авторизацію для admin UI;
- фонову переіндексацію через Yii2 queue;
- розширену валідацію CSV;
- підтримку різних роздільників CSV;
- tenant/client id для мультитенантної архітектури;
- окремі індекси або aliases для різних клієнтів.

## Коротко

Проєкт демонструє типовий цикл роботи з аналітичними даними:

```text
CSV upload → MongoDB raw storage → Elasticsearch/OpenSearch index → Aggregated report
```

Основна логіка винесена в сервіси, тому її можна використовувати як з UI, так і з консольних команд.
