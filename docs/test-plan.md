# Template Registry Test Plan

## Scope

Validate safe behavior of template registry generation and API with optional ClientSettings integration.

## Preconditions

- Evolution CMS 3 CE project with package installed
- API module installed (`php core/artisan template-registry:module:install`)
- Endpoint base: `/api/template-registry`

## Cases

1. ClientSettings installed

- Ensure `assets/modules/clientsettings/config` exists with valid tab configs.
- Run `php core/artisan template-registry:generate`.
- Check payload contains `client_settings.exists = true`.
- Check `client_settings.tabs` and `client_settings.fields_catalog` are non-empty.

2. ClientSettings absent

- Temporarily rename/remove `assets/modules/clientsettings/config`.
- Run generation command and call API endpoint.
- Verify no crash and payload includes:
  - `client_settings.exists = false`
  - `client_settings.tabs = []`
  - `client_settings.fields_catalog = []`

3. Selector controllers partially absent

- Add `customtv:selector` fields in clientsettings config.
- Ensure only part of expected controllers exists in `assets/tvs/selector/lib`.
- Generate payload and verify selector metadata per field:
  - existing controller -> `controller_exists = true`
  - missing controller -> `controller_exists = false`
- Verify generation/API still succeed.

4. Empty/broken tab configs

- Add one valid and one invalid tab config (syntax/runtime error or non-array return).
- Run generation command.
- Verify:
  - process does not fatal
  - valid tabs are included
  - invalid tab is marked with `valid=false` and `error`
  - `client_settings.stats.tabs_invalid` increments

5. Missing required template/TV tables

- Point config table names to nonexistent tables (or run on DB without them).
- Run generation command:
  - verify non-zero exit code
  - verify readable error message
- Call API endpoint:
  - verify `503`
  - verify JSON body includes `code = registry_unavailable`

6. Strict vs non-strict mode

- Prepare dataset with missing controller/view.
- Run without `--strict`: verify success and missing flags/stats populated.
- Run with `--strict`: verify non-zero exit code (`2`) and strict error message.
