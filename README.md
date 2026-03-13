# Evocms Template Registry

Reusable package for Evolution CMS 3 that generates a registry:

- template -> controller
- template -> view
- template -> TVs

The command writes deterministic generated files (JSON / Markdown / PHP array) so they can be committed and reused by other tools.

## Product note

In addition to package output files, the same registry data should be available via API endpoints.
This is needed so admin-side tools can read current entity state and understand how to work with these entities.

## Installation

Inside your project `core` directory:

```bash
php artisan package:installrequire wrkandreev/evocms-template-registry "*"
```

Optional: publish config.

```bash
php artisan vendor:publish --provider="WrkAndreev\EvocmsTemplateRegistry\EvocmsTemplateRegistryServiceProvider" --tag="evocms-template-registry-config"
```

Register manager module (so it appears in CMS Modules menu):

```bash
php core/artisan template-registry:module:install
```

Remove manager module:

```bash
php core/artisan template-registry:module:uninstall
```

## Usage

```bash
php core/artisan template-registry:generate
```

Options:

- `--output=` custom output directory
- `--format=json|md|php|all`
- `--strict` fail when missing controller/view is detected

Example:

```bash
php core/artisan template-registry:generate --output=core/custom/packages/Main/generated/registry --format=all --strict
```

## API

Package also exposes the same registry data over HTTP API (for admin-side tools/agents).

Access is restricted by default:

- manager session is required (`api.require_manager = true`)
- API can be globally switched on/off from manager module
- optional token access for local tools (`X-Template-Registry-Token` header)

Default endpoints:

- `GET /api/template-registry` full payload
- `GET /api/template-registry/templates` templates only
- `GET /api/template-registry/templates/{id}` single template by id
- `GET /api/template-registry/tvs` TV catalog only
- `GET /api/template-registry/stats` stats only

Optional filter:

- `GET /api/template-registry?template_id=12` single template by query

### Manager module (API toggle)

Open module page in manager:

- `GET /manager/template-registry/access`

On this page you can enable/disable API access without editing config manually.

To register this page as manager module item (Modules menu), run:

- `php core/artisan template-registry:module:install`

## Generated structure

- `templates.generated.json`
- `templates.generated.md`
- `templates.generated.php`

Payload fields:

- `templates[]` with `controller`, `view`, `tv_refs`, `flags`
- `tv_catalog[]` for deduplicated TV metadata
- `client_settings` always present (object; optional module data)
- `stats` summary (`missing_*`, `unique_tvs`, etc.)

### JSON contract (schema-like)

`client_settings` is always present even when ClientSettings module is not installed.

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

### Optional ClientSettings integration

ClientSettings is not required.

- If `assets/modules/clientsettings/config` does not exist: payload stays valid and `client_settings.exists=false`.
- Tab configs are loaded safely; invalid/broken files increment `client_settings.stats.tabs_invalid` and do not break API/command.
- For selector fields (`customtv:selector`) package tries to enrich controller metadata from `assets/tvs/selector/lib/*.controller.class.php`.
- Missing selector controller files are non-fatal (`controller_exists=false`).

### API/command failure behavior

- Missing required TV/template tables returns controlled error (no fatal).
- CLI command returns failure code with readable message.
- API returns `503` with `{"code":"registry_unavailable"}` and error message.

## Test plan

Manual regression plan: `docs/test-plan.md`

## Config

Config file: `config/template-registry.php`.

Main settings:

- output defaults (`output`, `format`, `strict`)
- table names (`site_templates`, `site_tmplvar_templates`, `site_tmplvars`)
- fallback conventions for controller and view
- controller namespace/path mapping for file resolution
- optional ClientSettings paths (`client_settings.config_path`, `client_settings.selector_controllers_path`)
- API options (`api.enabled`, `api.prefix`, `api.middleware`, `api.require_manager`, `api.access_token`, `api.admin_prefix`)

## Compatibility

The package targets Evolution CMS 3 CE.
