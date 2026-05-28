# evgenmil/webhook-storage

Framework-agnostic слой хранения вебхуков для PHP.

- Одна таблица MySQL на каждый источник вебхуков.
- Единая схема, отличается только имя таблицы.
- Жизненный цикл записи: `pending → processing → done | failed`.
- Идемпотентность по `external_event_id`.
- Подключается в любой фреймворк через DI. Никаких зависимостей от Yii/Laravel/Symfony.

Что модуль **делает**: сохраняет вебхук и обновляет его статус.
Что модуль **не делает**: парсинг payload, проверка подписи, очереди, бизнес-логика, HTTP-роутинг.

## Установка

```bash
composer require evgenmil/webhook-storage
```

Требования: PHP ^8.1, `ext-pdo`, `ext-json`.

## Концепция

- **Источник** — строковый slug (`amocrm`, `bitrix24`, ...). Задаёт приложение.
- **Таблица** — своя на каждый источник, имя задаёт приложение через `SourceTableMap`.
- **Схема таблицы** — берётся из `WebhookSchema`, единая для всех источников.

Приложение отвечает за:
1. создание таблицы (миграция своего фреймворка, SQL берётся из `WebhookSchema`);
2. вычисление `external_event_id` (id события от вендора или хэш тела);
3. перевод статуса (`markProcessing` / `markDone` / `markFailed`).

## Миграции

SQL живёт **в пакете**, чтобы не было копипасты. Миграция приложения — это одна строка.

### Yii2

```php
use evgenmil\WebhookStorage\Schema\WebhookSchema;

class m260528_120000_create_webhooks_amocrm extends \yii\db\Migration
{
    public function safeUp(): void
    {
        $this->execute(WebhookSchema::createTableSql('webhooks_amocrm'));
    }

    public function safeDown(): void
    {
        $this->execute(WebhookSchema::dropTableSql('webhooks_amocrm'));
    }
}
```

Новый источник = новая миграция, в которой меняется **только имя таблицы**. То же самое в Phinx / Doctrine Migrations / Laravel — везде сводится к `execute(WebhookSchema::createTableSql($table))`.

## Сборка через DI

```php
use evgenmil\WebhookStorage\WebhookStore;
use evgenmil\WebhookStorage\SourceTableMap;
use evgenmil\WebhookStorage\Repository\PdoMysqlWebhookRepository;

$pdo = new \PDO(
    'mysql:host=127.0.0.1;dbname=app;charset=utf8mb4',
    $user,
    $password,
    [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES   => false,
    ]
);

$store = new WebhookStore(
    new PdoMysqlWebhookRepository($pdo),
    new SourceTableMap([
        'amocrm'   => 'webhooks_amocrm',
        'bitrix24' => 'webhooks_bitrix24',
    ]),
);
```

В контейнере (любой PSR-11 DI) регистрируется `WebhookStore` синглтоном — всё.

## Использование

```php
// 1. На приёме вебхука:
$result = $store->save(
    source:          'amocrm',
    externalEventId: $eventId,  // приложение само его извлекает или хэширует
    payload:         $payload,
);
// $result->id          — id записи в таблице webhooks_amocrm
// $result->isDuplicate — true, если такой event_id уже был

// 2. Отдаёте $result->id в очередь / воркер.

// 3. В воркере:
$store->markProcessing('amocrm', $id);

try {
    // ... ваша бизнес-логика ...
    $store->markDone('amocrm', $id);
} catch (\Throwable $e) {
    $store->markFailed('amocrm', $id, $e->getMessage());
}
```

## API

```php
WebhookStore::save(string $source, string $externalEventId, array $payload): SaveResult
WebhookStore::markProcessing(string $source, int $id): void   // attempts++
WebhookStore::markDone(string $source, int $id): void          // last_error = NULL
WebhookStore::markFailed(string $source, int $id, string $error): void

SaveResult { public int $id; public bool $isDuplicate; }

enum Status: string { Pending, Processing, Done, Failed }
```

## Исключения

- `evgenmil\WebhookStorage\Exception\UnknownSourceException` — `source` не зарегистрирован в `SourceTableMap`.
- `evgenmil\WebhookStorage\Exception\WebhookStorageException` — базовый класс пакетных ошибок.
- Ошибки `\PDOException` пробрасываются как есть (кроме `SQLSTATE 23000` в `save()`, который трактуется как дубликат).

## Тесты

```bash
composer install
composer test:unit          # юнит-тесты, без БД
composer test:integration   # интеграционные, требуют MySQL
composer test               # всё вместе
```

### Юнит-тесты
Покрывают framework-agnostic ядро: `WebhookStore`, `SourceTableMap`,
`WebhookSchema`, `TableNameGuard`, `SaveResult`, `Status`, исключения.
Репозиторий заменяется моком `WebhookRepositoryInterface`. БД не нужна.

### Интеграционные тесты `PdoMysqlWebhookRepository`
Поднимают реальные `CREATE TABLE` / `INSERT` / `UPDATE` на MySQL. Каждый
тест создаёт уникальную таблицу `__wh_test_<random>` и дропает её в
`tearDown()`. Если БД недоступна, эти тесты автоматически пропускаются —
`composer test:unit` зелёный без MySQL.

Подготовка БД (один раз):

```bash
composer db:test:setup
```

Скрипт читает `WH_TEST_DSN`, подключается к серверу без указания БД
и делает `CREATE DATABASE IF NOT EXISTS` с `utf8mb4 / utf8mb4_unicode_ci`.
Безопасно запускать повторно.

Альтернатива — создать БД руками:

```sql
CREATE DATABASE webhook_storage_test
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
```

Параметры подключения берутся из переменных окружения. Приоритет такой:
реальный `export` в шелле → файл `.env` в корне пакета (читает
`tests/bootstrap.php`) → ничего.

| Переменная     | Назначение                                                                  |
|----------------|-----------------------------------------------------------------------------|
| `WH_TEST_DSN`  | PDO DSN, например `mysql:host=127.0.0.1;port=3306;dbname=webhook_storage_test;charset=utf8mb4` |
| `WH_TEST_USER` | пользователь MySQL                                                          |
| `WH_TEST_PASS` | пароль                                                                      |

Пример `.env` в корне пакета (файл в `.gitignore`):

```env
WH_TEST_DSN="mysql:host=127.0.0.1;port=3306;dbname=webhook_storage_test;charset=utf8mb4"
WH_TEST_USER="root"
WH_TEST_PASS=""
```

Полный цикл первой настройки:

```bash
composer install
composer db:test:setup
composer test
```

Изолированный прогон, который не оставляет следов на сервере MySQL:

```bash
composer test:ci
# = db:test:setup → test → db:test:drop
```

Если тест упал — `db:test:drop` намеренно **не** выполняется, чтобы можно
было залезть в БД и посмотреть, что не так. Удалить вручную:

```bash
composer db:test:drop
```

Скрипт удаления отказывается дропать БД, имя которой не содержит `test`
и не начинается с `__` — защита от случайной подмены DSN.

## Структура

```
src/
  WebhookStore.php                   фасад (публичный API)
  SaveResult.php                     DTO
  Status.php                         enum статусов
  SourceTableMap.php                 source -> table
  WebhookRepositoryInterface.php     контракт хранилища
  Repository/
    PdoMysqlWebhookRepository.php    реализация на \PDO MySQL
  Schema/
    WebhookSchema.php                генератор CREATE/DROP TABLE SQL
  Exception/
    WebhookStorageException.php
    UnknownSourceException.php
  Internal/
    TableNameGuard.php               валидация имени таблицы
tests/
  Unit/                              юнит-тесты (без БД)
  Integration/                       интеграционные тесты (нужен MySQL)
phpunit.xml.dist
```
