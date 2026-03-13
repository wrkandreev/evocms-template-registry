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

Эндпоинты по умолчанию:

- `GET /api/template-registry` полный payload
- `GET /api/template-registry/templates` только шаблоны
- `GET /api/template-registry/templates/{id}` один шаблон по id
- `GET /api/template-registry/tvs` только каталог TV
- `GET /api/template-registry/stats` только статистика
- `GET /api/template-registry/resource-context` контекст ресурс/шаблон/TV по URL или id

Опциональные фильтры:

- `GET /api/template-registry?template_id=12` один шаблон через query
- `GET /api/template-registry/resource-context?url=/catalog/iphone-15`
- `GET /api/template-registry/resource-context?resource_id=123`

`resource-context` возвращает:

- мета ресурса (`id`, `pagetitle`, `alias`, `uri`, `template_id`)
- объект шаблона из реестра
- доступные TV для шаблона (`tvs_available`)
- текущие значения TV для этого ресурса (`tv_values`)

### Модуль менеджера (переключение API)

Страница модуля в менеджере:

- `GET /manager/template-registry/access`

На этой странице можно включать/выключать доступ к API без ручного редактирования конфига.

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
      "selector_fields_total": 0,
      "selector_controllers_found": 0,
      "selector_controllers_missing": 0,
      "selector_controllers_dir_exists": false
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
- Для selector-полей (`customtv:selector`) пакет пытается обогатить метаданные контроллера из `assets/tvs/selector/lib/*.controller.class.php`.
- Отсутствующие файлы selector-контроллеров не являются фатальной ошибкой (`controller_exists=false`).

### Поведение при ошибках API/команды

- Отсутствие обязательных таблиц TV/template возвращает контролируемую ошибку (без фатала).
- CLI-команда возвращает код ошибки и читаемое сообщение.
- API возвращает `503` с `{"code":"registry_unavailable"}` и текстом ошибки.

## Тест-план

План ручной регрессии: `docs/test-plan.md`

## Конфигурация

Файл конфига: `config/template-registry.php`.

Основные настройки:

- параметры вывода (`output`, `format`, `strict`)
- имена таблиц (`site_templates`, `site_tmplvar_templates`, `site_tmplvars`)
- fallback-конвенции для controller и view
- маппинг namespace/path для поиска файлов controller
- опциональные пути ClientSettings (`client_settings.config_path`, `client_settings.selector_controllers_path`)
- таблицы для lookup ресурсов (`resources_table`, `tv_values_table`)
- настройки API (`api.enabled`, `api.prefix`, `api.middleware`, `api.require_manager`, `api.access_token`, `api.admin_prefix`)

## Совместимость

Пакет ориентирован на Evolution CMS 3 CE.
