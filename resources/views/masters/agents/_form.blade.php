@props([
    'agent' => null,
    'agentTypes' => [],
    'categories' => [],
    'calculationBases' => [],
    'currencies' => [],
    'commissionPayers' => [],
    'paymentTerms' => [],
    'commissionTypes' => [],
    'banks' => [],
    'cities' => [],
])

@php
    /**
     * Default to supplier: most agents are domestic, and a buyer default left a
     * hidden required SWIFT field that blocked Save after switching type.
     */
    $type     = old('agent_type', $agent?->agent_type ?? 'supplier');
    $domestic = $type !== 'buyer';
    $payer    = old('commission_paid_by', $agent?->commission_paid_by);
    $supplierPays = $payer === 'supplier';

    $commissionRows = old('commissions');

    if ($commissionRows === null) {
        $commissionRows = $agent?->commissions
            ->map(fn ($row) => [
                'commission_type' => $row->commission_type,
                'amount'          => $row->amount,
                'currency_id'     => $row->currency_id,
            ])->all();
    }

    if (blank($commissionRows)) {
        $commissionRows = [['commission_type' => 'percent', 'amount' => '', 'currency_id' => null]];
    }

    $bankValue = old('bank_name', $agent?->bank_name);
    if (filled($bankValue) && ! array_key_exists($bankValue, $banks)) {
        $banks = [$bankValue => $bankValue] + $banks;
    }
@endphp

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <strong>Could not save — please fix the following:</strong>
        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- ==================== IDENTITY ==================== --}}
<x-ui.form-section title="Identity" icon="bi-person-badge"
                   subtitle="The side decides which tax and bank fields apply below.">
    <div class="form-stack">

        <x-ui.select name="agent_type" label="Agent Type" required horizontal
                     :options="$agentTypes" :selected="$type"
                     :placeholder="false"
                     hint="Supplier and jobber side agents are paid in India; buyer side agents are paid abroad." />

        <x-ui.field name="name" label="Agent Name" :value="$agent?->name" required
                    horizontal maxlength="200" placeholder="E.g. John Doe & Co." />

        <div class="row form-line">
            <label for="display_code" class="col-sm-4 col-lg-3 col-form-label fw-semibold">
                Display Code <span class="req">*</span>
            </label>
            <div class="col-sm-8 col-lg-9">
                <input type="text" id="display_code" name="display_code" maxlength="5" required
                       value="{{ old('display_code', $agent?->display_code) }}"
                       class="form-control text-uppercase js-unique-check @error('display_code') is-invalid @enderror"
                       data-field="display_code" placeholder="AGT01" autocomplete="off">
                @error('display_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text js-unique-feedback"></div>
                <div class="form-text">
                    Used in the debit note number —
                    <span class="font-monospace" id="dn-preview">GT/{{ old('display_code', $agent?->display_code) ?: '—' }}/001/25-26</span>
                </div>
            </div>
        </div>

        <div class="row form-line">
            <label for="categories" class="col-sm-4 col-lg-3 col-form-label fw-semibold">
                Categories <span class="req">*</span>
            </label>
            <div class="col-sm-8 col-lg-9">
                <select id="categories" name="categories[]" multiple required data-searchable
                        data-placeholder="Select categories…"
                        class="form-select @error('categories') is-invalid @enderror">
                    @foreach($categories as $id => $name)
                        <option value="{{ $id }}"
                            @selected(in_array($id, old('categories', $agent?->categories->pluck('id')->all() ?? [])))>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
                @error('categories')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">The product categories this agent handles.</div>
            </div>
        </div>

    </div>
</x-ui.form-section>

{{-- ==================== CONTACT ==================== --}}
<x-ui.form-section title="Contact & Address" icon="bi-geo-alt"
                   subtitle="Prints on the commission debit note.">
    <div class="form-stack">
        <x-ui.field name="phone" label="Phone" :value="$agent?->phone" required
                    horizontal maxlength="30" placeholder="+91 98XXX XXXXX" />

        @php
            $cityValue = old('city', $agent?->city);
            $cityOptions = $cities ?? [];
            if (filled($cityValue) && ! array_key_exists($cityValue, $cityOptions)) {
                $cityOptions = [$cityValue => $cityValue] + $cityOptions;
            }
        @endphp
        <div class="row form-line">
            <label for="city" class="col-sm-4 col-lg-3 col-form-label fw-semibold">
                City <span class="req">*</span>
            </label>
            <div class="col-sm-8 col-lg-9">
                <select id="city" name="city" required data-searchable data-allow-create="true"
                        data-placeholder="Search city…"
                        class="form-select @error('city') is-invalid @enderror">
                    <option value="">— Select —</option>
                    @foreach($cityOptions as $value => $label)
                        <option value="{{ $value }}" @selected((string) $cityValue === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('city')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">Pick from the list, or type a city name and press Enter.</div>
            </div>
        </div>

        <x-ui.textarea name="address" label="Full Address" :value="$agent?->address" required
                       horizontal rows="2" maxlength="255"
                       placeholder="Shop / office no., street, area, city, state, pincode"
                       hint="Required — city alone is not enough; enter the full postal address." />
    </div>
</x-ui.form-section>

{{-- ==================== TAX — domestic sides only ==================== --}}
<x-ui.form-section title="Tax Details" icon="bi-receipt" id="tax-section"
                   subtitle="Shown on the debit note. Supplier and jobber side only."
                   :class="$domestic ? '' : 'd-none'">
    <div class="form-stack">
        <x-ui.field name="gst_number" label="GST Number" :value="$agent?->gst_number"
                    :required="$domestic" horizontal maxlength="15"
                    placeholder="27AAAAA0000A1Z5"
                    data-side-required="domestic"
                    hint="15-character GSTIN. The PAN below must match characters 3–12 of it." />

        <x-ui.field name="pan_number" label="PAN Number" :value="$agent?->pan_number"
                    :required="$domestic" horizontal maxlength="10"
                    placeholder="AAAAA0000A"
                    data-side-required="domestic"
                    hint="10-character PAN — used for TDS deduction." />
    </div>
</x-ui.form-section>

{{-- ==================== COMMISSION ==================== --}}
<x-ui.form-section title="Commission" icon="bi-percent"
                   subtitle="Feeds the costing panel, the debit note and the agent commission settlement.">
    <div class="form-stack">

        {{-- Who pays first — Supplier pays hides basis, % entries and payment terms. --}}
        <x-ui.select name="commission_paid_by" label="Who Pays This Commission?" required horizontal
                     :options="$commissionPayers" :selected="$payer"
                     placeholder="— Select —"
                     hint="We pay = our payable, raises a debit note. Supplier pays = deducted from the supplier bill (no % needed here). Buyer pays = deducted from the export invoice." />

        <div id="commission-details-wrap" class="@if($supplierPays) d-none @endif">
            <div id="commission-basis-wrap">
                <x-ui.select name="calculation_basis_id" label="Commission Basis"
                             :required="! $supplierPays" horizontal searchable
                             :options="$calculationBases" :selected="$agent?->calculation_basis_id"
                             placeholder="Search commission basis…"
                             hint="What a percentage entry is a percentage of — typically Net Value for a supplier side agent, FOB Value for a buyer side one." />
            </div>

            <div class="row form-line" id="commission-entries-wrap">
                <label class="col-sm-4 col-lg-3 col-form-label fw-semibold">
                    Commission Entries <span class="req js-commission-req">*</span>
                </label>
                <div class="col-sm-8 col-lg-9">
                    @error('commissions')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width:38%">Type</th>
                                    <th style="width:28%">Amount</th>
                                    <th style="width:28%" class="js-currency-col @if($domestic) d-none @endif">Currency</th>
                                    <th style="width:6%"></th>
                                </tr>
                            </thead>
                            <tbody id="commission-rows">
                                @foreach($commissionRows as $index => $row)
                                    <tr data-commission-row>
                                        <td>
                                            <select name="commissions[{{ $index }}][commission_type]"
                                                    class="form-select form-select-sm @error("commissions.{$index}.commission_type") is-invalid @enderror"
                                                    aria-label="Commission type">
                                                @foreach($commissionTypes as $value => $label)
                                                    <option value="{{ $value }}" @selected(($row['commission_type'] ?? 'percent') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error("commissions.{$index}.commission_type")<div class="cell-error">{{ $message }}</div>@enderror
                                        </td>
                                        <td>
                                            <input type="number" step="0.0001" min="0"
                                                   name="commissions[{{ $index }}][amount]"
                                                   value="{{ $row['amount'] ?? '' }}"
                                                   class="form-control form-control-sm js-commission-amount @error("commissions.{$index}.amount") is-invalid @enderror"
                                                   placeholder="2.5" aria-label="Commission amount"
                                                   @unless($supplierPays) required @endunless>
                                            @error("commissions.{$index}.amount")<div class="cell-error">{{ $message }}</div>@enderror
                                        </td>
                                        <td class="js-currency-col @if($domestic) d-none @endif">
                                            <select name="commissions[{{ $index }}][currency_id]"
                                                    class="form-select form-select-sm @error("commissions.{$index}.currency_id") is-invalid @enderror"
                                                    aria-label="Currency">
                                                <option value="">— Select —</option>
                                                @foreach($currencies as $id => $label)
                                                    <option value="{{ $id }}" @selected((string) ($row['currency_id'] ?? '') === (string) $id)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error("commissions.{$index}.currency_id")<div class="cell-error">{{ $message }}</div>@enderror
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0 js-remove-commission"
                                                    aria-label="Remove entry" title="Remove entry">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="add-commission">
                        <i class="bi bi-plus-lg me-1"></i>Add commission entry
                    </button>
                    <div class="form-text">
                        Not shown when <strong>Supplier pays</strong> (deducted from their bill).
                        Otherwise fill at least one amount. Currency applies to buyer side agents only.
                    </div>

                    <template id="commission-row-template">
                        <tr data-commission-row>
                            <td>
                                <select name="commissions[__INDEX__][commission_type]" class="form-select form-select-sm" aria-label="Commission type">
                                    @foreach($commissionTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.0001" min="0" name="commissions[__INDEX__][amount]"
                                       class="form-control form-control-sm js-commission-amount" placeholder="2.5" aria-label="Commission amount">
                            </td>
                            <td class="js-currency-col">
                                <select name="commissions[__INDEX__][currency_id]" class="form-select form-select-sm" aria-label="Currency">
                                    <option value="">— Select —</option>
                                    @foreach($currencies as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-link text-danger p-0 js-remove-commission" aria-label="Remove entry">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                </div>
            </div>

            <div id="payment-term-wrap">
                <x-ui.select name="payment_term" label="Payment Terms"
                             :required="! $supplierPays" horizontal
                             :options="$paymentTerms" :selected="$agent?->payment_term"
                             placeholder="— Select —"
                             hint="When the commission falls due, and therefore when it can be settled." />

                <div id="payment-term-custom-row" class="@unless(old('payment_term', $agent?->payment_term) === 'custom') d-none @endunless">
                    <x-ui.field name="payment_term_custom" label="Describe the Term"
                                :value="$agent?->payment_term_custom" horizontal maxlength="255"
                                placeholder="e.g. 50% on shipment, balance on buyer payment" />
                </div>
            </div>
        </div>

        <div id="supplier-pays-note" class="alert alert-secondary py-2 px-3 small mb-0 @unless($supplierPays) d-none @endunless">
            <strong>Supplier pays:</strong> this is only an agent master record.
            Commission %, payment terms and bank details are not needed — the amount is deducted from the supplier bill.
        </div>

    </div>
</x-ui.form-section>

{{-- ==================== BANK ==================== --}}
<div id="bank-details-wrap" class="@if($supplierPays) d-none @endif">
<x-ui.form-section title="Bank Details" icon="bi-bank"
                   subtitle="Where the commission is transferred.">
    <div class="form-stack">
        <div class="row form-line">
            <label for="bank_name" class="col-sm-4 col-lg-3 col-form-label fw-semibold">
                Bank Name <span class="req">*</span>
            </label>
            <div class="col-sm-8 col-lg-9">
                <select id="bank_name" name="bank_name" data-searchable data-allow-create="true"
                        data-placeholder="Search or type bank name…"
                        class="form-select @error('bank_name') is-invalid @enderror">
                    <option value="">— Select —</option>
                    @foreach($banks as $value => $label)
                        <option value="{{ $value }}" @selected((string) $bankValue === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('bank_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">Pick from the list, or type a new bank name and press Enter.</div>
            </div>
        </div>

        <x-ui.field name="account_number" label="Account Number" :value="$agent?->account_number"
                    :required="! $supplierPays" horizontal maxlength="40" placeholder="Account number" />

        <div id="ifsc-row" class="@unless($domestic) d-none @endunless">
            <x-ui.field name="ifsc_code" label="IFSC Code" :value="$agent?->ifsc_code"
                        :required="$domestic && ! $supplierPays" horizontal maxlength="11"
                        placeholder="HDFC0001234"
                        data-side-required="domestic"
                        hint="11 characters, e.g. HDFC0001234."
                        class="text-uppercase" />
        </div>

        <div id="swift-row" class="@if($domestic) d-none @endif">
            <x-ui.field name="swift_code" label="SWIFT Code" :value="$agent?->swift_code"
                        :required="! $domestic && ! $supplierPays" horizontal maxlength="11"
                        placeholder="EBILAEAD"
                        data-side-required="foreign"
                        hint="International transfers."
                        class="text-uppercase" />
        </div>
    </div>
</x-ui.form-section>
</div>

{{-- ==================== STATUS ==================== --}}
<x-ui.form-section title="Status & Remarks" icon="bi-toggle-on">
    <div class="form-stack">
        <x-ui.select name="status" label="Status" required horizontal
                     :options="['active' => 'Active', 'inactive' => 'Inactive']"
                     :selected="$agent?->status ?? 'active'"
                     :placeholder="false"
                     hint="An inactive agent stays on existing orders but drops out of the Buyer and Supplier dropdowns." />

        <x-ui.textarea name="remarks" label="Remarks" :value="$agent?->remarks"
                       horizontal rows="2" placeholder="Internal notes — not printed anywhere" />

        <x-ui.textarea name="comments" label="Comments" :value="$agent?->comments"
                       horizontal rows="2" placeholder="Optional comments" />
    </div>
</x-ui.form-section>

<div class="form-actions">
    <button type="submit" class="btn btn-primary px-4">
        <i class="bi bi-check-lg me-1"></i>{{ $agent ? 'Update' : 'Save' }} Agent
    </button>
    <a href="{{ route('masters.agents.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const typeSelect = document.getElementById('agent_type');
    const payerSelect = document.getElementById('commission_paid_by');

    function setRequired(field, on) {
        if (! field) return;
        if (on) field.setAttribute('required', 'required');
        else field.removeAttribute('required');
    }

    function setDisabled(field, on) {
        if (! field) return;
        field.disabled = !! on;
        if (field.tomselect) {
            try {
                on ? field.tomselect.disable() : field.tomselect.enable();
            } catch (e) { /* ignore TomSelect state glitches */ }
        }
    }

    function setWrapEnabled(wrap, enabled) {
        if (! wrap) return;
        wrap.querySelectorAll('input, select, textarea').forEach(function (field) {
            setDisabled(field, ! enabled);
            if (! enabled) setRequired(field, false);
        });
    }

    function toggle(element, visible) {
        if (! element) return;
        element.classList.toggle('d-none', ! visible);

        if (! visible) {
            element.querySelectorAll('input, select, textarea').forEach(function (field) {
                setRequired(field, false);
                try {
                    if (field.tagName === 'SELECT') {
                        field.tomselect ? field.tomselect.clear(true) : (field.value = '');
                    } else {
                        field.value = '';
                    }
                } catch (e) { /* ignore */ }
            });
        }
    }

    function applySide() {
        const domestic = (typeSelect?.value || 'supplier') !== 'buyer';
        const supplierPays = payerSelect?.value === 'supplier';

        toggle(document.getElementById('tax-section'), domestic);
        toggle(document.getElementById('ifsc-row'), domestic);
        toggle(document.getElementById('swift-row'), ! domestic);

        document.querySelectorAll('[data-side-required="domestic"]').forEach(function (field) {
            setRequired(field, domestic && ! supplierPays);
        });
        document.querySelectorAll('[data-side-required="foreign"]').forEach(function (field) {
            setRequired(field, ! domestic && ! supplierPays);
        });

        document.querySelectorAll('.js-currency-col').forEach(function (cell) {
            cell.classList.toggle('d-none', domestic);
            if (domestic) {
                const select = cell.querySelector('select');
                if (select) select.value = '';
            }
        });

        applyPayer();
    }

    function applyPayer() {
        const supplierPays = payerSelect?.value === 'supplier';
        const detailsWrap = document.getElementById('commission-details-wrap');
        const bankWrap = document.getElementById('bank-details-wrap');
        const note = document.getElementById('supplier-pays-note');
        const termSelect = document.getElementById('payment_term');
        const basisSelect = document.getElementById('calculation_basis_id');
        const domestic = (typeSelect?.value || 'supplier') !== 'buyer';

        detailsWrap?.classList.toggle('d-none', supplierPays);
        bankWrap?.classList.toggle('d-none', supplierPays);
        note?.classList.toggle('d-none', ! supplierPays);

        // Disabled controls are skipped by the browser (and HTML5), even if a
        // required attribute is left behind — this is what was blocking Save
        // when Supplier pays left payment/bank fields required but hidden.
        setWrapEnabled(detailsWrap, ! supplierPays);
        setWrapEnabled(bankWrap, ! supplierPays);

        if (! supplierPays) {
            setRequired(termSelect, true);
            setRequired(basisSelect, true);
            document.querySelectorAll('.js-commission-amount').forEach(function (input) {
                setRequired(input, true);
            });

            const account = document.getElementById('account_number');
            const ifsc = document.getElementById('ifsc_code');
            const swift = document.getElementById('swift_code');
            setRequired(account, true);
            setRequired(ifsc, domestic);
            setRequired(swift, ! domestic);
        } else {
            try {
                if (termSelect?.tomselect) termSelect.tomselect.clear(true);
                else if (termSelect) termSelect.value = '';
                if (basisSelect?.tomselect) basisSelect.tomselect.clear(true);
                else if (basisSelect) basisSelect.value = '';
                document.querySelectorAll('.js-commission-amount').forEach(function (input) {
                    input.value = '';
                });
                const customInput = document.querySelector('#payment-term-custom-row input');
                if (customInput) customInput.value = '';
                document.getElementById('payment-term-custom-row')?.classList.add('d-none');

                const bank = document.getElementById('bank_name');
                if (bank?.tomselect) bank.tomselect.clear(true);
                else if (bank) bank.value = '';
                const account = document.getElementById('account_number');
                const ifsc = document.getElementById('ifsc_code');
                const swift = document.getElementById('swift_code');
                if (account) account.value = '';
                if (ifsc) ifsc.value = '';
                if (swift) swift.value = '';
            } catch (e) { /* never block the form */ }
        }
    }

    typeSelect?.addEventListener('change', applySide);
    payerSelect?.addEventListener('change', applyPayer);
    applySide();
    applyPayer();

    // Before submit: never let prep JS abort the POST. TomSelect clear/create
    // has thrown in the past and cancelled the browser submit entirely —
    // which matched live logs: create page loaded, Save clicked, zero POSTs.
    document.querySelectorAll('form.js-agent-form').forEach(function (form) {
        form.addEventListener('submit', function () {
            try {
                applySide();
                applyPayer();

                form.querySelectorAll('.d-none [required]').forEach(function (field) {
                    setRequired(field, false);
                });
                form.querySelectorAll('.d-none input, .d-none select, .d-none textarea').forEach(function (field) {
                    setDisabled(field, true);
                });

                const bank = document.getElementById('bank_name');
                if (bank?.tomselect && ! bank.disabled) {
                    const typed = (bank.tomselect.control_input?.value || '').trim();
                    if (typed && ! bank.tomselect.getValue()) {
                        bank.tomselect.addOption({ value: typed, text: typed });
                        bank.tomselect.setValue(typed, true);
                    }
                }

                const city = document.getElementById('city');
                if (city?.tomselect && ! city.disabled) {
                    const typed = (city.tomselect.control_input?.value || '').trim();
                    if (typed && ! city.tomselect.getValue()) {
                        city.tomselect.addOption({ value: typed, text: typed });
                        city.tomselect.setValue(typed, true);
                    }
                }
            } catch (e) {
                console.warn('Agent form submit prep failed; continuing save.', e);
            }
        });
    });

    const termSelect = document.getElementById('payment_term');
    const customRow  = document.getElementById('payment-term-custom-row');

    function applyTerm() {
        const custom = termSelect?.value === 'custom';
        customRow?.classList.toggle('d-none', ! custom);
        if (! custom) {
            const input = customRow?.querySelector('input');
            if (input) input.value = '';
        }
    }

    termSelect?.addEventListener('change', applyTerm);
    applyTerm();

    const rows     = document.getElementById('commission-rows');
    const template = document.getElementById('commission-row-template');

    function reindex() {
        rows.querySelectorAll('[data-commission-row]').forEach(function (row, index) {
            row.querySelectorAll('[name^="commissions"]').forEach(function (field) {
                field.name = field.name.replace(/commissions\[\d+\]/, 'commissions[' + index + ']');
            });
        });
    }

    rows.addEventListener('click', function (event) {
        const remove = event.target.closest('.js-remove-commission');
        if (! remove) return;

        const all = rows.querySelectorAll('[data-commission-row]');
        if (all.length === 1) {
            all[0].querySelector('input').value = '';
            all[0].querySelectorAll('select').forEach(function (select, i) {
                select.value = i === 0 ? 'percent' : '';
            });
        } else {
            remove.closest('[data-commission-row]').remove();
        }
        reindex();
        applyPayer();
    });

    document.getElementById('add-commission').addEventListener('click', function () {
        const index = rows.querySelectorAll('[data-commission-row]').length;
        const row   = template.content.cloneNode(true).querySelector('tr');
        row.querySelectorAll('[name*="__INDEX__"]').forEach(function (field) {
            field.name = field.name.replace('__INDEX__', index);
        });
        rows.appendChild(row);
        applySide();
        applyPayer();
        row.querySelector('input')?.focus();
    });

    const checkUrl = @json(route('masters.agents.check-code'));
    const ignoreId = @json($agent?->id);
    const original = new Map();

    document.querySelectorAll('.js-unique-check').forEach(function (input) {
        original.set(input, input.value.trim().toUpperCase());
        const feedback = input.parentElement.querySelector('.js-unique-feedback');
        const preview  = document.getElementById('dn-preview');
        let timer = null;

        function clearFeedback() {
            feedback.textContent = '';
            feedback.className = 'form-text js-unique-feedback';
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            const value = input.value.trim().toUpperCase();
            if (preview) preview.textContent = 'GT/' + (value || '—') + '/001/25-26';

            if (value === '' || value === original.get(input)) {
                clearFeedback();
                input.classList.remove('is-invalid', 'is-valid');
                return;
            }

            timer = setTimeout(function () {
                const params = new URLSearchParams({ field: input.dataset.field, value: value });
                if (ignoreId) params.append('ignore', ignoreId);
                fetch(checkUrl + '?' + params, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.ok ? r.json() : Promise.reject())
                    .then(function (data) {
                        input.classList.toggle('is-invalid', !data.available);
                        input.classList.toggle('is-valid', data.available);
                        feedback.textContent = data.available ? 'Available.' : 'Already taken — choose another.';
                        feedback.className = 'form-text js-unique-feedback ' + (data.available ? 'text-success' : 'text-danger');
                    })
                    .catch(clearFeedback);
            }, 350);
        });
    });
});
</script>
@endpush
