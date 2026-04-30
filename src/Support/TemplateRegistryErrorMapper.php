<?php

declare(strict_types=1);

namespace WrkAndreev\EvocmsTemplateRegistry\Support;

class TemplateRegistryErrorMapper
{
    /** @return array{status:int,code:string} */
    public function map(string $message): array
    {
        $normalized = strtolower($message);

        $rules = [
            ['required field "name" is empty.', 422, 'required_name'],
            ['required field "alias" is empty.', 422, 'required_alias'],
            ['required field "pagetitle" is empty.', 422, 'required_pagetitle'],
            ['template name already exists.', 422, 'template_name_exists'],
            ['template alias already exists.', 422, 'template_alias_exists'],
            ['tv name already exists.', 422, 'tv_name_exists'],
            ['tv type is invalid.', 422, 'invalid_tv_type'],
            ['template is used by existing resources.', 422, 'template_in_use'],
            ['tv is not attached to resource template.', 422, 'tv_not_attached_to_template'],
            ['resource has no valid template for tv assignment.', 422, 'resource_template_missing'],
            ['no template fields provided for update.', 422, 'no_template_fields'],
            ['no tv fields provided for update.', 422, 'no_tv_fields'],
            ['no resource fields provided for update.', 422, 'no_resource_fields'],
            ['resource id must be greater than zero.', 422, 'invalid_resource_id'],
            ['tv id must be greater than zero.', 422, 'invalid_tv_id'],
            ['template id must be greater than zero.', 422, 'invalid_template_id'],
            ['template-tv link not found.', 404, 'template_tv_link_not_found'],
            ['template not found.', 404, 'template_not_found'],
            ['tv not found.', 404, 'tv_not_found'],
            ['resource not found.', 404, 'resource_not_found'],
            ['blang lexicon key already exists.', 422, 'blang_lexicon_key_exists'],
            ['blang field name already exists.', 422, 'blang_field_name_exists'],
            ['blang field name is invalid.', 422, 'blang_field_name_invalid'],
            ['no blang field fields provided for update.', 422, 'no_blang_field_fields'],
            ['no blang field values provided for update.', 422, 'no_blang_field_values'],
            ['no blang lexicon fields provided for update.', 422, 'no_blang_lexicon_fields'],
            ['no blang settings provided for update.', 422, 'no_blang_settings'],
            ['blang lexicon table not found.', 404, 'blang_lexicon_table_not_found'],
            ['blang lexicon entry not found.', 404, 'blang_lexicon_entry_not_found'],
            ['blang field not found.', 404, 'blang_field_not_found'],
            ['blang localized tv not found.', 404, 'blang_localized_tv_not_found'],
            ['blang generated tv name already exists.', 422, 'blang_generated_tv_name_exists'],
            ['blang language is invalid.', 422, 'blang_language_invalid'],
            ['blang language not found.', 404, 'blang_language_not_found'],
            ['cannot remove the last blang language.', 422, 'blang_last_language_remove_forbidden'],
            ['blang replacement default language is invalid.', 422, 'blang_replacement_default_invalid'],
            ['blang languages setting is invalid.', 422, 'blang_languages_invalid'],
            ['blang suffixes setting is invalid.', 422, 'blang_suffixes_invalid'],
            ['blang default language is invalid.', 422, 'blang_default_invalid'],
            ['blang default fields definition not found.', 500, 'blang_default_fields_missing'],
            ['blang default fields definition is empty.', 500, 'blang_default_fields_empty'],
            ['no client settings values provided for update.', 422, 'no_client_settings_values'],
            ['clientsettings config not found.', 404, 'client_settings_config_not_found'],
            ['clientsettings field not found.', 404, 'client_settings_field_not_found'],
            ['clientsettings field is not writable.', 422, 'client_settings_field_not_writable'],
            ['clientsettings setting name could not be resolved.', 422, 'client_settings_setting_name_unresolved'],
            ['clientsettings system settings table not found.', 404, 'client_settings_table_not_found'],
            ['parent resource not found.', 404, 'parent_resource_not_found'],
            ['template reference not found.', 404, 'template_reference_not_found'],
            ['tv reference not found.', 404, 'tv_reference_not_found'],
            ['resource reference not found.', 404, 'resource_reference_not_found'],
            ['migration checksum changed after apply:', 409, 'migration_checksum_changed'],
            ['migration operation missing op.', 422, 'migration_operation_missing'],
            ['unsupported migration operation:', 422, 'migration_operation_unsupported'],
            ['migration file not found:', 404, 'migration_file_not_found'],
            ['migration file must return array:', 422, 'migration_file_invalid'],
            ['migration operations must be array:', 422, 'migration_operations_invalid'],
        ];

        foreach ($rules as [$needle, $status, $code]) {
            if (str_contains($normalized, $needle)) {
                return ['status' => $status, 'code' => $code];
            }
        }

        if (str_contains($normalized, 'not found')) {
            return ['status' => 404, 'code' => 'not_found'];
        }

        if (str_contains($normalized, 'invalid') || str_contains($normalized, 'required') || str_contains($normalized, 'must')) {
            return ['status' => 422, 'code' => 'validation_error'];
        }

        return ['status' => 500, 'code' => 'internal_error'];
    }
}
