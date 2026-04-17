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
