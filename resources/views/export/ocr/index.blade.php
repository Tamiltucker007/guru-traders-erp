<x-app-layout>
    <x-slot name="header">Document OCR</x-slot>

    <x-ui.card title="Document OCR — Uploaded docs" variant="primary">
        <x-slot name="actions">
            <a href="{{ route('export.documents.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-files me-1"></i> Export Documents
            </a>
        </x-slot>

        <p class="text-body-secondary small mb-3">
            Select the Export Document, upload a scan, then <strong>Extract with Gemini</strong>.
            Gemini fills reference / remarks and matches parties from the scan; after extract the desk
            also shows this order’s Buyer / Supplier / OC, payment status, jobbers, inventory, and
            Markup price hints (from the selected order — not invented by Gemini).
        </p>

        @unless($ocrConfigured)
            <div class="alert alert-warning">
                Gemini is not configured. Add <code>GEMINI_API_KEY</code> to <code>.env</code>, then run
                <code>php artisan config:clear</code>.
            </div>
        @endunless

        @if($documents->isEmpty())
            <x-ui.empty-state icon="bi-stars"
                              title="No Export Document to attach OCR to"
                              message="Raise an Export Document from a confirmed Order Confirmation first, then come back here." />
        @else
            <form id="ocr-save-form" action="{{ route('export.ocr.store') }}" method="POST" enctype="multipart/form-data" class="row g-3">
                @csrf

                <div class="col-md-5">
                    <label class="form-label fw-semibold">Export Document <span class="req">*</span></label>
                    <select name="export_document_id" id="export_document_id" class="form-select" required
                            onchange="window.location='{{ route('export.ocr.index') }}?export_document_id='+this.value+'&type_code={{ $typeCode }}'">
                        @foreach($documents as $doc)
                            <option value="{{ $doc->id }}" @selected($selected?->id === $doc->id)>
                                {{ $doc->doc_num }} — {{ $doc->buyer?->company_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Uploaded document type <span class="req">*</span></label>
                    <select name="type_code" id="type_code" class="form-select" required>
                        @foreach($typeLabels as $code => $label)
                            <option value="{{ $code }}" @selected($typeCode === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">All uploaded types from the export docs sheet are enabled.</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Scan / PDF <span class="req">*</span></label>
                    <input type="file" name="file" id="ocr-file" class="form-control" required
                           accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/*,application/pdf">
                </div>

                <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                    <button type="button" id="btn-ocr-extract" class="btn btn-outline-primary"
                            @disabled(! $ocrConfigured)>
                        <i class="bi bi-stars me-1"></i> Extract with Gemini
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save to checklist
                    </button>
                    @if($selected)
                        <a href="{{ route('export.documents.show', $selected) }}" class="btn btn-outline-secondary">
                            Open Export Document
                        </a>
                    @endif
                    <span id="ocr-status" class="small text-body-secondary"></span>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Reference no.</label>
                    <input type="text" name="reference_no" id="ocr-reference" class="form-control"
                           value="{{ old('reference_no', $checklist?->reference_no) }}"
                           placeholder="Filled by OCR — editable">
                </div>

                <div class="col-md-8">
                    <label class="form-label fw-semibold">Remarks</label>
                    <input type="text" name="remarks" id="ocr-remarks" class="form-control"
                           value="{{ old('remarks', $checklist?->remarks) }}"
                           placeholder="Date / SB / CHA notes from OCR">
                </div>

                <input type="hidden" name="matched_buyer_id" id="ocr-matched-buyer-id"
                       value="{{ old('matched_buyer_id', $checklist?->matched_buyer_id) }}">
                <input type="hidden" name="matched_supplier_id" id="ocr-matched-supplier-id"
                       value="{{ old('matched_supplier_id', $checklist?->matched_supplier_id) }}">
                <input type="hidden" name="matched_order_confirmation_id" id="ocr-matched-oc-id"
                       value="{{ old('matched_order_confirmation_id', $checklist?->matched_order_confirmation_id) }}">
                <input type="hidden" name="ocr_verification_json" id="ocr-verification-json" value="">

                <div class="col-12" id="ocr-party-panel" @class(['d-none' => ! ($checklist?->matched_buyer_id || $checklist?->matched_supplier_id || $checklist?->matched_order_confirmation_id)])>
                    <div class="border rounded-3 p-3 bg-body-tertiary">
                        <div class="fw-semibold mb-2"><i class="bi bi-people me-1"></i> Linked parties &amp; order</div>
                        <div class="row g-2 small">
                            <div class="col-md-4">
                                <div class="text-body-secondary">Buyer (linked for Save)</div>
                                <div id="ocr-buyer-match" class="fw-semibold">
                                    @if($checklist?->matchedBuyer)
                                        {{ $checklist->matchedBuyer->display_code }} — {{ $checklist->matchedBuyer->company_name }}
                                    @else
                                        —
                                    @endif
                                </div>
                                <div id="ocr-buyer-scan" class="text-body-secondary"></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-body-secondary">Supplier (linked for Save)</div>
                                <div id="ocr-supplier-match" class="fw-semibold">
                                    @if($checklist?->matchedSupplier)
                                        {{ $checklist->matchedSupplier->display_code }} — {{ $checklist->matchedSupplier->company_name }}
                                    @else
                                        —
                                    @endif
                                </div>
                                <div id="ocr-supplier-scan" class="text-body-secondary"></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-body-secondary">Order Confirmation (linked)</div>
                                <div id="ocr-oc-match" class="fw-semibold">
                                    @if($checklist?->matchedOrderConfirmation)
                                        {{ $checklist->matchedOrderConfirmation->oc_num }}
                                        @if($checklist->matchedOrderConfirmation->buyer_ref)
                                            <span class="text-body-secondary fw-normal">({{ $checklist->matchedOrderConfirmation->buyer_ref }})</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </div>
                                <div id="ocr-oc-scan" class="text-body-secondary"></div>
                            </div>
                        </div>
                        <div id="ocr-party-hint" class="small text-body-secondary mt-2"></div>
                    </div>
                </div>

                {{-- Hidden until Extract succeeds — ma’am wants blank desk first, then order cockpit. --}}
                <div class="col-12 d-none" id="ocr-order-context">
                    @include('export.ocr._order-context', ['orderContext' => $orderContext ?? ['available' => false]])
                </div>

                <div class="col-12 d-none" id="ocr-verification-panel">
                    <div class="border rounded-3 p-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div class="fw-semibold"><i class="bi bi-ui-checks me-1"></i> ERP vs Document check</div>
                            <div class="small" id="ocr-verification-summary"></div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" id="ocr-verification-table">
                                <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>ERP Data</th>
                                    <th>Document Data</th>
                                    <th>Result</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="small text-body-secondary mt-2">
                            Gemini reads the PDF; we compare it with the selected Export Document to flag mistakes.
                        </div>
                    </div>
                </div>

                @if($typeCode === 'insurance')
                    <div class="col-12">
                        <div class="alert alert-light border small mb-0">
                            <strong>Insurance (#16) option 2:</strong> upload the certificate with B/L number and date.
                            To cancel the draft (option 1), use Update on the Export Document checklist.
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">B/L number <span class="req">*</span></label>
                        <input type="text" name="bl_number" id="ocr-bl-number" class="form-control" required
                               value="{{ old('bl_number', $checklist?->insuranceBlNumber()) }}"
                               placeholder="From final B/L">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">B/L date <span class="req">*</span></label>
                        <input type="date" name="bl_date" id="ocr-bl-date" class="form-control" required
                               value="{{ old('bl_date', $checklist?->insuranceBlDate()) }}">
                    </div>
                    <input type="hidden" name="insurance_action" value="upload_certificate">
                @endif

                @if($checklist)
                    <div class="col-12">
                        <div class="small text-body-secondary">
                            Current checklist status:
                            <span class="badge text-bg-{{ $checklist->statusColor() }}">{{ $checklist->statusLabel() }}</span>
                            @if($checklist->hasFile())
                                · <a href="{{ $checklist->fileUrl() }}" target="_blank" rel="noopener">{{ $checklist->original_name }}</a>
                            @endif
                        </div>
                    </div>
                @endif
            </form>

            @if(count($upcomingTypes))
            <div class="mt-4 pt-3 border-top">
                <h6 class="fw-semibold mb-2">Coming next (Uploaded only)</h6>
                <ul class="small text-body-secondary mb-0">
                    @foreach($upcomingTypes as $label)
                        <li>{{ $label }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        @endif
    </x-ui.card>

    {{-- Must stay inside <x-app-layout> so @stack('scripts') in the layout picks it up. --}}
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('btn-ocr-extract');
        const fileInput = document.getElementById('ocr-file');
        const typeSelect = document.getElementById('type_code');
        const docSelect = document.getElementById('export_document_id');
        const refInput = document.getElementById('ocr-reference');
        const remarksInput = document.getElementById('ocr-remarks');
        const statusEl = document.getElementById('ocr-status');
        const buyerIdInput = document.getElementById('ocr-matched-buyer-id');
        const supplierIdInput = document.getElementById('ocr-matched-supplier-id');
        const ocIdInput = document.getElementById('ocr-matched-oc-id');
        const partyPanel = document.getElementById('ocr-party-panel');
        const buyerMatchEl = document.getElementById('ocr-buyer-match');
        const supplierMatchEl = document.getElementById('ocr-supplier-match');
        const ocMatchEl = document.getElementById('ocr-oc-match');
        const buyerScanEl = document.getElementById('ocr-buyer-scan');
        const supplierScanEl = document.getElementById('ocr-supplier-scan');
        const ocScanEl = document.getElementById('ocr-oc-scan');
        const partyHintEl = document.getElementById('ocr-party-hint');
        if (! btn || ! fileInput || ! statusEl) return;

        function applyParties(parties) {
            if (! parties) return;

            if (buyerIdInput) buyerIdInput.value = parties.buyer?.id || '';
            if (supplierIdInput) supplierIdInput.value = parties.supplier?.id || '';
            if (ocIdInput) ocIdInput.value = parties.order_confirmation?.id || '';

            if (buyerMatchEl) {
                if (parties.buyer) {
                    buyerMatchEl.textContent = (parties.buyer.display_code || '') + ' — ' + parties.buyer.company_name
                        + (parties.buyer.source === 'shipment' ? ' (from this order)' : ' (' + parties.buyer.score + '%)');
                } else {
                    buyerMatchEl.textContent = '—';
                }
            }
            if (supplierMatchEl) {
                if (parties.supplier) {
                    supplierMatchEl.textContent = (parties.supplier.display_code || '') + ' — ' + parties.supplier.company_name
                        + (parties.supplier.source === 'shipment' ? ' (from this order)' : ' (' + parties.supplier.score + '%)');
                } else {
                    supplierMatchEl.textContent = '—';
                }
            }
            if (ocMatchEl) {
                if (parties.order_confirmation) {
                    ocMatchEl.textContent = parties.order_confirmation.oc_num
                        + (parties.order_confirmation.buyer_ref ? (' (' + parties.order_confirmation.buyer_ref + ')') : '')
                        + (parties.order_confirmation.source === 'shipment' ? ' (from this order)' : ' (' + parties.order_confirmation.score + '%)');
                } else {
                    ocMatchEl.textContent = '—';
                }
            }

            if (buyerScanEl) {
                buyerScanEl.textContent = parties.buyer_name
                    ? ('On scan: ' + parties.buyer_name + (parties.buyer ? '' : ' — not in master'))
                    : '';
            }
            if (supplierScanEl) {
                supplierScanEl.textContent = parties.supplier_name
                    ? ('On scan: ' + parties.supplier_name + (parties.supplier ? '' : ' — not in master'))
                    : '';
            }
            if (ocScanEl) {
                ocScanEl.textContent = parties.invoice_no
                    ? ('On scan invoice: ' + parties.invoice_no + (parties.order_confirmation ? '' : ' — no OC match'))
                    : '';
            }

            let hint = (parties.scan_notes || []).join(' ');
            if (parties.selected_buyer_matches === true && parties.selected_oc_matches === true) {
                hint = (hint ? hint + ' ' : '') + 'Ready to Save against this Export Document / OC.';
            } else if (parties.selected_buyer_matches === false) {
                hint = (hint ? hint + ' ' : '') + 'Warning: linked buyer differs from selected Export Document buyer.';
            }

            // Do not auto-switch away from the Export Document the user already picked.
            if (partyHintEl) partyHintEl.textContent = hint;
            if (partyPanel) partyPanel.classList.remove('d-none');
        }

        function money(v) {
            const n = Number(v);
            return Number.isFinite(n) ? n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
        }

        function applyOrderContext(ctx) {
            const panel = document.getElementById('ocr-order-context');
            if (! panel) return;

            // Show only after a successful Extract — keep blank until then.
            if (! ctx || ! ctx.available) {
                panel.classList.add('d-none');
                return;
            }
            panel.classList.remove('d-none');

            const setText = (id, text) => {
                const el = document.getElementById(id);
                if (el) el.innerHTML = text;
            };

            setText('ocr-ctx-summary', ctx.summary || '');

            const doc = ctx.export_document || {};
            setText('ocr-ctx-doc', (doc.doc_num || '—')
                + (doc.invoice_no ? ('<div class="fw-normal text-body-secondary">Invoice ' + doc.invoice_no + '</div>') : ''));

            const oc = ctx.order_confirmation || null;
            setText('ocr-ctx-oc', oc
                ? ((oc.oc_num || '—') + (oc.buyer_ref ? ('<div class="fw-normal text-body-secondary">Ref ' + oc.buyer_ref + '</div>') : ''))
                : '—');

            const buyer = ctx.buyer || null;
            if (buyer) {
                let pay = buyer.payment_term || 'No payment term';
                if (buyer.payment_term_days != null) pay += ' (' + buyer.payment_term_days + ' days)';
                if (buyer.advance_percent != null) pay += ' · Adv ' + buyer.advance_percent + '%';
                if (buyer.sight_percent != null) pay += ' · Sight ' + buyer.sight_percent + '%';
                setText('ocr-ctx-buyer', (buyer.display_code || '') + ' — ' + buyer.company_name
                    + '<div class="fw-normal text-body-secondary">' + pay + '</div>');
            } else {
                setText('ocr-ctx-buyer', '—');
            }

            const amount = doc.amount != null ? doc.amount : (oc ? oc.amount : null);
            const currency = doc.currency || (oc ? oc.currency : '') || '';
            setText('ocr-ctx-amount', amount != null ? (money(amount) + (currency ? (' ' + currency) : '')) : '—');

            const suppliersEl = document.getElementById('ocr-ctx-suppliers');
            if (suppliersEl) {
                const list = ctx.suppliers || [];
                suppliersEl.innerHTML = list.length
                    ? list.map(s => '<li>' + (s.display_code || '') + ' — ' + s.company_name
                        + (s.discount_percent != null ? (' <span class="text-body-secondary">(disc ' + s.discount_percent + '%)</span>') : '')
                        + '</li>').join('')
                    : '<li class="text-body-secondary">No supplier linked on OC / export lines yet.</li>';
            }

            const pay = ctx.payment || {};
            const badge = (row) => {
                const status = (row && row.status) || 'pending';
                const label = (row && row.label) || 'Pending';
                const cls = status === 'pending' ? 'secondary' : 'success';
                return '<span class="badge text-bg-' + cls + '">' + label + '</span>';
            };
            let payHtml = '<div>Swift / Payment: ' + badge(pay.payment_received);
            if (pay.payment_received && pay.payment_received.reference) {
                payHtml += ' <span class="text-body-secondary">' + pay.payment_received.reference + '</span>';
            }
            payHtml += '</div>';
            payHtml += '<div class="mt-1">EEFC: ' + badge(pay.eefc_upload) + '</div>';
            payHtml += '<div class="mt-1">eBRC: ' + badge(pay.ebrc) + '</div>';
            if (pay.due_note) {
                payHtml += '<div class="mt-1 text-body-secondary">' + pay.due_note + '</div>';
            }
            if ((pay.pending_labels || []).length) {
                payHtml += '<div class="text-warning mt-1">Still pending: ' + pay.pending_labels.join(', ') + '</div>';
            } else if (pay.realised) {
                payHtml += '<div class="text-success mt-1">Payment looks realised for this order.</div>';
            }
            setText('ocr-ctx-payment', payHtml);

            const jobbersEl = document.getElementById('ocr-ctx-jobbers');
            if (jobbersEl) {
                const jobbers = ctx.jobbers || [];
                jobbersEl.innerHTML = jobbers.length
                    ? jobbers.map(j => '<li>' + (j.display_code || '') + ' — ' + j.company_name
                        + (j.via_label ? (' <span class="text-body-secondary">(' + j.via_label + ')</span>') : '')
                        + '</li>').join('')
                    : '<li class="text-body-secondary">No jobber linked to this buyer / products yet.</li>';
            }

            const invBody = document.querySelector('#ocr-ctx-inventory tbody');
            if (invBody) {
                const invRows = (ctx.inventory && ctx.inventory.rows) ? ctx.inventory.rows : [];
                invBody.innerHTML = invRows.length
                    ? invRows.map(r => '<tr><td>' + (r.product || '—') + '</td>'
                        + '<td class="text-end">' + money(r.order_qty || 0) + '</td>'
                        + '<td class="text-end">' + money(r.po_ordered_qty || 0) + '</td>'
                        + '<td class="text-end">' + money(r.received_qty || 0) + '</td>'
                        + '<td class="text-end">' + money(r.balance_to_receive || 0) + '</td>'
                        + '<td class="text-end">' + money(r.global_passed_qty || 0) + '</td></tr>').join('')
                    : '<tr><td colspan="6" class="text-body-secondary">No product qty to show yet.</td></tr>';
                const invNote = document.getElementById('ocr-ctx-inventory-note');
                if (invNote && ctx.inventory && ctx.inventory.note) {
                    invNote.textContent = ctx.inventory.note;
                }
            }

            const pricing = ctx.pricing || {};
            if (pricing.sample_profit != null) {
                let pricingHtml = 'Markup ' + pricing.markup_percent + '% · Client ' + money(pricing.sample_client_price)
                    + ' · Our cost ' + money(pricing.sample_our_cost)
                    + ' · Profit ' + money(pricing.sample_profit);
                if (pricing.total_line_profit != null) {
                    pricingHtml += ' · Line total profit ' + money(pricing.total_line_profit);
                }
                pricingHtml += '<div class="text-body-secondary">' + (pricing.note || '') + '</div>';
                setText('ocr-ctx-pricing', pricingHtml);
            } else {
                setText('ocr-ctx-pricing', '<span class="text-body-secondary">' + (pricing.note || 'No markup sample yet.') + '</span>');
            }

            const minBody = document.querySelector('#ocr-ctx-min-prices tbody');
            if (minBody) {
                const mins = ctx.min_prices || [];
                minBody.innerHTML = mins.length
                    ? mins.map(m => '<tr><td>' + (m.product || '—') + '</td>'
                        + '<td class="text-end">' + (m.min_cost_price != null ? money(m.min_cost_price) : '—') + '</td>'
                        + '<td>' + (m.min_cost_supplier || '—') + '</td>'
                        + '<td class="text-end">' + (m.min_list_price != null ? money(m.min_list_price) : '—')
                        + (m.min_list_party ? ('<div class="text-body-secondary fw-normal">' + m.min_list_party + '</div>') : '')
                        + '</td></tr>').join('')
                    : '<tr><td colspan="4" class="text-body-secondary">Link products on OC lines to compare supplier prices.</td></tr>';
            }

            const tbody = document.querySelector('#ocr-ctx-lines tbody');
            if (tbody) {
                const lines = (ctx.line_calcs && ctx.line_calcs.length) ? ctx.line_calcs : (ctx.lines || []);
                tbody.innerHTML = lines.length
                    ? lines.map(l => {
                        const list = l.list_price != null ? l.list_price : l.price;
                        let listCell = money(list || 0);
                        if (l.cost_price != null) {
                            listCell += '<div class="text-body-secondary">cost ' + money(l.cost_price) + '</div>';
                        }
                        let profitCell = '—';
                        if (l.unit_profit != null) {
                            profitCell = money(l.unit_profit);
                            if (l.line_profit != null) {
                                profitCell += '<div class="text-body-secondary">line ' + money(l.line_profit) + '</div>';
                            }
                        }
                        return '<tr><td>' + (l.product || '—') + '</td><td>' + (l.supplier || '—')
                            + '</td><td class="text-end">' + money(l.qty || 0) + ' ' + (l.unit || '')
                            + '</td><td class="text-end">' + listCell
                            + '</td><td class="text-end">' + (l.client_price != null ? money(l.client_price) : '—')
                            + '</td><td class="text-end">' + (l.our_cost != null ? money(l.our_cost) : '—')
                            + '</td><td class="text-end">' + profitCell + '</td></tr>';
                    }).join('')
                    : '<tr><td colspan="7" class="text-body-secondary">No OC lines loaded.</td></tr>';
            }
        }

        function applyVerification(v) {
            const panel = document.getElementById('ocr-verification-panel');
            const hidden = document.getElementById('ocr-verification-json');
            if (! panel) return;

            if (! v || ! (v.rows || []).length) {
                panel.classList.add('d-none');
                if (hidden) hidden.value = '';
                return;
            }

            panel.classList.remove('d-none');
            if (hidden) hidden.value = JSON.stringify(v);

            const summary = document.getElementById('ocr-verification-summary');
            if (summary) {
                const badge = v.status === 'verified' ? 'success'
                    : (v.status === 'errors' ? 'danger'
                        : (v.status === 'review' ? 'warning' : 'secondary'));
                summary.innerHTML = '<span class="badge text-bg-' + badge + '">' + (v.status_label || '') + '</span>'
                    + ' <span class="text-body-secondary">' + (v.summary || '') + '</span>';
            }

            const tbody = document.querySelector('#ocr-verification-table tbody');
            if (! tbody) return;

            const icon = (result, label) => {
                if (result === 'match') return '<span class="text-success">Match</span>';
                if (result === 'error') return '<span class="text-danger">' + (label || 'Error') + '</span>';
                if (result === 'warning') return '<span class="text-warning">' + (label || 'Possible spelling error') + '</span>';
                return '<span class="text-body-secondary">' + (label || result || '—') + '</span>';
            };

            tbody.innerHTML = (v.rows || []).map(r =>
                '<tr><td>' + (r.field || '—') + '</td><td>' + (r.erp || '—') + '</td><td>'
                + (r.document || '—') + '</td><td>' + icon(r.result, r.result_label) + '</td></tr>'
            ).join('');
        }

        // Keep checklist status / saved fields in sync with the selected type.
        if (typeSelect && docSelect) {
            typeSelect.addEventListener('change', function () {
                const base = @json(route('export.ocr.index'));
                window.location = base
                    + '?export_document_id=' + encodeURIComponent(docSelect.value)
                    + '&type_code=' + encodeURIComponent(typeSelect.value);
            });
        }

        function csrfToken() {
            return document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('#ocr-save-form input[name="_token"]')?.value
                || '';
        }

        function xsrfToken() {
            const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
            return match ? decodeURIComponent(match[1]) : '';
        }

        btn.addEventListener('click', async function () {
            if (! fileInput.files.length) {
                statusEl.className = 'small text-danger';
                statusEl.textContent = 'Choose a PDF or image first.';
                return;
            }

            const token = csrfToken();
            if (! token) {
                statusEl.className = 'small text-danger';
                statusEl.textContent = 'Missing CSRF token — refresh the page (Ctrl+F5) and try again.';
                return;
            }

            const body = new FormData();
            body.append('file', fileInput.files[0]);
            body.append('type_code', typeSelect.value);
            body.append('_token', token);
            if (docSelect?.value) {
                body.append('export_document_id', docSelect.value);
            }

            const headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
            };
            const xsrf = xsrfToken();
            if (xsrf) {
                headers['X-XSRF-TOKEN'] = xsrf;
            }

            btn.disabled = true;
            statusEl.className = 'small text-body-secondary';
            statusEl.textContent = 'Reading with Gemini…';

            try {
                const res = await fetch(@json(route('export.ocr.extract')), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers,
                    body,
                });
                const data = await res.json().catch(() => ({}));

                if (res.status === 419) {
                    throw new Error('Session expired (CSRF). Refresh the page (Ctrl+F5), re-select the file, then Extract again.');
                }
                if (! res.ok) throw new Error(data.message || ('OCR failed (' + res.status + ')'));

                if (data.reference_no) refInput.value = data.reference_no;
                if (data.remarks) remarksInput.value = data.remarks;
                applyParties(data.parties);
                applyOrderContext(data.order_context);
                applyVerification(data.verification);

                const blNumberInput = document.getElementById('ocr-bl-number');
                const blDateInput = document.getElementById('ocr-bl-date');
                const fields = data.fields || {};
                if (blNumberInput && fields.bl_number) {
                    blNumberInput.value = fields.bl_number;
                }
                if (blDateInput) {
                    const blDate = fields.bl_date || fields.document_date
                        || (String(data.remarks || '').match(/B\/L date:\s*(\d{4}-\d{2}-\d{2})/i) || [])[1]
                        || (String(data.remarks || '').match(/Date:\s*(\d{4}-\d{2}-\d{2})/i) || [])[1]
                        || '';
                    if (blDate) blDateInput.value = blDate;
                }

                statusEl.className = 'small text-success';
                statusEl.textContent = 'Fields filled + ERP vs Document check ready — review matches/errors, then Save.';
            } catch (err) {
                statusEl.className = 'small text-danger';
                statusEl.textContent = err.message || 'OCR failed.';
            } finally {
                btn.disabled = false;
            }
        });
    });
    </script>
    @endpush
</x-app-layout>
