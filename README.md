# Evocms Template Registry

Reusable package for Evolution CMS 3 that generates a registry:

- template -> controller
- template -> view
- template -> TVs

The command writes deterministic generated files (JSON / Markdown / PHP array) so they can be committed and reused by other tools.

## Installation

Inside your project `core` directory:

```bash
php artisan package:installrequire wrkandreev/evocms-template-registry "*"
```

Optional: publish config.

```bash
php artisan vendor:publish --provider="WrkAndreev\EvocmsTemplateRegistry\EvocmsTemplateRegistryServiceProvider" --tag="evocms-template-registry-config"
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
