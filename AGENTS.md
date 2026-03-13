# AGENTS

This file is for AI/code agents working with this repository.

## Goal

`evocms-template-registry` is a package for Evolution CMS 3 CE.

It provides:

- deterministic registry generation from DB (`template -> controller -> view -> TVs`)
- machine-readable outputs for local tooling/LLM context
- HTTP API with access control for reading current entity state from admin-side tools
- manager page to enable/disable API access quickly
- optional ClientSettings extraction (safe, non-required)

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

## Generated files

Default output directory:

- `core/custom/packages/Main/generated/registry`

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
- `GET /api/template-registry/stats`
- `GET /api/template-registry/resource-context`

Optional single-template filter:

- `GET /api/template-registry?template_id=12`

Resource context for local AI/tools:

- `GET /api/template-registry/resource-context?url=/path/to/resource`
- `GET /api/template-registry/resource-context?resource_id=123`

`resource-context` returns resource meta, matched template, available TVs and current TV values.
Use this endpoint when agent needs exact context for one page/resource.

If registry cannot be built (for example required tables are missing), API returns controlled JSON error:

- HTTP `503`
- body contains `code = registry_unavailable`

## API access control

Access is protected by middleware:

1. Global enabled/disabled state (toggle in manager module)
2. Optional token bypass for local tools
3. Manager session check (default)

### Config keys (`config/template-registry.php`)

- `api.enabled` default enabled value
- `api.prefix` API route prefix
- `api.middleware` route middleware list
- `api.require_manager` require manager auth (`true` by default)
- `api.access_token` optional token for local tools (header: `X-Template-Registry-Token`)
- `api.admin_prefix` manager page prefix

### Runtime state file

The manager toggle writes runtime state here:

- `core/storage/app/template-registry-api-state.json`

If file does not exist, default comes from `api.enabled`.

## Optional ClientSettings integration

ClientSettings is optional and must never be treated as required.

Default paths:

- `assets/modules/clientsettings/config`
- `assets/tvs/selector/lib/*.controller.class.php`

Rules:

- Always return `client_settings` in payload.
- If config directory is absent: `client_settings.exists=false`, empty `tabs` and `fields_catalog`.
- Invalid/broken tab configs must not break generation/API.
- Selector controller enrichment is best-effort. Missing file/controller is non-fatal.

## JSON contract requirement

Registry payload must include:

- `generated_at`, `project`, `templates`, `tv_catalog`, `stats`
- `client_settings` object (always present)

`client_settings` shape:

- `exists` (bool)
- `tabs` (array)
- `fields_catalog` (array)
- `stats` with tabs/fields/selector counters

## Testing

Manual regression checklist: `docs/test-plan.md`

Must cover at least:

- ClientSettings installed
- ClientSettings absent
- selector controllers partially absent
- empty/broken tab configs

## Manager page for API toggle

Manager route:

- `GET /manager/template-registry/access`

Use this page to switch API on/off without editing config.

## Important conventions for agents

- Keep Evolution CMS 3 CE compatibility
- Do not remove API access protection
- Prefer additive changes in config and routes
- Preserve payload structure used by `TemplateRegistryGenerator`
- If you change API contract, update both `README.md` and `AGENTS.md`
