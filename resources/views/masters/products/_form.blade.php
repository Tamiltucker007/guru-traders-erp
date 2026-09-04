@props([
    'product' => null,
    'categories' => [],
    'units' => [],
    'priceBands' => [],
    'gstRates' => [],
    'calculationBases' => [],
])

@php
    use App\Models\ProductIncentive;

    /**
     * Product form — every field here is a column on the client's Product
     * Master sheet, in the sheet's own order. Nothing has been added.
     *
     * Laid out one field per line, label on the left, same as the Category
     * master. 27 fields in a three-column grid made the eye jump around.
     *
     * Reads an incentive field, preferring old input after a failed validation
     * round-trip so a long form is never retyped.
     */
    $incentive = fn (string $scheme, string $field) => old(
        "incentives.{$scheme}.{$field}",
        $product?->incentive($scheme)?->{$field}
    );
@endphp

{{-- ===================== A–E · IDENTIFICATION ===================== --}}
<x-ui.form-section title="Identification" icon="bi-tag"
                   subtitle="What the product is called, here and on export documents.">
    <div class="form-stack">

        {{-- Col A --}}
        <x-ui.select name="category_id" label="Category" required horizontal searchable
                     :options="$categories" :selected="$product?->category_id"
                     placeholder="Search category…" />

        {{-- Col B — "incase a code is already taken I should get an alert here" --}}
        <div class="row form-line">
            <label for="item_group_code" class="col-sm-4 col-lg-3 col-form-label fw-semibold">
                Item Group Code <span class="req">*</span>
            </label>
            <div class="col-sm-8 col-lg-9">
                <input type="text" id="item_group_code" name="item_group_code" maxlength="5" required
                       value="{{ old('item_group_code', $product?->item_group_code) }}"
                       class="form-control text-uppercase js-unique-check @error('item_group_code') is-invalid @enderror"
                       data-field="item_group_code" placeholder="PRD01" autocomplete="off">
                @error('item_group_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                {{-- Written by JS only once a code has actually been checked. --}}
                <div class="form-text js-unique-feedback">Up to 5 characters. Must be unique.</div>
            </div>
        </div>

        {{-- Col C — same alert rule --}}
        <div class="row form-line">
            <label for="name" class="col-sm-4 col-lg-3 col-form-label fw-semibold">
                Product Name <span class="req">*</span>
            </label>
            <div class="col-sm-8 col-lg-9">
                <input type="text" id="name" name="name" maxlength="200" required
                       value="{{ old('name', $product?->name) }}"
                       class="form-control js-unique-check @error('name') is-invalid @enderror"
                       data-field="name" placeholder="Cotton Casual Shirt" autocomplete="off">
                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text js-unique-feedback"></div>
            </div>
        </div>

        {{-- Col D --}}
        <x-ui.field name="name_on_export_document" label="Product Name as per Export Document"
                    :value="$product?->name_on_export_document" horizontal
                    placeholder="Exactly as it must print on the invoice" />

        {{-- Col E — "needs to have text and number allowed" --}}
        <x-ui.field name="barcode" label="Barcode" :value="$product?->barcode" horizontal
                    placeholder="Letters and numbers" />

    </div>
</x-ui.form-section>

{{-- ================= F–K · UNITS AND CLASSIFICATION ================ --}}
<x-ui.form-section title="Units & Classification" icon="bi-rulers"
                   subtitle="Units, HSN, price band and tax rate.">
    <div class="form-stack">

        {{-- Cols F and G — picked, not typed. Synced from the units defined on
             the Order Format master, so PCS cannot drift into Pcs and pcs
             across products and then export documents. The unit on a PO is
             not always the unit declared on the export document, hence two
             fields. --}}
        <x-ui.select name="unit_po" label="Unit (PO & OC)" :options="$units"
                     :selected="$product?->unit_po" horizontal searchable
                     placeholder="Search or type a new unit…"
                     data-create-url="{{ route('masters.products.units.store') }}"
                     hint="From Order Format units — type a new name to add it everywhere." />

        <x-ui.select name="unit_export" label="Unit (Export Docs)" :options="$units"
                     :selected="$product?->unit_export" horizontal searchable
                     placeholder="Search or type a new unit…"
                     data-create-url="{{ route('masters.products.units.store') }}" />

        {{-- Col H — a text box on the sheet, not a dropdown --}}
        <x-ui.field name="hsn_code" label="HSN Code" :value="$product?->hsn_code"
                    horizontal placeholder="620520" />

        {{-- Col J — includes N/A for products with no band --}}
        <x-ui.select name="price_band_id" label="Price Band" horizontal searchable
                     :options="$priceBands" :selected="$product?->price_band_id"
                     placeholder="Search band…"
                     hint="Choose N/A when price band does not apply." />

        {{-- Col K — "different rates with option to add in the future". Typing
             a rate not already in the list adds it, same as Payment Terms and
             Designation on the Buyer form — see ProductController::storeGstRate(). --}}
        <x-ui.select name="gst_rate_id" label="GST %" horizontal searchable
                     :options="$gstRates" :selected="$product?->gst_rate_id"
                     placeholder="Search or type a new rate…"
                     data-create-url="{{ route('masters.products.gst-rates.store') }}" />

        {{-- Col I --}}
        <x-ui.field name="drawback_sr_no" label="Drawback Sr. No." :value="$product?->drawback_sr_no"
                    horizontal placeholder="B001" />

    </div>
</x-ui.form-section>

{{-- ===================== L–U · EXPORT INCENTIVES ==================== --}}
<x-ui.form-section title="Export Incentives" icon="bi-cash-coin"
                   subtitle="Rate % applies to FOB value; Cap Value applies per PCS. Claim = the lower of the two. Leave a row blank if a scheme does not apply.">
    <div class="form-stack">
        <div class="table-responsive incentives-grid-wrap">
            <table class="table grid-table align-middle mb-0 incentives-grid">
                <thead>
                    <tr>
                        <th style="width:90px">Applicable</th>
                        <th style="width:110px">Scheme</th>
                        <th style="width:110px">Rate %</th>
                        <th style="width:110px">Rate % 2</th>
                        <th style="width:130px">Cap Value <span class="fw-normal text-body-secondary">(per PCS)</span></th>
                        <th style="width:130px">Cap Value 2 <span class="fw-normal text-body-secondary">(per PCS)</span></th>
                        <th style="min-width:200px">Calculated On</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(ProductIncentive::SCHEMES as $scheme => $schemeLabel)
                        @php
                            $twoPercent = in_array($scheme, ProductIncentive::TWO_PERCENT_SCHEMES, true);
                            $schemeEnabled = filled($incentive($scheme, 'percent_1'))
                                || filled($incentive($scheme, 'cap_value'))
                                || filled($incentive($scheme, 'cap_value_2'))
                                || filled($incentive($scheme, 'calculation_basis_id'));
                        @endphp

                        <tr>
                            <td>
                                {{-- Off by default — a blank row already means "not applicable"
                                     (see the section subtitle). The switch just makes that state
                                     explicit and stops a stray keystroke from applying a scheme
                                     nobody meant to turn on. --}}
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input js-incentive-toggle" type="checkbox"
                                           role="switch" id="incentive-toggle-{{ $scheme }}"
                                           data-scheme="{{ $scheme }}"
                                           @checked($schemeEnabled)>
                                </div>
                            </td>

                            <td class="scheme-name">{{ $schemeLabel }}</td>

                            <td>
                                <input type="number" step="0.001" min="0" max="100" placeholder="0.000"
                                       name="incentives[{{ $scheme }}][percent_1]"
                                       value="{{ $incentive($scheme, 'percent_1') }}"
                                       data-incentive-field="{{ $scheme }}"
                                       class="form-control @error('incentives.'.$scheme.'.percent_1') is-invalid @enderror">
                                @error('incentives.'.$scheme.'.percent_1')
                                    <div class="cell-error">{{ $message }}</div>
                                @enderror
                            </td>

                            <td>
                                @if($twoPercent)
                                    {{-- Only RoSCTL is quoted as two percentages (sheet cols S and W). --}}
                                    <input type="number" step="0.001" min="0" max="100" placeholder="0.000"
                                           name="incentives[{{ $scheme }}][percent_2]"
                                           value="{{ $incentive($scheme, 'percent_2') }}"
                                           data-incentive-field="{{ $scheme }}"
                                           class="form-control @error('incentives.'.$scheme.'.percent_2') is-invalid @enderror">
                                    @error('incentives.'.$scheme.'.percent_2')
                                        <div class="cell-error">{{ $message }}</div>
                                    @enderror
                                @else
                                    <div class="na">—</div>
                                @endif
                            </td>

                            <td>
                                <input type="number" step="0.0001" min="0" placeholder="0.0000"
                                       name="incentives[{{ $scheme }}][cap_value]"
                                       value="{{ $incentive($scheme, 'cap_value') }}"
                                       data-incentive-field="{{ $scheme }}"
                                       class="form-control @error('incentives.'.$scheme.'.cap_value') is-invalid @enderror">
                                @error('incentives.'.$scheme.'.cap_value')
                                    <div class="cell-error">{{ $message }}</div>
                                @enderror
                            </td>

                            <td>
                                @if($twoPercent)
                                    {{-- RoSCTL second cap (sheet col Y) — pairs with Rate % 2. --}}
                                    <input type="number" step="0.0001" min="0" placeholder="0.0000"
                                           name="incentives[{{ $scheme }}][cap_value_2]"
                                           value="{{ $incentive($scheme, 'cap_value_2') }}"
                                           data-incentive-field="{{ $scheme }}"
                                           class="form-control @error('incentives.'.$scheme.'.cap_value_2') is-invalid @enderror">
                                    @error('incentives.'.$scheme.'.cap_value_2')
                                        <div class="cell-error">{{ $message }}</div>
                                    @enderror
                                @else
                                    <div class="na">—</div>
                                @endif
                            </td>

                            <td>
                                <select name="incentives[{{ $scheme }}][calculation_basis_id]"
                                        data-searchable data-placeholder="Search basis…"
                                        data-incentive-field="{{ $scheme }}"
                                        class="form-select @error('incentives.'.$scheme.'.calculation_basis_id') is-invalid @enderror">
                                    <option value="">— Select —</option>
                                    @foreach($calculationBases as $id => $basis)
                                        <option value="{{ $id }}"
                                            @selected((string) $incentive($scheme, 'calculation_basis_id') === (string) $id)>{{ $basis }}</option>
                                    @endforeach
                                </select>
                                @error('incentives.'.$scheme.'.calculation_basis_id')
                                    <div class="cell-error">{{ $message }}</div>
                                @enderror
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Live claim preview — same rule used at shipment time.
             RoSCTL with two caps: min(p1×FOB,c1×PCS) + min(p2×FOB,c2×PCS). --}}
        <div class="border rounded p-3 mt-3 bg-body-tertiary incentive-claim-preview">
            <div class="d-flex flex-wrap align-items-end gap-3 mb-2">
                <div>
                    <label class="form-label small mb-1">Sample FOB value (₹)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="incentive-sample-fob" value="10000" style="max-width:10rem">
                </div>
                <div>
                    <label class="form-label small mb-1">Sample qty (PCS)</label>
                    <input type="number" step="1" min="0" class="form-control form-control-sm" id="incentive-sample-pcs" value="100" style="max-width:8rem">
                </div>
                <div class="form-text mb-1">
                    Claim = <strong>min</strong>( Rate% × FOB , Cap × PCS ). RoSCTL with 2 caps = leg1 + leg2.
                </div>
            </div>
            <div id="incentive-claim-rows" class="small"></div>
        </div>
    </div>
</x-ui.form-section>

{{-- ==================== BOM / COMPONENTS (Task 13) ==================== --}}
<x-ui.form-section title="BOM / Components" icon="bi-diagram-3"
                   subtitle="Optional. Free-text components per finished piece. Leave empty if this product has no BOM.">
    @php
        $bomRows = old('bom', $product?->bomItems?->toArray() ?? []);
    @endphp
    <div id="bom-wrap">
        @forelse($bomRows as $index => $row)
            <div class="row g-2 align-items-end mb-2 bom-row" data-bom-row>
                <div class="col-md-4">
                    <label class="form-label small">Component</label>
                    <input type="text" name="bom[{{ $index }}][component_name]" class="form-control form-control-sm"
                           maxlength="200" value="{{ $row['component_name'] ?? '' }}" placeholder="e.g. Lining fabric">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Qty / piece</label>
                    <input type="number" step="0.0001" min="0" name="bom[{{ $index }}][qty]" class="form-control form-control-sm"
                           value="{{ $row['qty'] ?? '1' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Unit</label>
                    <select name="bom[{{ $index }}][unit]" class="form-select form-select-sm">
                        <option value="">—</option>
                        @foreach($units as $unitValue => $unitLabel)
                            <option value="{{ $unitValue }}" @selected(($row['unit'] ?? '') === $unitValue)>{{ $unitLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Remarks</label>
                    <input type="text" name="bom[{{ $index }}][remarks]" class="form-control form-control-sm"
                           maxlength="500" value="{{ $row['remarks'] ?? '' }}">
                    <input type="hidden" name="bom[{{ $index }}][is_custom]" value="1">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-outline-danger w-100 js-remove-bom" title="Remove">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        @empty
        @endforelse
    </div>
    <button type="button" id="add-bom-row" class="btn btn-sm btn-outline-primary mt-1">
        <i class="bi bi-plus-lg me-1"></i>Add component
    </button>
</x-ui.form-section>

{{-- ==================== V–X · FABRIC MEASUREMENT ==================== --}}
<x-ui.form-section title="Fabric Measurement" icon="bi-bounding-box"
                   subtitle="For fabrics and sarees. Leave blank for other products.">
    <div class="form-stack">

        {{-- Cols V and W --}}
        <x-ui.field name="fabric_length_mtr" label="Fabric Length (mtrs)" type="number" step="0.001" min="0"
                    :value="$product?->fabric_length_mtr" horizontal
                    class="js-sqm-input" placeholder="0.000" />

        <x-ui.field name="fabric_width_inch" label="Fabric Width (inch)" type="number" step="0.001" min="0"
                    :value="$product?->fabric_width_inch" horizontal
                    class="js-sqm-input" placeholder="0.000" />

        {{-- Col X — "autocalculated based on inputted values". Shown live for
             feedback; the stored value is a generated column computed by the
             database, so the formula has exactly one home. --}}
        <div class="row form-line">
            <label class="col-sm-4 col-lg-3 col-form-label fw-semibold">Sq. Mtrs / Unit</label>
            <div class="col-sm-8 col-lg-9">
                <div class="input-group">
                    <span class="input-group-text bg-body-secondary"><i class="bi bi-calculator"></i></span>
                    <input type="text" id="sqm_preview" class="form-control" readonly
                           value="{{ $product?->sq_mtr_per_unit }}" placeholder="Calculated">
                </div>
            </div>
        </div>

    </div>
</x-ui.form-section>

{{-- ==================== Y, Z, AA · OTHER DETAILS =================== --}}
<x-ui.form-section title="Other Details" icon="bi-card-text">
    <div class="form-stack">

        {{-- Col Z --}}
        <x-ui.select name="status" label="Status" required horizontal
                     :options="['active' => 'Active', 'inactive' => 'Inactive']"
                     :selected="$product?->status ?? 'active'"
                     :placeholder="false" />

        {{-- Col Y --}}
        <x-ui.textarea name="description" label="Description" :value="$product?->description"
                       horizontal rows="2" placeholder="100% Cotton, 180 GSM" />

        {{-- Col AA --}}
        <x-ui.textarea name="remarks" label="Remarks" :value="$product?->remarks"
                       horizontal rows="2" placeholder="Optional notes" />

        <x-ui.textarea name="comments" label="Comments" :value="$product?->comments"
                       horizontal rows="2" placeholder="Optional comments" />

    </div>
</x-ui.form-section>

<div class="form-actions">
    <button type="submit" class="btn btn-primary px-4">
        <i class="bi bi-check-lg me-1"></i>{{ $product ? 'Update' : 'Save' }} Product
    </button>
    <a href="{{ route('masters.products.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ------------------------------------------------------------------ *
     * Sq. metres preview (sheet col X).
     * Display only — the stored value is computed by the database, so this
     * cannot drift from what is saved.
     * ------------------------------------------------------------------ */
    const preview = document.getElementById('sqm_preview');
    const inputs  = document.querySelectorAll('.js-sqm-input');

    function recalcSqm() {
        const [length, width] = Array.from(inputs).map(el => parseFloat(el.value));

        preview.value = (isNaN(length) || isNaN(width))
            ? ''
            : ((length * width) / 39.3701).toFixed(4);
    }

    inputs.forEach(el => el.addEventListener('input', recalcSqm));

    /* ------------------------------------------------------------------ *
     * Task 13 — Product BOM rows (free-text, empty allowed).
     * ------------------------------------------------------------------ */
    const bomWrap = document.getElementById('bom-wrap');
    const bomUnitEntries = @json($units);

    function nextBomIndex() {
        return bomWrap.querySelectorAll('[data-bom-row]').length;
    }

    document.getElementById('add-bom-row')?.addEventListener('click', function () {
        const i = nextBomIndex();
        const unitOpts = Object.entries(bomUnitEntries).map(function (pair) {
            return '<option value="' + pair[0] + '">' + pair[1] + '</option>';
        }).join('');

        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end mb-2 bom-row';
        row.dataset.bomRow = '1';
        row.innerHTML =
            '<div class="col-md-4">' +
                '<label class="form-label small">Component</label>' +
                '<input type="text" name="bom[' + i + '][component_name]" class="form-control form-control-sm" maxlength="200" placeholder="e.g. Lining fabric">' +
            '</div>' +
            '<div class="col-md-2">' +
                '<label class="form-label small">Qty / piece</label>' +
                '<input type="number" step="0.0001" min="0" name="bom[' + i + '][qty]" class="form-control form-control-sm" value="1">' +
            '</div>' +
            '<div class="col-md-2">' +
                '<label class="form-label small">Unit</label>' +
                '<select name="bom[' + i + '][unit]" class="form-select form-select-sm"><option value="">—</option>' + unitOpts + '</select>' +
            '</div>' +
            '<div class="col-md-3">' +
                '<label class="form-label small">Remarks</label>' +
                '<input type="text" name="bom[' + i + '][remarks]" class="form-control form-control-sm" maxlength="500">' +
                '<input type="hidden" name="bom[' + i + '][is_custom]" value="1">' +
            '</div>' +
            '<div class="col-md-1">' +
                '<button type="button" class="btn btn-sm btn-outline-danger w-100 js-remove-bom" title="Remove"><i class="bi bi-trash"></i></button>' +
            '</div>';
        bomWrap.appendChild(row);
    });

    bomWrap?.addEventListener('click', function (e) {
        if (e.target.closest('.js-remove-bom')) {
            e.target.closest('[data-bom-row]')?.remove();
        }
    });

    /* ------------------------------------------------------------------ *
     * Export incentive toggles — Drawback, RoSCTL, RoDTEP each get an
     * "Applicable" switch. Off disables and clears that row's inputs; a
     * blank row already means "not applicable" to the server (see the
     * section subtitle), so switching off is what actually posts nothing
     * for that scheme.
     * ------------------------------------------------------------------ */
    document.querySelectorAll('.js-incentive-toggle').forEach(function (toggle) {
        const scheme = toggle.dataset.scheme;
        const fields = document.querySelectorAll('[data-incentive-field="' + scheme + '"]');
        const fobBasisId = @json(
            collect($calculationBases)->search(fn ($label) => str_contains(strtolower((string) $label), 'fob'))
                ?: collect($calculationBases)->keys()->first()
        );

        function apply() {
            fields.forEach(function (field) {
                field.disabled = ! toggle.checked;

                if (! toggle.checked) {
                    if (field.tomselect) {
                        field.tomselect.clear(true);
                        field.tomselect.disable();
                    } else {
                        field.value = '';
                    }
                } else if (field.tomselect) {
                    field.tomselect.enable();
                    // Default Calculated On → FOB Value (rate side of the claim rule).
                    if (field.name && field.name.indexOf('[calculation_basis_id]') !== -1 && ! field.value && fobBasisId) {
                        field.tomselect.setValue(String(fobBasisId), true);
                    }
                } else if (field.name && field.name.indexOf('[calculation_basis_id]') !== -1 && ! field.value && fobBasisId) {
                    field.value = String(fobBasisId);
                }
            });
            renderIncentiveClaims();
        }

        toggle.addEventListener('change', apply);
        apply();
    });

    /* ------------------------------------------------------------------ *
     * Live claim preview: Rate% × FOB vs Cap × PCS → lower.
     * ------------------------------------------------------------------ */
    const sampleFob = document.getElementById('incentive-sample-fob');
    const samplePcs = document.getElementById('incentive-sample-pcs');
    const claimRows = document.getElementById('incentive-claim-rows');
    const schemeLabels = @json(ProductIncentive::SCHEMES);

    function schemeClaim(scheme, fob, pcs) {
        const p1 = parseFloat(document.querySelector('[name="incentives[' + scheme + '][percent_1]"]')?.value) || 0;
        const p2El = document.querySelector('[name="incentives[' + scheme + '][percent_2]"]');
        const p2 = p2El && ! p2El.disabled ? (parseFloat(p2El.value) || 0) : 0;
        const cap1Raw = document.querySelector('[name="incentives[' + scheme + '][cap_value]"]')?.value;
        const cap2El = document.querySelector('[name="incentives[' + scheme + '][cap_value_2]"]');
        const cap2Raw = cap2El && ! cap2El.disabled ? cap2El.value : '';
        const enabled = document.getElementById('incentive-toggle-' + scheme)?.checked;

        function leg(pct, capRaw) {
            const rateAmt = Math.max(0, fob * (pct / 100));
            const hasCap = capRaw !== undefined && capRaw !== null && String(capRaw).trim() !== '';
            const capAmt = hasCap ? Math.max(0, pcs * (parseFloat(capRaw) || 0)) : null;
            const claim = capAmt === null ? rateAmt : Math.min(rateAmt, capAmt);
            return { rateAmt, capAmt, claim, pct };
        }

        // RoSCTL with both caps filled → two independent legs (matches sheet U + Y).
        const hasCap2 = cap2Raw !== undefined && cap2Raw !== null && String(cap2Raw).trim() !== '';
        if (scheme === 'rosctl' && p2 > 0 && hasCap2) {
            const a = leg(p1, cap1Raw);
            const b = leg(p2, cap2Raw);
            return {
                ratePct: a.pct + b.pct,
                rateAmt: a.rateAmt + b.rateAmt,
                capAmt: (a.capAmt || 0) + (b.capAmt || 0),
                claim: a.claim + b.claim,
                enabled: enabled,
                paired: true,
            };
        }

        const combined = leg(p1 + p2, cap1Raw);
        return {
            ratePct: combined.pct,
            rateAmt: combined.rateAmt,
            capAmt: combined.capAmt,
            claim: combined.claim,
            enabled: enabled,
            paired: false,
        };
    }

    function renderIncentiveClaims() {
        if (! claimRows) return;
        const fob = parseFloat(sampleFob?.value) || 0;
        const pcs = parseFloat(samplePcs?.value) || 0;
        const lines = [];
        Object.keys(schemeLabels).forEach(function (scheme) {
            const row = schemeClaim(scheme, fob, pcs);
            if (! row.enabled || row.ratePct <= 0) return;
            const capText = row.capAmt === null
                ? 'no cap'
                : ('Cap×PCS = ' + row.capAmt.toFixed(2));
            lines.push(
                '<div class="mb-1"><strong>' + schemeLabels[scheme] + '</strong>: '
                + 'Rate×FOB = ' + row.rateAmt.toFixed(2)
                + ' · ' + capText
                + ' → <span class="text-success">Claim ₹' + row.claim.toFixed(2) + '</span></div>'
            );
        });
        claimRows.innerHTML = lines.length
            ? lines.join('')
            : '<span class="text-body-secondary">Turn on a scheme and enter Rate % to preview the claim.</span>';
    }

    sampleFob?.addEventListener('input', renderIncentiveClaims);
    samplePcs?.addEventListener('input', renderIncentiveClaims);
    document.querySelectorAll('[data-incentive-field]').forEach(function (el) {
        el.addEventListener('input', renderIncentiveClaims);
        el.addEventListener('change', renderIncentiveClaims);
    });
    renderIncentiveClaims();

    /* ------------------------------------------------------------------ *
     * "Tell me if a code / name is already taken" (sheet cols B and C).
     * A convenience only — the unique index is what actually enforces it.
     * ------------------------------------------------------------------ */
    const checkUrl = @json(route('masters.products.check-code'));
    const ignoreId = @json($product?->id);
    const original = new Map();

    document.querySelectorAll('.js-unique-check').forEach(function (input) {
        original.set(input, input.value.trim());
        const feedback = input.parentElement.querySelector('.js-unique-feedback');
        let timer = null;

        function clearFeedback() {
            feedback.textContent = '';
            feedback.className = 'form-text js-unique-feedback';
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            const value = input.value.trim();

            // Unchanged on an edit form is not a clash with itself.
            if (value === '' || value === original.get(input)) {
                clearFeedback();
                input.classList.remove('is-invalid', 'is-valid');
                return;
            }

            // Debounced so a 12-character code is one request, not twelve.
            timer = setTimeout(function () {
                const params = new URLSearchParams({ field: input.dataset.field, value: value });
                if (ignoreId) params.append('ignore', ignoreId);

                fetch(checkUrl + '?' + params, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.ok ? r.json() : Promise.reject())
                    .then(function (data) {
                        input.classList.toggle('is-invalid', !data.available);
                        input.classList.toggle('is-valid', data.available);
                        feedback.textContent = data.available
                            ? 'Available.'
                            : 'Already taken — choose another.';
                        feedback.className = 'form-text js-unique-feedback ' +
                            (data.available ? 'text-success' : 'text-danger');
                    })
                    // Network failure must not block the form. The unique index
                    // still rejects a duplicate on submit.
                    .catch(clearFeedback);
            }, 350);
        });
    });
});
</script>
@endpush
