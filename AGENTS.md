# AGENTS

This file is for AI/code agents working with this repository.

## Goal

`evocms-template-registry` is a package for Evolution CMS 3 CE.

It provides:

- deterministic registry generation from DB (`template -> controller -> view -> TVs`)
- machine-readable outputs for local tooling/LLM context
- HTTP API with access control for reading current entity state from admin-side tools
- manager page to enable/disable API access quickly
- optional manager plugin for auto-regeneration on TV/template changes
- optional ClientSettings extraction (safe, non-required)
- detection of installed related extensions (`ClientSettings`, `multiTV`, `custom tv select`, `templatesedit`)

## Compatibility

- Evolution CMS 3 CE
- PHP 8.1+

## Package install in project

Run inside project `core` directory:

```bash
php artisan package:installrequire wrkandreev/evocms-template-registry "*"
```

Optional config publish:

```bash
php artisan vendor:publish --provider="WrkAndreev\EvocmsTemplateRegistry\EvocmsTemplateRegistryServiceProvider" --tag="evocms-template-registry-config"
```

Published config path:

- `core/custom/config/template-registry.php`

## Main commands

Generate registry files:

```bash
php core/artisan template-registry:generate
```

Options:

- `--output=` output directory
- `--format=json|md|php|all`
- `--strict` fail on missing controller/view

Create/update manager module (shown in CMS Modules menu):

```bash
php core/artisan template-registry:module:install
```

Options:

- `--name="Template Registry API"`
- `--description="Toggle access to Template Registry API"`
- `--disabled`

Remove manager module:

```bash
php core/artisan template-registry:module:uninstall
```

Option:

- `--name="..."` (fallback match by module name)

Install web routes bridge for frontend/runtime API access:

```bash
php core/artisan template-registry:routes:install
```

Remove web routes bridge:

```bash
php core/artisan template-registry:routes:uninstall
```

Create/update auto-regenerate plugin (disabled by default):

```bash
php core/artisan template-registry:plugin:install
```

Options:

- `--enabled` install plugin as enabled
- `--name="Template Registry Auto Generate"`
- `--description="..."`

Remove auto-regenerate plugin:

```bash
php core/artisan template-registry:plugin:uninstall
```

Option:

- `--name="..."` (fallback match by plugin name)

## Generated files

Default output directory:

- if available: `core/custom/packages/Main/generated/registry`
- fallback: `core/storage/app/template-registry/generated/registry`

Resolution rules:

- `--output` option has highest priority
- then `output` from config
- if both empty, first existing parent path from `output_fallbacks` is used

Generated payload files:

- `templates.generated.json`
- `templates.generated.md`
- `templates.generated.php`

Payload always includes `client_settings` object even when ClientSettings module is missing.

## API endpoints

Default prefix: `/api/template-registry`

- `GET /api/template-registry`
- `GET /api/template-registry/templates`
- `GET /api/template-registry/templates/{id}`
- `GET /api/template-registry/tvs`
- `GET /api/template-registry/resources`
- `GET /api/template-registry/resources/{id}`
- `GET /api/template-registry/resources/{id}/children`
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

Optional single-template filter:

- `GET /api/template-registry?template_id=12`

Resource context for local AI/tools:

- `GET /api/template-registry/resource-resolve?url=/path/to/resource`
- `GET /api/template-registry/resource-resolve?resource_id=123`
- `GET /api/template-registry/resource-context?url=/path/to/resource`
- `GET /api/template-registry/resource-context?resource_id=123`

`resources` returns created resources with template meta and key system fields useful for admin/tooling context.
Deleted resources are excluded by default; use `include_deleted=1` when you need the full list including soft-deleted items.

`resource-resolve` returns stable `resource_id` by URL/id with `matched_by` diagnostics.
Use this endpoint first when you only have URL and need reliable resource id.

`resource-context` returns resource meta, matched template, available TVs and current TV values.
Use this endpoint after `resource-resolve` when agent needs exact context for one page/resource.

`pagebuilder-configs` returns parsed PageBuilder config files from `assets/plugins/pagebuilder/config` for remote tooling.
Use `pagebuilder-configs/{name}` when you need one exact block/container/groups config by file name.

If registry cannot be built (for example required tables are missing), API returns controlled JSON error:

- HTTP `503`
- body contains `code = registry_unavailable`

## API access control

Access is protected by middleware:

1. Global enabled/disabled state (toggle in manager module)
2. Optional token bypass for local tools
3. Manager session check (default)
4. Write operations require separate write flag/token or manager session

### Config keys (`core/custom/config/template-registry.php`)

- `api.enabled` default enabled value
- `api.prefix` API route prefix
- `api.middleware` route middleware list
- `api.require_manager` require manager auth (`true` by default)
- `api.access_token` optional token for local tools (header: `X-Template-Registry-Token`)
- `api.write_enabled` enable write API (`false` by default)
- `api.write_access_token` optional write token (header: `X-Template-Registry-Write-Token`)
- `api.regenerate_after_write` rewrite generated registry files after successful write operation
- `api.admin_prefix` module page prefix (default `template-registry-admin`)

### Runtime state file

Manager page writes runtime state here (enabled flag):

- `core/storage/app/template-registry-api-state.json`

If file does not exist, default comes from config (`api.enabled`).

## Optional ClientSettings integration

ClientSettings is optional and must never be treated as required.

Default paths:

- `assets/modules/clientsettings/config`
- `assets/tvs/selector/lib/*.controller.class.php`

Rules:

- Always return `client_settings` in payload.
- If config directory is absent: `client_settings.exists=false`, empty `tabs` and `fields_catalog`.
- Read fields from ClientSettings tab config (`settings` key is primary).
- Enrich fields with current values from `system_settings` using configured prefixes.
- Invalid/broken tab configs must not break generation/API.
- Selector controller enrichment is best-effort. Missing file/controller is non-fatal.

## JSON contract requirement

Registry payload must include:

- `generated_at`, `project`, `templates`, `tv_catalog`, `stats`
- `client_settings` object (always present)
- `system_features` object with installation status for related extensions

`client_settings` shape:

- `exists` (bool)
- `tabs` (array)
- `fields_catalog` (array)
- `stats` with tabs/fields/selector counters

`system_features` shape:

- `client_settings.installed`
- `multitv.installed`
- `custom_tv_select.installed`
- `templatesedit.installed`
- `pagebuilder.installed`
- each item may include `details` with filesystem diagnostics

## Testing

Manual regression checklist: `docs/test-plan.md`

Must cover at least:

- ClientSettings installed
- ClientSettings absent
- selector controllers partially absent
- empty/broken tab configs
- auto-regenerate plugin enabled/disabled behavior

## Manager page for API toggle

Manager route:

- `GET /template-registry-admin/access`

Use this page to switch API on/off, edit token value in `custom/config/template-registry.php`, and manage auto-regenerate plugin state.
The same page also exposes write API enable/token settings.

## Important conventions for agents

- Keep Evolution CMS 3 CE compatibility
- Do not remove API access protection
- Prefer additive changes in config and routes
- Preserve payload structure used by `TemplateRegistryGenerator`
- If you change API contract, update both `README.md` and `AGENTS.md`
- Keep manager module UI aligned with common Evolution CMS style (same visual approach as Commerce module templates).
- When showing updates on a remote site: first commit and push package changes to GitHub, then update package on server via Composer over SSH (do not patch files directly in `vendor`).
