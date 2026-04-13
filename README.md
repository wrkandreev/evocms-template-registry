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

Установить bridge для web-routes в Evolution CMS:

```bash
php core/artisan template-registry:routes:install
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

Удалить bridge для web-routes:

```bash
php core/artisan template-registry:routes:uninstall
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
  По умолчанию удалённые ресурсы скрыты. Для полного списка используйте `include_deleted=1`.
- `GET /api/template-registry/resources/{id}` один ресурс по id
- `GET /api/template-registry/resources/{id}/children` дети ресурса по id родителя
- `GET /api/template-registry/stats` только статистика
- `GET /api/template-registry/resource-resolve` быстрый резолв `resource_id` по URL или id
- `GET /api/template-registry/resource-context` контекст ресурс/шаблон/TV по URL или id
- `GET /api/template-registry/pagebuilder-configs` список PageBuilder-конфигов
- `GET /api/template-registry/pagebuilder-configs/{name}` один PageBuilder-конфиг по имени
- `POST /api/template-registry/templates` создать шаблон
- `PATCH /api/template-registry/templates/{templateId}` обновить шаблон
- `DELETE /api/template-registry/templates/{templateId}` удалить шаблон (если не используется ресурсами)
- `POST /api/template-registry/tvs` создать TV
- `PATCH /api/template-registry/tvs/{tvId}` обновить TV
- `DELETE /api/template-registry/tvs/{tvId}` удалить TV вместе со связями и значениями
- `POST /api/template-registry/resources` создать ресурс
- `PATCH /api/template-registry/resources/{resourceId}` обновить ресурс
- `DELETE /api/template-registry/resources/{resourceId}` пометить ресурс удалённым
- `PUT /api/template-registry/resources/{resourceId}/restore` восстановить soft-deleted ресурс
- `PUT /api/template-registry/templates/{templateId}/tvs/{tvId}` привязать TV к шаблону
- `DELETE /api/template-registry/templates/{templateId}/tvs/{tvId}` отвязать TV от шаблона
- `PUT /api/template-registry/resources/{resourceId}/template` сменить шаблон ресурса
- `PUT /api/template-registry/resources/{resourceId}/published` опубликовать или снять с публикации ресурс
- `PUT /api/template-registry/resources/{resourceId}/tv-values/{tvId}` сохранить значение TV для ресурса

Опциональные фильтры:

- `GET /api/template-registry?template_id=12` один шаблон через query
- `GET /api/template-registry/resources?limit=100`
- `GET /api/template-registry/resources?include_deleted=1`
- `GET /api/template-registry/resources/7`
- `GET /api/template-registry/resources/7/children`
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
Для write API там же доступны отдельные настройки: `write_enabled` и `write_access_token`.
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
- `system_features` показывает наличие связанных модулей/расширений
- отдельный API endpoint доступен для удаленного чтения PageBuilder-конфигов
- `stats` сводная статистика (`missing_*`, `unique_tvs` и т.д.)

### JSON-контракт (schema-like)

`client_settings` присутствует всегда, даже если модуль ClientSettings не установлен.

```json
{
  "generated_at": "2026-03-13T10:00:00+00:00",
  "project": "example.local",
  "templates": [],
  "tv_catalog": [],
  "system_features": {
    "client_settings": {
      "installed": false,
      "details": {
        "config_dir_exists": false,
        "core_class_exists": false
      }
    },
    "multitv": {
      "installed": false,
      "details": {
        "customtv_file_exists": false,
        "module_file_exists": false,
        "configs_dir_exists": false,
        "configs_count": 0
      }
    },
    "custom_tv_select": {
      "installed": false,
      "details": {
        "customtv_file_exists": false,
        "lib_dir_exists": false,
        "controllers_count": 0
      }
    },
    "templatesedit": {
      "installed": false,
      "details": {
        "plugin_dir_exists": false,
        "plugin_file_exists": false,
        "class_file_exists": false,
        "configs_dir_exists": false
      }
    },
    "pagebuilder": {
      "installed": false,
      "details": {
        "plugin_dir_exists": false,
        "main_file_exists": false,
        "config_dir_exists": false,
        "customtv_file_exists": false,
        "configs_count": 0
      }
    }
  },
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

### Детект интеграций проекта

Пакет также определяет наличие связанных частей системы и возвращает это в `system_features`:

- `client_settings`
- `multitv`
- `custom_tv_select`
- `templatesedit`
- `pagebuilder`

Детект строится по файловым сигнатурам проекта и нужен, чтобы AI/инструменты точно понимали, какие расширения реально установлены.

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
- сигнатуры related extensions (`multitv.*`, `custom_tv_select.*`, `templatesedit.*`)
- сигнатуры и пути PageBuilder (`pagebuilder.*`)
- таблицы для lookup ресурсов (`resources_table`, `tv_values_table`)
- настройки API (`api.enabled`, `api.prefix`, `api.middleware`, `api.require_manager`, `api.access_token`, `api.admin_prefix`)
- настройки write API (`api.write_enabled`, `api.write_access_token`, `api.regenerate_after_write`)

## API endpoints

- `GET /api/template-registry`
- `GET /api/template-registry/templates`
- `GET /api/template-registry/templates/{id}`
- `GET /api/template-registry/tvs`
- `GET /api/template-registry/resources`
- `GET /api/template-registry/stats`
- `GET /api/template-registry/resource-resolve`
- `GET /api/template-registry/resource-context`
- `GET /api/template-registry/pagebuilder-configs`
- `GET /api/template-registry/pagebuilder-configs/{name}`
- `POST /api/template-registry/templates`
- `PATCH /api/template-registry/templates/{templateId}`
- `DELETE /api/template-registry/templates/{templateId}`
- `POST /api/template-registry/tvs`
- `PATCH /api/template-registry/tvs/{tvId}`
- `DELETE /api/template-registry/tvs/{tvId}`
- `POST /api/template-registry/resources`
- `PATCH /api/template-registry/resources/{resourceId}`
- `DELETE /api/template-registry/resources/{resourceId}`
- `PUT /api/template-registry/resources/{resourceId}/restore`
- `PUT /api/template-registry/templates/{templateId}/tvs/{tvId}`
- `DELETE /api/template-registry/templates/{templateId}/tvs/{tvId}`
- `PUT /api/template-registry/resources/{resourceId}/template`
- `PUT /api/template-registry/resources/{resourceId}/published`
- `PUT /api/template-registry/resources/{resourceId}/tv-values/{tvId}`

### PageBuilder configs API

Если на проекте установлен PageBuilder, можно отдельно читать его конфиги через API.

- `GET /api/template-registry/pagebuilder-configs` возвращает список файлов конфигурации, их тип (`block|container|groups`), валидность и полный распарсенный массив `config`.
- `GET /api/template-registry/pagebuilder-configs/{name}` возвращает один конфиг по имени файла без `.php` / `.php.sample`.
- Если директория `assets/plugins/pagebuilder/config` отсутствует, API возвращает валидный ответ с `exists=false` и пустым списком.

### Write API

Write API выключен по умолчанию.

- Для включения выставьте `api.write_enabled=true`.
- Для token-доступа передавайте заголовок `X-Template-Registry-Write-Token`.
- Если `write_access_token` пустой, запись разрешена только из активной manager session.
- После успешной write-операции пакет по умолчанию регенерирует registry files (`api.regenerate_after_write=true`).

Примеры payload:

```json
{
  "name": "Landing Page",
  "alias": "landing-page",
  "controller": "EvolutionCMS\\Main\\Controllers\\LandingPageController",
  "view": "landing-page"
}
```

```json
{
  "name": "hero_title",
  "caption": "Hero title",
  "type": "text",
  "default_text": ""
}
```

```json
{
  "pagetitle": "About us",
  "alias": "about",
  "template_id": 12,
  "parent": 0,
  "published": true,
  "tv_values": {
    "15": "Hero title from API"
  }
}
```

When a resource is created under a parent (`parent > 0`), write API automatically marks that parent as `isfolder=1` so the child is visible in Evolution tree.

```json
{
  "published": true
}
```

```json
{
  "caption": "Updated image",
  "description": "Updated via API"
}
```

```json
{
  "pagetitle": "Updated page title",
  "template_id": 1,
  "tv_values": {
    "15": "Updated value"
  }
}
```

`PUT /resources/{resourceId}/tv-values/{tvId}` now requires the TV to be attached to the resource's current template. Otherwise API returns `422`.

## Совместимость

Пакет ориентирован на Evolution CMS 3 CE v 3.1.30
php 8.3
