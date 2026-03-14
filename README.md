# Evocms Template Registry

Переиспользуемый пакет для Evolution CMS 3, который генерирует реестр:

- template -> controller
- template -> view
- template -> TVs

Команда формирует детерминированные выходные файлы (JSON / Markdown / PHP array), чтобы их можно было коммитить и использовать в других инструментах.

## Заметка по продукту

Помимо файлов, которые генерирует пакет, те же данные реестра должны быть доступны через API.
Это нужно, чтобы админские инструменты могли читать текущее состояние сущностей и понимать, как с ними работать.

## Установка

Пакет пока не опубликован в Packagist.
Подключайте его напрямую из GitHub-репозитория.

Пример для `composer.json` проекта:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:wrkandreev/evocms-template-registry.git"
    }
  ]
}
```

Выполнять из директории `core` вашего проекта:

```bash
php artisan package:installrequire wrkandreev/evocms-template-registry "*"
```

Опционально: опубликовать конфиг.

```bash
php artisan vendor:publish --provider="WrkAndreev\EvocmsTemplateRegistry\EvocmsTemplateRegistryServiceProvider" --tag="evocms-template-registry-config"
```

Зарегистрировать модуль менеджера (чтобы появился в меню Modules):

```bash
php core/artisan template-registry:module:install
```

Создать/обновить плагин автогенерации (по умолчанию создается выключенным):

```bash
php core/artisan template-registry:plugin:install
```

Удалить плагин автогенерации:

```bash
php core/artisan template-registry:plugin:uninstall
```

Удалить модуль менеджера:

```bash
php core/artisan template-registry:module:uninstall
```

## Использование

```bash
php core/artisan template-registry:generate
```

Опции:

- `--output=` кастомная директория для вывода
- `--format=json|md|php|all`
- `--strict` завершить с ошибкой при обнаружении отсутствующего controller/view

Если `--output` и `output` в конфиге не заданы, команда выбирает первый доступный путь из `output_fallbacks`:

- `core/custom/packages/Main/generated/registry`
- `core/storage/app/template-registry/generated/registry`

Это позволяет работать даже когда пакет `Main` еще не создан.

Для плагина автогенерации доступны опции:

- `template-registry:plugin:install --enabled` (установить сразу включенным)
- `template-registry:plugin:install --name="..." --description="..."`
- `template-registry:plugin:uninstall --name="..."`

Плагин слушает события менеджера:

- `OnTVFormSave`
- `OnTVFormDelete`
- `OnTempFormSave`
- `OnTempFormDelete`

Пример:

```bash
php core/artisan template-registry:generate --output=core/custom/packages/Main/generated/registry --format=all --strict
```

## API

Пакет также отдает те же данные реестра через HTTP API (для админских инструментов/агентов).

По умолчанию доступ ограничен:

- требуется manager-сессия (`api.require_manager = true`)
- API можно глобально включать/выключать через модуль в менеджере
- опционально можно использовать токен для локальных инструментов (заголовок `X-Template-Registry-Token`)
- токен можно редактировать на странице модуля (значение записывается в `custom/config/template-registry.php`)

Эндпоинты по умолчанию:

- `GET /api/template-registry` полный payload
- `GET /api/template-registry/templates` только шаблоны
- `GET /api/template-registry/templates/{id}` один шаблон по id
- `GET /api/template-registry/tvs` только каталог TV
- `GET /api/template-registry/resources` список ресурсов с template meta и основными системными полями
- `GET /api/template-registry/stats` только статистика
- `GET /api/template-registry/resource-resolve` быстрый резолв `resource_id` по URL или id
- `GET /api/template-registry/resource-context` контекст ресурс/шаблон/TV по URL или id

Опциональные фильтры:

- `GET /api/template-registry?template_id=12` один шаблон через query
- `GET /api/template-registry/resources?limit=100`
- `GET /api/template-registry/resource-resolve?url=/kontakty.html`
- `GET /api/template-registry/resource-resolve?resource_id=123`
- `GET /api/template-registry/resource-context?url=/catalog/iphone-15`
- `GET /api/template-registry/resource-context?resource_id=123`

`resource-resolve` возвращает:

- `resource_id`
- `normalized_url`
- `matched_by` (`id|uri|uri_html|alias|site_start`)
- минимальную информацию о ресурсе (`id`, `pagetitle`, `alias`, `uri`, `template_id`)

Рекомендуемый поток для инструментов/AI:

1. Сначала `resource-resolve` по URL.
2. Потом `resource-context` по найденному `resource_id`.

`resource-context` возвращает:

- мета ресурса (`id`, `pagetitle`, `alias`, `uri`, `template_id`)
- объект шаблона из реестра
- доступные TV для шаблона (`tvs_available`)
- текущие значения TV для этого ресурса (`tv_values`)

### Модуль менеджера (переключение API)

Страница модуля (по умолчанию):

- `GET /template-registry-admin/access`

На этой странице можно включать/выключать доступ к API, менять access token без ручного редактирования конфига и смотреть preview сгенерированных сущностей (templates/TV/resources/ClientSettings).
Там же отображается состояние плагина автогенерации и кнопки его установки/включения/выключения.
Путь можно изменить через `api.admin_prefix`.
Если токен уже задан в `custom/config/template-registry.php`, модуль покажет текущее значение.

Чтобы зарегистрировать эту страницу как пункт модуля (меню Modules), выполните:

- `php core/artisan template-registry:module:install`

## Структура сгенерированных файлов

- `templates.generated.json`
- `templates.generated.md`
- `templates.generated.php`

Поля payload:

- `templates[]` с `controller`, `view`, `tv_refs`, `flags`
- `tv_catalog[]` для дедуплицированного каталога TV
- `client_settings` присутствует всегда (объект; данные модуля опциональны)
- `stats` сводная статистика (`missing_*`, `unique_tvs` и т.д.)

### JSON-контракт (schema-like)

`client_settings` присутствует всегда, даже если модуль ClientSettings не установлен.

```json
{
  "generated_at": "2026-03-13T10:00:00+00:00",
  "project": "example.local",
  "templates": [],
  "tv_catalog": [],
  "client_settings": {
    "exists": false,
    "tabs": [],
    "fields_catalog": [],
    "stats": {
      "tabs_total": 0,
      "tabs_valid": 0,
      "tabs_invalid": 0,
      "fields_total": 0,
      "fields_with_values": 0,
      "selector_fields_total": 0,
      "selector_controllers_found": 0,
      "selector_controllers_missing": 0,
      "selector_controllers_dir_exists": false,
      "values_table_exists": false
    }
  },
  "stats": {
    "templates_total": 0,
    "missing_controllers": 0,
    "missing_views": 0,
    "placeholder_views": 0,
    "total_tvs_links": 0,
    "unique_tvs": 0
  }
}
```

### Опциональная интеграция ClientSettings

ClientSettings не является обязательным.

- Если `assets/modules/clientsettings/config` не существует: payload остается валидным и `client_settings.exists=false`.
- Tab-конфиги загружаются безопасно; невалидные/битые файлы увеличивают `client_settings.stats.tabs_invalid` и не ломают API/команду.
- Пакет поддерживает структуру ClientSettings с ключом `settings` и подхватывает текущие значения из `system_settings`.
- Для selector-полей (`customtv:selector`) пакет пытается обогатить метаданные контроллера из `assets/tvs/selector/lib/*.controller.class.php`.
- Отсутствующие файлы selector-контроллеров не являются фатальной ошибкой (`controller_exists=false`).

### Поведение при ошибках API/команды

- Отсутствие обязательных таблиц TV/template возвращает контролируемую ошибку (без фатала).
- CLI-команда возвращает код ошибки и читаемое сообщение.
- API возвращает `503` с `{"code":"registry_unavailable"}` и текстом ошибки.

## Конфигурация

Файл конфига: `core/custom/config/template-registry.php`.

Основные настройки:

- параметры вывода (`output`, `format`, `strict`)
- fallback-пути вывода (`output_fallbacks`)
- имена таблиц (`site_templates`, `site_tmplvar_templates`, `site_tmplvars`)
- fallback-конвенции для controller и view
- маппинг namespace/path для поиска файлов controller
- опциональные пути/источники ClientSettings (`client_settings.config_path`, `client_settings.selector_controllers_path`, `client_settings.settings_table`, `client_settings.setting_prefixes`)
- таблицы для lookup ресурсов (`resources_table`, `tv_values_table`)
- настройки API (`api.enabled`, `api.prefix`, `api.middleware`, `api.require_manager`, `api.access_token`, `api.admin_prefix`)

## Совместимость

Пакет ориентирован на Evolution CMS 3 CE v 3.1.30
php 8.3
