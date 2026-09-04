<?php

namespace App\Http\Requests\Masters;

use App\Models\Agent;
use App\Models\AgentCommission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared rules for creating and updating an agent.
 *
 * Store and Update differ in exactly two ways — the permission checked, and
 * whether the uniqueness rule ignores the current row — so those are the two
 * things the subclasses supply. Same shape as BuyerRequest and SupplierRequest.
 *
 * The rule set follows the client's prototype (Agent Master → amValidate), not
 * the original sheet: the sheet had seven fields, the prototype has twenty-odd
 * and marks most of them mandatory. See the 000100 expand migration.
 *
 * ## Side-dependent requirements
 *
 * The prototype swaps whole blocks on the agent type, and the schema cannot
 * express that — every column is nullable there so seeds and factories are not
 * held to a form's rules. The conditions live here instead:
 *
 * | Field        | Supplier / Jobber side | Buyer side |
 * |--------------|------------------------|------------|
 * | `gst_number` | required               | must be absent |
 * | `pan_number` | required               | must be absent |
 * | `ifsc_code`  | required               | must be absent |
 * | `swift_code` | must be absent         | required   |
 *
 * "Must be absent" rather than "ignored": a buyer-side agent carrying a stale
 * IFSC from before its type was switched would be paid through the wrong rail.
 * prepareForValidation() nulls the fields that do not apply, so the user is
 * never shown an error for a box the form had already hidden from them.
 */
abstract class AgentRequest extends FormRequest
{
    abstract protected function permission(): string;

    /**
     * Primary key to exclude from the unique check; null when creating.
     */
    abstract protected function ignoreId(): ?int;

    public function authorize(): bool
    {
        return $this->user()->can($this->permission());
    }

    /**
     * Does this agent collect Indian tax and bank details? Buyer-side agents
     * are paid abroad, so they carry a SWIFT code and no GSTIN.
     */
    protected function isDomestic(): bool
    {
        return $this->input('agent_type') !== 'buyer';
    }

    protected function prepareForValidation(): void
    {
        // Codes are compared case-insensitively by MySQL's collation, so "ag01"
        // and "AG01" already collide. Upper-casing means the stored value
        // matches what the duplicate check showed the user.
        foreach (['display_code', 'gst_number', 'pan_number', 'ifsc_code', 'swift_code'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => strtoupper(trim($this->string($field)->toString()))]);
            }
        }

        /*
         * Clear the block that does not apply to this side. Without this, an
         * agent switched from supplier to buyer keeps its GSTIN and IFSC — the
         * form stops showing them, so nobody would ever see the stale values,
         * but a payment run would.
         */
        if ($this->isDomestic()) {
            $this->merge(['swift_code' => null]);
        } else {
            $this->merge([
                'gst_number' => null,
                'pan_number' => null,
                'ifsc_code'  => null,
            ]);
        }

        // A term description only means something when the term is "custom".
        if ($this->input('payment_term') !== 'custom') {
            $this->merge(['payment_term_custom' => null]);
        }

        /*
         * Client: when the supplier pays the commission it is only a deduction
         * on their bill — payment terms and commission % are irrelevant to us.
         * Clear them so a leftover value from a previous "we pay" choice is not
         * saved against a supplier-pays agent.
         */
        if ($this->input('commission_paid_by') === 'supplier') {
            $this->merge([
                'payment_term'         => null,
                'payment_term_custom'  => null,
                'calculation_basis_id' => null,
                'commissions'          => [],
                // Client: supplier-pays agents are only a master record — we do
                // not settle them, so bank details are irrelevant.
                'bank_name'            => null,
                'account_number'       => null,
                'ifsc_code'            => null,
                'swift_code'           => null,
            ]);
        }

        /*
         * Supplier-side commissions are always INR — the prototype hides the
         * currency picker for them. Dropping a posted currency here keeps a
         * hand-crafted request from labelling a domestic commission in USD.
         */
        if ($this->isDomestic() && is_array($this->input('commissions'))) {
            $this->merge([
                'commissions' => array_map(
                    fn ($row) => is_array($row) ? ['currency_id' => null] + $row : $row,
                    $this->input('commissions')
                ),
            ]);
        }
    }

    /**
     * Supplier-pays agents: commission is deducted from the supplier bill, so
     * we do not need our own payment terms or a commission % entry.
     */
    protected function supplierPays(): bool
    {
        return $this->input('commission_paid_by') === 'supplier';
    }

    /**
     * Uniqueness deliberately does NOT exclude soft-deleted rows — the database
     * index does not either, so excluding them here would let validation pass
     * and the insert then fail with a duplicate-key error.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ignore   = $this->ignoreId();
        $domestic = $this->isDomestic();

        return [
            'agent_type'   => ['required', Rule::in(array_keys(Agent::TYPES))],
            'name'         => ['required', 'string', 'max:200'],
            'display_code' => [
                'required', 'string', 'max:5', 'regex:/^[A-Z0-9]+$/',
                Rule::unique('agents', 'display_code')->ignore($ignore),
            ],

            /*
             * The prototype requires at least one category: the agent dropdowns
             * on the Buyer and Supplier forms are filtered by category, and an
             * agent with none would never appear in either of them.
             */
            'categories'   => ['required', 'array', 'min:1'],
            'categories.*' => ['integer', Rule::exists('categories', 'id')],

            // Contact block — all three mandatory on the prototype.
            'phone'   => ['required', 'string', 'max:30'],
            'city'    => ['required', 'string', 'max:80'],
            'address' => ['required', 'string', 'max:255'],

            /*
             * Tax block. Same GSTIN structure and PAN format the Supplier master
             * validates — 2-digit state code, 10-char PAN, entity digit, 'Z',
             * checksum. `prohibited` on the far side rather than `nullable`, so
             * a posted value is an error and not a silent write.
             */
            'gst_number' => $domestic
                ? ['required', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/']
                : ['prohibited'],
            'pan_number' => $domestic
                ? ['required', 'string', 'size:10', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/']
                : ['prohibited'],

            // Bank block — required when we settle the agent; optional/cleared
            // when the supplier pays (record only).
            'bank_name'      => $this->supplierPays()
                ? ['nullable', 'string', 'max:120']
                : ['required', 'string', 'max:120'],
            'account_number' => $this->supplierPays()
                ? ['nullable', 'string', 'max:40']
                : ['required', 'string', 'max:40'],
            'ifsc_code' => $this->supplierPays()
                ? ['nullable', 'string', 'size:11', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/']
                : ($domestic
                    ? ['required', 'string', 'size:11', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/']
                    : ['prohibited']),
            'swift_code' => $this->supplierPays()
                ? ['nullable', 'string', 'min:8', 'max:11', 'regex:/^[A-Z0-9]+$/']
                : ($domestic
                    ? ['prohibited']
                    : ['required', 'string', 'min:8', 'max:11', 'regex:/^[A-Z0-9]+$/']),

            /*
             * Commission. Basis and entries are required when we pay / buyer pays
             * (we need a % to settle). When the supplier pays, the client said
             * both are irrelevant — optional then.
             */
            'calculation_basis_id' => $this->supplierPays()
                ? ['nullable', 'integer', Rule::exists('calculation_bases', 'id')]
                : ['required', 'integer', Rule::exists('calculation_bases', 'id')],

            'commissions'                   => $this->supplierPays()
                ? ['nullable', 'array']
                : ['required', 'array', 'min:1'],
            'commissions.*.commission_type' => [
                'nullable',
                Rule::in(array_keys(AgentCommission::TYPES)),
            ],
            'commissions.*.amount' => $this->supplierPays()
                ? ['nullable', 'numeric', 'min:0', 'max:99999999.9999']
                : ['required', 'numeric', 'min:0', 'max:99999999.9999'],
            'commissions.*.currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')],

            /*
             * Which ledger the commission lands in. No default — guessing "we
             * pay" would put a payable on our books for an agent the supplier
             * actually settles with.
             */
            'commission_paid_by' => ['required', Rule::in(array_keys(Agent::COMMISSION_PAYERS))],

            'payment_term' => $this->supplierPays()
                ? ['nullable', Rule::in(array_keys(Agent::PAYMENT_TERMS))]
                : ['required', Rule::in(array_keys(Agent::PAYMENT_TERMS))],
            'payment_term_custom' => [
                Rule::requiredIf(fn () => ! $this->supplierPays() && $this->input('payment_term') === 'custom'),
                'nullable', 'string', 'max:255',
            ],

            'status'   => ['required', Rule::in(['active', 'inactive'])],
            'remarks'  => ['nullable', 'string', 'max:1000'],
            'comments' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * A GSTIN carries the holder's PAN at characters 3–12. Two fields on one
     * form that must agree is a typo waiting to happen, and the mismatch stays
     * invisible until a return is filed. Same check as the Supplier master.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $gst = $this->input('gst_number');
            $pan = $this->input('pan_number');

            if (blank($gst) || blank($pan) || strlen((string) $gst) !== 15) {
                return;
            }

            if (substr((string) $gst, 2, 10) !== $pan) {
                $validator->errors()->add(
                    'pan_number',
                    'The PAN does not match the one inside the GST number ('.substr((string) $gst, 2, 10).').'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'calculation_basis_id'          => 'commission basis',
            'commission_paid_by'            => 'commission payer',
            'payment_term_custom'           => 'payment term description',
            'gst_number'                    => 'GST number',
            'pan_number'                    => 'PAN number',
            'ifsc_code'                     => 'IFSC code',
            'swift_code'                    => 'SWIFT code',
            'commissions'                   => 'commission entries',
            'commissions.*.amount'          => 'commission amount',
            'commissions.*.commission_type' => 'commission type',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'display_code.unique' => 'This display code is already taken.',
            'display_code.regex'  => 'The display code must contain only letters and numbers.',

            'categories.required'  => 'Choose at least one category — the Buyer and Supplier forms filter agents by it.',
            'commissions.required' => 'Add at least one commission entry.',
            'commissions.min'      => 'Add at least one commission entry.',

            'gst_number.required' => 'A GST number is required for a supplier-side agent.',
            'gst_number.regex'    => 'That is not a valid GSTIN — expected 15 characters, e.g. 33ABCDE1234F1Z5.',
            'gst_number.size'     => 'A GSTIN is exactly 15 characters.',
            'gst_number.prohibited' => 'A buyer-side agent does not carry a GST number.',
            'pan_number.required' => 'A PAN is required for a supplier-side agent.',
            'pan_number.regex'    => 'That is not a valid PAN — expected 10 characters, e.g. ABCDE1234F.',
            'pan_number.size'     => 'A PAN is exactly 10 characters.',
            'pan_number.prohibited' => 'A buyer-side agent does not carry a PAN.',

            'ifsc_code.required'   => 'An IFSC code is required — a supplier-side agent is paid domestically.',
            'ifsc_code.regex'      => 'That is not a valid IFSC code — expected 11 characters, e.g. HDFC0001234.',
            'ifsc_code.size'       => 'An IFSC code is exactly 11 characters.',
            'ifsc_code.prohibited' => 'A buyer-side agent is paid by SWIFT, not IFSC.',

            'swift_code.required'   => 'A SWIFT code is required — a buyer-side agent is paid internationally.',
            'swift_code.prohibited' => 'A supplier-side agent is paid by IFSC, not SWIFT.',

            'payment_term_custom.required' => 'Describe the custom payment term.',
        ];
    }
}
