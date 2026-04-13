# Template Registry Test Plan

## Scope

Validate safe behavior of template registry generation and API with optional ClientSettings integration.

## Preconditions

- Evolution CMS 3 CE project with package installed
- API module installed (`php core/artisan template-registry:module:install`)
- Web routes bridge installed (`php core/artisan template-registry:routes:install`)
- Endpoint base: `/api/template-registry`

## Cases

1. ClientSettings installed

- Ensure `assets/modules/clientsettings/config` exists with valid tab configs.
- Run `php core/artisan template-registry:generate`.
- Check payload contains `client_settings.exists = true`.
- Check `client_settings.tabs` and `client_settings.fields_catalog` are non-empty.
- Check `fields_with_values` > 0 when values are saved in ClientSettings.

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

7. Resource context endpoint

- Call `GET /api/template-registry/resource-resolve?url=<existing_resource_url_path>`.
- Verify `resource_id` and `matched_by` are returned.
- For html-like URL (`/kontakty.html`) verify resolver returns correct resource via `matched_by=uri_html` or `alias`.
- Call `GET /api/template-registry/resource-context?resource_id=<existing_id>`.
- Verify response contains `resource`, `template`, `tvs_available`, `tv_values`.
- Call `GET /api/template-registry/resource-context?url=<existing_resource_url_path>` and compare resolved resource id.
- Call endpoint with unknown url/id and verify `404` with `code = resource_not_found`.

8. Related system features detection

- Verify payload always contains `system_features` object.
- On project with related extensions installed, verify correct `installed=true` flags for:
  - `client_settings`
  - `multitv`
  - `custom_tv_select`
  - `templatesedit`
  - `pagebuilder`
- Verify `details` diagnostics match filesystem reality (configs dirs, controller count, plugin/class files).

9. Resources endpoint

- Call `GET /api/template-registry/resources`.
- Verify response is a list of created resources with `id`, `pagetitle`, `alias`, `template_id`, `template_name` and system fields like `menuindex`, `introtext`, `published`, `deleted`.
- Call `GET /api/template-registry/resources?limit=1` and verify limit is applied.

10. PageBuilder configs API

- Ensure `assets/plugins/pagebuilder/config` contains one or more valid config files.
- Call `GET /api/template-registry/pagebuilder-configs`.
- Verify response contains:
  - `exists = true`
  - non-empty `configs[]`
  - `kind` values like `block`, `container` or `groups`
  - parsed `config` arrays from files
- Call `GET /api/template-registry/pagebuilder-configs/<existing_name>` and verify one exact config is returned.
- Call endpoint with unknown name and verify `404`.
- Temporarily remove/rename config dir and verify list endpoint returns `exists = false` with empty `configs`.

11. Write API access control

- Open module page and enable `Write API status`.
- Set `write_access_token` and save settings.
- Call a write endpoint without manager session and without token: verify `403`.
- Call the same endpoint with invalid `X-Template-Registry-Write-Token`: verify `403`.
- Call with valid `X-Template-Registry-Write-Token`: verify request is allowed.
- Disable write API and verify all write endpoints return `403`.

12. Write API operations

- Call `POST /api/template-registry/templates` and verify a new row appears in `site_templates`.
- Call `POST /api/template-registry/tvs` and verify a new row appears in `site_tmplvars`.
- Call `PUT /api/template-registry/templates/{templateId}/tvs/{tvId}` and verify row appears in `site_tmplvar_templates`.
- Call `POST /api/template-registry/resources` with `template_id` and verify a new row appears in `site_content`.
- Call `PUT /api/template-registry/resources/{resourceId}/template` and verify `template` changes in `site_content`.
- Call `PUT /api/template-registry/resources/{resourceId}/published` and verify `published`/`publishedon` change in `site_content`.
- Call `PUT /api/template-registry/resources/{resourceId}/tv-values/{tvId}` and verify row appears or updates in `site_tmplvar_contentvalues`.
- Detach a TV from the resource template, then call `PUT /api/template-registry/resources/{resourceId}/tv-values/{tvId}` and verify API returns `422`.
- Call `DELETE /api/template-registry/templates/{templateId}/tvs/{tvId}` and verify link row is removed.
- With `api.regenerate_after_write=true`, verify generated registry files are refreshed after successful writes.

13. Auto-regenerate plugin

- Run `php core/artisan template-registry:plugin:install` and verify plugin is created as disabled.
- Enable plugin (from module page or `template-registry:plugin:install --enabled`).
- Edit and save any TV/template in manager.
- Verify generated registry files in `core/custom/packages/Main/generated/registry` are updated.
- Disable plugin and repeat TV/template save; verify no new regeneration is triggered.
