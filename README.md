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
- `stats` summary (`missing_*`, `unique_tvs`, etc.)

## Config

Config file: `config/template-registry.php`.

Main settings:

- output defaults (`output`, `format`, `strict`)
- table names (`site_templates`, `site_tmplvar_templates`, `site_tmplvars`)
- fallback conventions for controller and view
- controller namespace/path mapping for file resolution
- API options (`api.enabled`, `api.prefix`, `api.middleware`, `api.require_manager`, `api.access_token`, `api.admin_prefix`)

## Compatibility

The package targets Evolution CMS 3 CE.
