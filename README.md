# Evocms Template Registry

Переиспользуемый пакет для Evolution CMS 3, который:

- генерирует детерминированный реестр `template -> controller -> view -> TVs`
- отдает текущее состояние шаблонов, TVs, ресурсов и связанных данных через HTTP API
- поддерживает защищенный write API для шаблонов, TVs, ресурсов и их связей
- добавляет manager module для управления доступом к API
- добавляет optional plugin для автогенерации реестра при изменениях в админке
- поддерживает declarative content migrations для переносимых project-level изменений

Пакет формирует детерминированные выходные файлы (JSON / Markdown / PHP array), чтобы их можно было коммитить и использовать в других инструментах, и при этом дает отдельный слой API и migrations для работы с текущим состоянием проекта.

## Заметка по продукту

Пакет больше не ограничивается snapshot generation.

Он совмещает три сценария:

- registry generation для machine-readable snapshot-файлов
- read/write API для админских инструментов и локальных агентов
- content migrations для переносимых изменений между инстансами

Из-за этого пакет полезен и как инструмент документации текущей структуры проекта, и как безопасный automation layer для Evolution CMS.

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

Важно:

- Для работы web API на реальном сайте пакет должен лежать в `core/vendor` как обычные файлы, не как symlink.
- Если пакет установлен через `path` repository для тестов, используйте `"options": {"symlink": false}`.
- На некоторых окружениях symlink-вариант может ломать `core/custom/routes.php` и приводить к `500` в web-runtime.
- На свежих установках Evolution CMS 3 CE `php artisan package:discover` может не зарегистрировать service provider пакета, если пакет добавлен в `core/composer.json`, потому что discovery в этом окружении сканирует `core/custom/composer.json` и `vendor/*/composer.json` через него.
- Если после установки нет команд `template-registry:*`, создайте файл `core/custom/config/app/providers/EvocmsTemplateRegistryServiceProvider.php` со строкой `return WrkAndreev\EvocmsTemplateRegistry\EvocmsTemplateRegistryServiceProvider::class;`, затем повторите `vendor:publish` и команды `template-registry:*`.

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

Создать migration-файл для переносимых content-изменений:

```bash
php core/artisan template-registry:migrate:make CreateT1Assets
```

Применить content migrations:

```bash
php core/artisan template-registry:migrate
```

Dry run без изменений в БД:

```bash
php core/artisan template-registry:migrate --dry-run
```

Посмотреть статус content migrations:

```bash
php core/artisan template-registry:migrate:status
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

Рекомендуемый порядок для нового инстанса:

1. Установить пакет через Composer так, чтобы он оказался в `core/vendor` без symlink.
2. Проверить, что появились команды `template-registry:*` в `php artisan list`.
3. Если команд нет, создать `core/custom/config/app/providers/EvocmsTemplateRegistryServiceProvider.php` с `return WrkAndreev\EvocmsTemplateRegistry\EvocmsTemplateRegistryServiceProvider::class;`.
4. Выполнить `php artisan vendor:publish --provider="WrkAndreev\EvocmsTemplateRegistry\EvocmsTemplateRegistryServiceProvider" --tag="evocms-template-registry-config"`.
5. Выполнить `php core/artisan template-registry:routes:install`.
6. Выполнить `php core/artisan template-registry:module:install`.
7. При необходимости выполнить `php core/artisan template-registry:plugin:install` или `php core/artisan template-registry:plugin:install --enabled`.
8. При необходимости включить write API в `custom/config/template-registry.php` или через manager module.
9. Проверить `GET /api/template-registry` и `GET /api/template-registry/templates`.

Решение по content migrations:

- migration engine живет в пакете как dependency
- сами migration files живут в проекте, а не в пакете и не в `vendor`
- default path: `core/custom/template-registry/migrations`
- это project-level директория, чтобы migrations можно было хранить в git проекта независимо от способа установки пакета

## Content Migrations

Для переносимых изменений между инстансами используйте declarative migrations, а не raw DB ids.

Путь по умолчанию:

- `core/custom/template-registry/migrations`

Состояние applied migrations хранится в таблице:

- `template_registry_migrations`

Рекомендуемые стабильные ключи:

- templates: `alias`
- TVs: `name`
- resources: `path` или `alias + parent`

Пример migration-файла:

```php
<?php

declare(strict_types=1);

return [
    'name' => '2026_04_13_130000_create_t1_assets',
    'description' => 'Create template, TVs and one resource',
    'operations' => [
        [
            'op' => 'upsert_template',
            'match' => ['alias' => 't1'],
            'data' => [
                'name' => 'T1',
                'alias' => 't1',
            ],
        ],
        [
            'op' => 'upsert_tv',
            'match' => ['name' => 'tv_t1_img'],
            'data' => [
                'name' => 'tv_t1_img',
                'caption' => 'TV T1 image',
                'type' => 'image',
            ],
        ],
        [
            'op' => 'attach_tv_to_template',
            'template' => ['alias' => 't1'],
            'tv' => ['name' => 'tv_t1_img'],
            'rank' => 0,
        ],
        [
            'op' => 'upsert_resource',
            'match' => ['alias' => 'resurs-1', 'parent' => ['id' => 0]],
            'data' => [
                'pagetitle' => 'Ресурс 1',
                'alias' => 'resurs-1',
                'template' => ['alias' => 't1'],
                'parent' => 0,
                'published' => true,
            ],
        ],
    ],
];
```

Поддерживаемые операции v1:

- `upsert_template`
- `update_template`
- `delete_template`
- `upsert_tv`
- `update_tv`
- `delete_tv`
- `attach_tv_to_template`
- `detach_tv_from_template`
- `upsert_resource`
- `update_resource`
- `set_resource_template`
- `set_resource_published`
- `set_resource_tv_value`
- `delete_resource`
- `restore_resource`

Migration engine идемпотентен на уровне файла:

- уже применённый файл с тем же checksum будет пропущен
- если checksum applied migration изменился, команда завершится с ошибкой

### Error codes

Write API errors now return machine-readable `code` together with `message`, for example:

```json
{
  "ok": false,
  "code": "template_not_found",
  "message": "Template not found."
}
```

Migration command failures print the same code in CLI output, for example:

```text
[migration_checksum_changed] Migration checksum changed after apply: ...
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
- `GET /api/template-registry/tvs` полный каталог TV из системы, включая TV без привязки к шаблонам
- `GET /api/template-registry/client-settings` текущая runtime-схема и значения ClientSettings
- `GET /api/template-registry/resources` список ресурсов с template meta и основными системными полями
  По умолчанию удалённые ресурсы скрыты. Для полного списка используйте `include_deleted=1`.
  По умолчанию выдача ограничена `100` записями. Поддерживаются `limit`, `per_page`, `all=1` и `include_meta=1`.
- `GET /api/template-registry/resources/{id}` один ресурс по id
- `GET /api/template-registry/resources/{id}/children` дети ресурса по id родителя
- `GET /api/template-registry/stats` только статистика
- `GET /api/template-registry/resource-resolve` быстрый резолв `resource_id` по URL или id
- `GET /api/template-registry/resource-context` контекст ресурс/шаблон/TV по URL или id
- `GET /api/template-registry/blang` конфигурация `bLang`, поля и связи с шаблонами
- `GET /api/template-registry/blang/lexicon` записи словаря `bLang`
- `GET /api/template-registry/pagebuilder-configs` список PageBuilder-конфигов
- `GET /api/template-registry/pagebuilder-configs/{name}` один PageBuilder-конфиг по имени
- `POST /api/template-registry/templates` создать шаблон
- `PATCH /api/template-registry/templates/{templateId}` обновить шаблон
- `DELETE /api/template-registry/templates/{templateId}` удалить шаблон (если не используется ресурсами)
- `POST /api/template-registry/tvs` создать TV
- `PATCH /api/template-registry/tvs/{tvId}` обновить TV
- `DELETE /api/template-registry/tvs/{tvId}` удалить TV вместе со связями и значениями
- `PATCH /api/template-registry/client-settings` обновить значения ClientSettings только для полей из текущей runtime-схемы
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
- `GET /api/template-registry/resources?per_page=500`
- `GET /api/template-registry/resources?all=1`
- `GET /api/template-registry/resources?include_meta=1`
- `GET /api/template-registry/resources?include_deleted=1`
- `GET /api/template-registry/resources/7`
- `GET /api/template-registry/resources/7/children`
- `GET /api/template-registry/resources/7/children?include_meta=1`
- `GET /api/template-registry/resource-resolve?url=/kontakty.html`
- `GET /api/template-registry/resource-resolve?resource_id=123`
- `GET /api/template-registry/resource-context?url=/catalog/iphone-15`
- `GET /api/template-registry/resource-context?resource_id=123`

`GET /api/template-registry/resources` и `GET /api/template-registry/resources/{id}/children` поддерживают:

- `limit` лимит записей, максимум `500`
- `per_page` алиас для `limit`
- `all=1` вернуть все записи без ручного расчета лимита
- `include_deleted=1` включить soft-deleted ресурсы
- `include_meta=1` вернуть объект `{items, meta}` вместо голого массива

Если `include_meta` не указан, ответ остается массивом как раньше, но дополнительно отдаются headers:

- `X-Template-Registry-Total`
- `X-Template-Registry-Returned`
- `X-Template-Registry-Limit`
- `X-Template-Registry-Has-More`

`GET /api/template-registry/client-settings` возвращает нормализованную runtime-схему ClientSettings с вычисленным `setting_name`, `resolved_prefix`, `writable` и текущими значениями.

`PATCH /api/template-registry/client-settings` принимает только поля из `client_settings.fields_catalog`.

Пример:

```json
{
  "values": {
    "phone": "+7 999 000-00-00",
    "email": "hello@example.com",
    "footer_text": "Новый текст в футере"
  }
}
```

Сервис сам:

- находит точный `setting_name` для поля
- пишет значение в `system_settings`
- повторяет события `OnBeforeClientSettingsSave`, `OnDocFormSave`, `OnClientSettingsSave`
- очищает cache

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
- секцию `blang` с языками, suffixes и bLang-полями шаблона ресурса
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
- `tv_catalog[]` для полного каталога TV из системы, включая TV без template links
- `client_settings` присутствует всегда (объект; данные модуля опциональны)
- `blang` присутствует всегда (объект; данные модуля опциональны)
- `system_features` показывает наличие связанных модулей/расширений
- отдельный API endpoint доступен для удаленного чтения PageBuilder-конфигов
- `stats` сводная статистика (`missing_*`, `unique_tvs` и т.д.)

### JSON-контракт (schema-like)

`client_settings` присутствует всегда, даже если модуль ClientSettings не установлен.

`blang` также присутствует всегда. Если `bLang` не установлен или его таблицы недоступны, объект остается валидным с `exists=false` и пустыми коллекциями.

```json
{
  "generated_at": "2026-03-13T10:00:00+00:00",
  "project": "example.local",
  "templates": [],
  "tv_catalog": [],
  "blang": {
    "exists": false,
    "languages": [],
    "default_language": "",
    "suffixes": {},
    "settings": {
      "auto_fields": false,
      "auto_url": false,
      "client_settings_prefix": "",
      "menu_controller_fields": [],
      "content_controller_fields": [],
      "default_to_new_tab": false,
      "pb_show_btn": false,
      "pb_is_te3": false,
      "pb_config": "",
      "translate": false,
      "translate_provider": ""
    },
    "fields_catalog": [],
    "template_links": [],
    "stats": {
      "settings_table_exists": false,
      "fields_table_exists": false,
      "template_links_table_exists": false,
      "lexicon_table_exists": false,
      "languages_total": 0,
      "fields_total": 0,
      "template_links_total": 0,
      "templates_total": 0,
      "lexicon_entries_total": 0
    }
  },
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
    },
    "simplegallery": {
      "installed": false,
      "details": {
        "plugin_dir_exists": false,
        "plugin_file_exists": false,
        "thumb_plugin_file_exists": false,
        "snippets_dir_exists": false,
        "snippets_count": 0
      }
    },
    "blang": {
      "installed": false,
      "details": {
        "module_path_exists": false,
        "class_file_exists": false,
        "plugin_file_exists": false,
        "snippet_file_exists": false
      }
    }
  },
  "client_settings": {
    "exists": false,
    "resolved_prefix": "client_",
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
      "values_table_exists": false,
      "writable_fields_total": 0,
      "duplicate_field_names_total": 0
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
- Для write API пакет вычисляет `setting_name` по runtime-схеме и разрешает запись только в поля, найденные в `fields_catalog`.
- Если значение поля в `system_settings` ещё не существует, пакет использует вычисленный `resolved_prefix`; при необходимости его можно зафиксировать через `client_settings.write_prefix` в конфиге пакета.
- Для selector-полей (`customtv:selector`) пакет пытается обогатить метаданные контроллера из `assets/tvs/selector/lib/*.controller.class.php`.
- Отсутствующие файлы selector-контроллеров не являются фатальной ошибкой (`controller_exists=false`).

### Детект интеграций проекта

Пакет также определяет наличие связанных частей системы и возвращает это в `system_features`:

- `client_settings`
- `multitv`
- `custom_tv_select`
- `templatesedit`
- `pagebuilder`
- `simplegallery`
- `blang`

Детект строится по файловым сигнатурам проекта и нужен, чтобы AI/инструменты точно понимали, какие расширения реально установлены.

### bLang support

Пакет умеет:

- безопасно определять наличие `bLang` через `system_features.blang`
- читать `blang_settings`, `blang_tmplvars`, `blang_tmplvar_templates`
- возвращать нормализованный `blang` object в основном payload
- отдавать тот же объект через `GET /api/template-registry/blang`
- расширять `resource-context` секцией `blang` для текущего ресурса и его шаблона

На реальных проектах `bLang` влияет не только на наличие файлов, но и на runtime-поведение:

- язык и suffix берутся из `blang_settings`
- переводимые поля описываются в `blang_tmplvars`
- на фронте и в сниппетах часто используются `lang`, `suffix`, language urls и `bLang`-aware DocLister controllers
- возможна связка с ClientSettings, templatesEdit и PageBuilder

`resource-context` для `bLang` возвращает:

- языки и suffixes
- subset настроек `bLang`
- `template_fields` для шаблона текущего ресурса
- для каждого bLang-поля: `base_resource_value`, `localized_names` и доступные `resource_values`

Для словаря `bLang` доступен отдельный API:

- `POST /api/template-registry/blang/fields`
- `PATCH /api/template-registry/blang/fields/{fieldId}`
- `DELETE /api/template-registry/blang/fields/{fieldId}`
- `PATCH /api/template-registry/blang/settings`
- `DELETE /api/template-registry/blang/languages/{language}`
- `GET /api/template-registry/blang/lexicon`
- `GET /api/template-registry/blang/health`
- `POST /api/template-registry/blang/lexicon`
- `PATCH /api/template-registry/blang/lexicon/{entryId}`
- `DELETE /api/template-registry/blang/lexicon/{entryId}`
- `POST /api/template-registry/blang/default-params`
- `POST /api/template-registry/blang/fix-template-links`

Write-contract для `bLang`-поля:

```json
{
  "name": "missionTitle",
  "caption": "Наша миссия: Заголовок",
  "type": "textareamini",
  "tab": "mission.[lang]",
  "category": "main",
  "template_ids": [8]
}
```

Что делает API поля `bLang`:

- создает или обновляет запись в `blang_tmplvars`
- сохраняет привязки шаблонов в `blang_tmplvar_templates`
- синхронизирует реальные локализованные TV в `site_tmplvars`
- при rename поля переименовывает связанные локализованные TV
- при delete удаляет `bLang`-поле, его template links и производные локализованные TV

Write-contract для настроек `bLang`:

```json
{
  "languages": ["ru", "en"],
  "suffixes": {
    "ru": "",
    "en": "_en"
  },
  "default": "ru",
  "autoFields": true,
  "autoUrl": true,
  "default_to_new_tab": false,
  "menu_controller_fields": ["pagetitle", "menutitle"],
  "content_controller_fields": ["pagetitle", "menutitle", "introtext", "longtitle", "description"],
  "clientSettingsPrefix": "client_",
  "translate": false,
  "translate_provider": "",
  "pb_show_btn": false,
  "pb_is_te3": false,
  "pb_config": ""
}
```

Что делает `PATCH /api/template-registry/blang/settings`:

- обновляет записи в `blang_settings`
- при добавлении новых языков добавляет недостающие колонки в таблицу `blang`
- после изменения `languages` или `suffixes` пересинхронизирует локализованные TV из `blang_tmplvars`

Важно: endpoint зеркалит manager settings, но не делает destructive schema changes и не удаляет старые языковые колонки из `blang`.

Удаление языка `bLang` делается отдельной операцией:

```json
{
  "new_default": "en"
}
```

Используйте `DELETE /api/template-registry/blang/languages/{language}` только когда язык действительно нужно убрать из active model.
Endpoint обновляет `languages`, `suffixes` и `default` в `blang_settings`, затем пересинхронизирует локализованные TVs.
Он не удаляет старые языковые колонки словаря `blang` из БД.

Write-contract для словаря:

```json
{
  "name": "cta_submit",
  "values": {
    "ru": "Отправить",
    "en": "Submit"
  }
}
```

`name` обязателен. `values` принимает переводы по language keys из `blang.languages`. Также можно передавать языковые ключи плоско в теле запроса (`ru`, `en`) без вложенного `values`.

API для кнопки `Добавить стандартные параметры`:

```json
{
  "attach_all_templates": true
}
```

Что делает этот endpoint:

- добавляет отсутствующие записи из стандартного набора в `blang_tmplvars`
- при `attach_all_templates=true` привязывает их ко всем шаблонам в `blang_tmplvar_templates`
- затем синхронизирует реальные `site_tmplvars` из `bLang`-описаний

Важно: `bLang` считает источником истины таблицу `blang_tmplvars`, а не пары TV в `site_tmplvars`.
Если вручную создать `missionTitle` и `missionTitle_en` только как обычные TV, модуль не начнет считать их `bLang`-парой автоматически.
Для корректной пары поле должно существовать в `blang_tmplvars`, после чего `bLang` сам синхронизирует локализованные TV по suffixes.

Write-contract для локализованных значений ресурса:

```json
{
  "values": {
    "pagetitle_en": "About Braver",
    "introtext_en": "Short intro",
    "missionTitle_en": "Mission title"
  }
}
```

`PATCH /api/template-registry/resources/{resourceId}/blang-fields` обновляет:

- локализованные resource columns в `site_content`, если поле существует там физически
- локализованные TVs в `site_tmplvar_contentvalues`, если поле реализовано как `bLang` TV

`GET /api/template-registry/blang/health` показывает drift между:

- `blang_tmplvar_templates`
- `site_tmplvar_templates`

`POST /api/template-registry/blang/fix-template-links` добавляет недостающие `bLang` template links там, где локализованные TVs уже отмечены у шаблона в MODX, а в `bLang` связь отсутствует.

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
- опциональные таблицы/пути `bLang` (`blang.settings_table`, `blang.fields_table`, `blang.template_links_table`, `blang.lexicon_table`, файловые сигнатуры `blang.*`)
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
- `GET /api/template-registry/blang`
- `GET /api/template-registry/blang/lexicon`
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
- `POST /api/template-registry/blang/lexicon`
- `PATCH /api/template-registry/blang/lexicon/{entryId}`
- `DELETE /api/template-registry/blang/lexicon/{entryId}`
- `POST /api/template-registry/blang/default-params`
- `POST /api/template-registry/blang/fields`
- `PATCH /api/template-registry/blang/fields/{fieldId}`
- `DELETE /api/template-registry/blang/fields/{fieldId}`
- `PATCH /api/template-registry/blang/settings`
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
- Для token-доступа можно передавать `X-Template-Registry-Write-Token`.
- Если `write_access_token` совпадает с `access_token`, для write-запросов достаточно любого одного из заголовков: `X-Template-Registry-Write-Token` или `X-Template-Registry-Token`.
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
