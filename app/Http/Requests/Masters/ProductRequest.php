<?php

namespace App\Http\Requests\Masters;

use App\Models\ProductIncentive;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared rules for creating and updating a product.
 *
 * Store and Update differ in exactly two ways — the permission checked, and
 * whether the uniqueness rules ignore the current row — so those are the two
 * things the subclasses supply.
 */
abstract class ProductRequest extends FormRequest
{
    abstract protected function permission(): string;

    /**
     * Primary key to exclude from the unique checks; null when creating.
     */
    abstract protected function ignoreId(): ?int;

    public function authorize(): bool
    {
        return $this->user()->can($this->permission());
    }

    /**
     * A code is compared case-insensitively by MySQL's default collation, so
     * "abc" and "ABC" already collide — upper-casing it here means the stored
     * value matches what the live check showed. Same treatment as Buyer and
     * Agent display codes.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('item_group_code')) {
            $this->merge(['item_group_code' => strtoupper(trim($this->string('item_group_code')->toString()))]);
        }
    }

    /**
     * Uniqueness does not exclude soft-deleted rows, matching the database
     * index — see StoreCategoryRequest for why.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ignore = $this->ignoreId();

        $rules = [
            'category_id'             => ['required', 'integer', Rule::exists('categories', 'id')],
            // Max 5 characters, letters and numbers only — same convention as
            // Buyer and Agent display codes.
            'item_group_code'         => ['required', 'string', 'max:5', 'regex:/^[A-Z0-9]+$/', Rule::unique('products', 'item_group_code')->ignore($ignore)],
            'name'                    => ['required', 'string', 'max:200', Rule::unique('products', 'name')->ignore($ignore)],
            'name_on_export_document' => ['nullable', 'string', 'max:255'],

            // Sheet: "barcode (needs to have text and number allowed)".
            'barcode'                 => ['nullable', 'string', 'max:60'],

            // Typed, not picked — the client cancelled the Unit Master.
            'unit_po'                 => ['nullable', 'string', 'max:20'],
            'unit_export'             => ['nullable', 'string', 'max:20'],

            // HSN is 4, 6 or 8 digits in practice. Kept as a digit string rather
            // than an integer so a leading zero survives.
            'hsn_code'                => ['nullable', 'string', 'regex:/^[0-9]{4,12}$/'],
            'drawback_sr_no'          => ['nullable', 'string', 'max:20'],

            'price_band_id'           => ['nullable', 'integer', Rule::exists('price_bands', 'id')],
            'gst_rate_id'             => ['nullable', 'integer', Rule::exists('gst_rates', 'id')],

            'fabric_length_mtr'       => ['nullable', 'numeric', 'min:0', 'max:99999.999'],
            'fabric_width_inch'       => ['nullable', 'numeric', 'min:0', 'max:99999.999'],

            'description'             => ['nullable', 'string', 'max:1000'],
            'status'                  => ['required', Rule::in(['active', 'inactive'])],
            'remarks'                 => ['nullable', 'string', 'max:1000'],
            'comments'                => ['nullable', 'string', 'max:1000'],

            'incentives'              => ['nullable', 'array'],

            // Task 13 — free-text BOM; empty list allowed.
            'bom'                     => ['nullable', 'array', 'max:100'],
            'bom.*.component_name'    => ['nullable', 'string', 'max:200'],
            'bom.*.qty'               => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
            'bom.*.unit'              => ['nullable', 'string', 'max:20'],
            'bom.*.is_custom'         => ['nullable', 'boolean'],
            'bom.*.remarks'           => ['nullable', 'string', 'max:500'],
        ];

        foreach (array_keys(ProductIncentive::SCHEMES) as $scheme) {
            $rules["incentives.{$scheme}.percent_1"] = ['nullable', 'numeric', 'min:0', 'max:100'];
            $rules["incentives.{$scheme}.cap_value"] = ['nullable', 'numeric', 'min:0'];

            // A percentage with nothing to apply it to cannot be computed at
            // shipment time, so the basis is required as soon as a rate is given.
            $rules["incentives.{$scheme}.calculation_basis_id"] = [
                "required_with:incentives.{$scheme}.percent_1",
                'nullable',
                'integer',
                Rule::exists('calculation_bases', 'id'),
            ];

            // Only RoSCTL is quoted as two percentages and two caps.
            $two = in_array($scheme, ProductIncentive::TWO_PERCENT_SCHEMES, true);
            $rules["incentives.{$scheme}.percent_2"] = $two
                ? ['nullable', 'numeric', 'min:0', 'max:100']
                : ['prohibited'];
            $rules["incentives.{$scheme}.cap_value_2"] = $two
                ? ['nullable', 'numeric', 'min:0']
                : ['prohibited'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [
            'category_id'             => 'category',
            'item_group_code'         => 'item group code',
            'name_on_export_document' => 'export document name',
            'unit_po'                 => 'unit (PO and OC)',
            'unit_export'             => 'unit (export docs)',
            'price_band_id'           => 'price band',
            'gst_rate_id'             => 'GST %',
            'fabric_length_mtr'       => 'fabric length',
            'fabric_width_inch'       => 'fabric width',
        ];

        foreach (ProductIncentive::SCHEMES as $scheme => $label) {
            $attributes["incentives.{$scheme}.percent_1"]            = "{$label} %";
            $attributes["incentives.{$scheme}.percent_2"]            = "{$label} % 2";
            $attributes["incentives.{$scheme}.cap_value"]            = "{$label} cap value";
            $attributes["incentives.{$scheme}.cap_value_2"]          = "{$label} cap value 2";
            $attributes["incentives.{$scheme}.calculation_basis_id"] = "{$label} calculated on";
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_group_code.unique' => 'This item group code is already taken.',
            'item_group_code.max'    => 'Item group code may not be longer than 5 characters.',
            'item_group_code.regex'  => 'Item group code may contain letters and numbers only.',
            'name.unique'            => 'A product with this name already exists.',
            'hsn_code.regex'         => 'HSN code must be 4 to 12 digits.',
        ];
    }
}
