# AGENTS

This file is for AI/code agents working with this repository.

## Goal

`evocms-template-registry` is a package for Evolution CMS 3 CE.

It provides:

- deterministic registry generation from DB (`template -> controller -> view -> TVs`)
- machine-readable outputs for local tooling/LLM context
- HTTP API with access control for reading current entity state from admin-side tools
- manager page to enable/disable API access quickly

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

## API endpoints

Default prefix: `/api/template-registry`

- `GET /api/template-registry`
- `GET /api/template-registry/templates`
- `GET /api/template-registry/templates/{id}`
- `GET /api/template-registry/tvs`
- `GET /api/template-registry/stats`

Optional single-template filter:

- `GET /api/template-registry?template_id=12`

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
