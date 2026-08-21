<x-app-layout>
    <x-slot name="header">Export Document {{ $document->doc_num }}</x-slot>

    <x-ui.card :title="$document->doc_num" variant="primary">
        <x-slot name="actions">
            @can('export-document.edit')
                <a href="{{ route('export.documents.edit', $document) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
            <a href="{{ route('export.documents.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <x-order-context-modal :order-context="$orderContext ?? ['available' => false]" modal-id="exportOrderContextModal" />
        </x-slot>

        {{-- Always-visible status strip — the handful of facts worth seeing without clicking a tab. --}}
        <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3 pb-3 mb-3 border-bottom">
            <span class="badge text-bg-{{ $document->statusColor() }}">{{ $document->statusLabel() }}</span>
            <span class="text-body-secondary small">{{ $document->checklistProgress() }} checklist items complete</span>
            <span class="text-body-secondary small">&middot; {{ $document->buyer?->company_name }} ({{ $document->buyer?->display_code }})</span>
            @if($document->shipment_date)
                <span class="text-body-secondary small">&middot; {{ $document->shipment_date->format('d M Y') }}</span>
            @endif
        </div>

        @php
            $canEdit = auth()->user()->can('export-document.edit');
            $canGenerate = auth()->user()->can('export-document.generate');
            $ocrEnabled = filled(config('services.gemini.key'));
            $ocrTypes = \App\Services\Export\GeminiDocumentExtractor::SUPPORTED_TYPES;

            /**
             * One route per generated document type that actually has a PDF
             * template today — codes missing here still list their checklist
             * rows (so what's pending is visible at a glance) but show "Not
             * built yet" instead of a Generate button. Packing List's single
             * route takes the variant; the rest take none.
             */
            $generatorRoutes = [
                'delivery_challan' => fn ($variant) => route('export.documents.delivery-challan', $document),
                'e_invoice'        => fn ($variant) => route('export.documents.e-invoice', $document),
                'bl_draft'         => fn ($variant) => route('export.documents.bl-draft', $document),
                'packing_list'     => fn ($variant) => route('export.documents.packing-list', [$document, $variant]),
                'export_invoice'   => fn ($variant) => route('export.documents.export-invoice', [$document, $variant]),
                'item_summary'     => fn ($variant) => route('export.documents.item-summary', [$document, $variant]),
                'purchase_bills'   => fn ($variant) => route('export.documents.purchase-bills', [$document, $variant]),
                'vgm'              => fn ($variant) => route('export.documents.vgm', [$document, $variant]),
                'bank_docs'        => fn ($variant) => route('export.documents.bank-docs', [$document, $variant]),
                'buyer_docs'       => fn ($variant) => route('export.documents.buyer-docs', [$document, $variant]),
            ];

            /**
             * Section-wise grouping for the Generate Documents tab, mirroring
             * how the shipment paperwork is actually assembled: pack &
             * summarise, invoice & clear customs, hand to the shipping line,
             * settle with the bank/buyer. New generated-category checklist
             * types need only be added to a group here — no other markup
             * changes — to appear with a working Generate button once
             * $generatorRoutes gets an entry for them.
             */
            $generatorGroups = [
                'Packing & Item Docs'        => ['packing_list', 'item_summary'],
                'Invoicing & Customs Filing' => ['export_invoice', 'e_invoice', 'purchase_bills', 'delivery_challan'],
                'Shipping Line'              => ['vgm', 'bl_draft'],
                'Bank & Buyer Docs'          => ['bank_docs', 'buyer_docs'],
            ];

            $generatedEntries = $document->checklist
                ->filter(fn ($e) => $e->type?->category === 'generated')
                ->sortBy('id')
                ->groupBy('type.code');

            $generatedTotal = $generatedEntries->flatten(1)->count();
            $generatedPending = $generatedEntries->flatten(1)->where('status', 'pending')->count();

            $checklistRows = $document->checklist->whereIn('type.category', ['uploaded', 'manual'])->sortBy('id');
            $checklistPending = $checklistRows->where('status', 'pending')->count();
        @endphp

        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button" role="tab">
                    Overview
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-generate" type="button" role="tab">
                    Generate Documents
                    @if($generatedPending)
                        <span class="badge text-bg-secondary ms-1">{{ $generatedPending }}</span>
                    @endif
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-items" type="button" role="tab">
                    Items Shipped
                    @if($document->items->isNotEmpty())
                        <span class="badge text-bg-light text-body-secondary ms-1">{{ $document->items->count() }}</span>
                    @endif
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-checklist" type="button" role="tab">
                    Checklist
                    @if($checklistPending)
                        <span class="badge text-bg-secondary ms-1">{{ $checklistPending }}</span>
                    @endif
                </button>
            </li>
        </ul>

        <div class="tab-content">
            {{-- ================= Overview ================= --}}
            <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
                <dl class="row mb-4">
                    <dt class="col-sm-3 text-body-secondary fw-normal">Order Confirmation</dt>
                    <dd class="col-sm-9">
                        @if($document->orderConfirmation)
                            <a href="{{ route('sales.order-confirmations.show', $document->orderConfirmation) }}">{{ $document->orderConfirmation->oc_num }}</a>
                        @else — @endif
                    </dd>

                    <dt class="col-sm-3 text-body-secondary fw-normal">Buyer</dt>
                    <dd class="col-sm-9">{{ $document->buyer?->company_name }} ({{ $document->buyer?->display_code }})</dd>

                    <dt class="col-sm-3 text-body-secondary fw-normal">Currency / Incoterm</dt>
                    <dd class="col-sm-9">{{ $document->currency?->iso_code ?? '—' }} / {{ $document->incoterm?->code ?? '—' }}</dd>

                    <dt class="col-sm-3 text-body-secondary fw-normal">Shipment</dt>
                    <dd class="col-sm-9">
                        {{ $document->shipmentMethod?->name ?? '—' }}
                        @if($document->shipment_date) &middot; {{ $document->shipment_date->format('d M Y') }} @endif
                        @if($document->portOfLoading) &middot; POL: {{ $document->portOfLoading->name }} @endif
                        @if($document->portOfDischarge) &middot; POD: {{ $document->portOfDischarge->name }} @endif
                    </dd>

                    <dt class="col-sm-3 text-body-secondary fw-normal">Remarks</dt>
                    <dd class="col-sm-9">{{ $document->remarks ?: '—' }}</dd>
                </dl>

                <dl class="row mb-0 pt-3 border-top">
                    <dt class="col-sm-3 text-body-secondary fw-normal">Created</dt>
                    <dd class="col-sm-9 text-body-secondary small">
                        {{ $document->created_at?->format('d M Y, H:i') }}
                        @if($document->creator) by {{ $document->creator->name }} @endif
                    </dd>

                    <dt class="col-sm-3 text-body-secondary fw-normal">Last updated</dt>
                    <dd class="col-sm-9 text-body-secondary small">
                        {{ $document->updated_at?->format('d M Y, H:i') }}
                        @if($document->updater) by {{ $document->updater->name }} @endif
                    </dd>
                </dl>
            </div>

            {{-- ================= Generate Documents ================= --}}
            <div class="tab-pane fade" id="tab-generate" role="tabpanel">
                @php
                    $groupMeta = [
                        'Packing & Item Docs'        => ['accent' => 'primary', 'icon' => 'bi-box-seam'],
                        'Invoicing & Customs Filing' => ['accent' => 'info',    'icon' => 'bi-receipt'],
                        'Shipping Line'              => ['accent' => 'warning', 'icon' => 'bi-truck'],
                        'Bank & Buyer Docs'          => ['accent' => 'success', 'icon' => 'bi-bank'],
                    ];

                    $typeIcons = [
                        'packing_list'     => 'bi-box2',
                        'item_summary'     => 'bi-list-check',
                        'export_invoice'   => 'bi-file-earmark-spreadsheet',
                        'purchase_bills'   => 'bi-receipt-cutoff',
                        'delivery_challan' => 'bi-truck',
                        'vgm'              => 'bi-speedometer2',
                        'bl_draft'         => 'bi-file-earmark-text',
                        'e_invoice'        => 'bi-receipt',
                        'bank_docs'        => 'bi-bank',
                        'buyer_docs'       => 'bi-envelope-paper',
                    ];

                    $orderedCodes = $generatedEntries
                        ->map(fn ($entries) => $entries->first()->type)
                        ->sortBy('sort_order')
                        ->keys();

                    $typesByCode = $orderedCodes->mapWithKeys(fn ($code) => [$code => $generatedEntries->get($code)->first()->type]);
                @endphp

                <div class="gd-summary d-flex flex-wrap align-items-center gap-3 mb-4">
                    <div class="gd-summary-ring flex-shrink-0" style="--pct: {{ $generatedTotal ? round((($generatedTotal - $generatedPending) / $generatedTotal) * 100) : 0 }}">
                        <span>{{ $generatedTotal - $generatedPending }}/{{ $generatedTotal }}</span>
                    </div>
                    <div>
                        <div class="fw-semibold">Document generation progress</div>
                        <div class="text-body-secondary small">
                            {{ $generatedPending }} format{{ $generatedPending === 1 ? '' : 's' }} still pending across {{ $orderedCodes->count() }} document types.
                            "Not built yet" formats need to be recorded manually from the <strong>Checklist</strong> tab.
                        </div>
                    </div>
                </div>

                <div class="gd-groups d-flex flex-column gap-4">
                    @foreach($generatorGroups as $groupLabel => $codesInGroup)
                        @php
                            $codesPresent = collect($codesInGroup)->filter(fn ($c) => $orderedCodes->contains($c))->values();
                            if ($codesPresent->isEmpty()) continue;
                            $meta = $groupMeta[$groupLabel] ?? ['accent' => 'secondary', 'icon' => 'bi-folder'];
                        @endphp

                        <section class="gd-group">
                            <div class="gd-group-heading d-flex align-items-center gap-2 mb-3">
                                <span class="gd-group-dot bg-{{ $meta['accent'] }}"><i class="bi {{ $meta['icon'] }}"></i></span>
                                <h6 class="mb-0 text-uppercase fw-semibold small text-body-secondary" style="letter-spacing:.05em">{{ $groupLabel }}</h6>
                            </div>

                            <div class="d-flex flex-column gap-3">
                                @foreach($codesPresent as $code)
                                    @php
                                        $entries = $generatedEntries->get($code);
                                        $type = $typesByCode[$code];
                                        $accent = $meta['accent'];
                                        $icon = $typeIcons[$code] ?? 'bi-file-earmark';
                                        $panelId = 'gen-'.$code;
                                        $done = $entries->where('status', 'generated')->count();
                                        $total = $entries->count();
                                        $complete = $total > 0 && $done === $total;
                                    @endphp

                                    @php $isFirstCard = $loop->parent->first && $loop->first; @endphp
                                    <div class="gd-card">
                                        <button class="gd-card-header" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#{{ $panelId }}"
                                                aria-expanded="{{ $isFirstCard ? 'true' : 'false' }}" aria-controls="{{ $panelId }}">
                                            <span class="gd-card-icon bg-{{ $accent }}-subtle text-{{ $accent }}"><i class="bi {{ $icon }}"></i></span>
                                            <span class="gd-card-title">
                                                <span class="d-block fw-semibold">{{ $type->name }}</span>
                                                @if($type->description)
                                                    <span class="d-block text-body-secondary small fw-normal">{{ $type->description }}</span>
                                                @endif
                                            </span>
                                            <span class="gd-card-status badge {{ $complete ? 'text-bg-success-subtle text-success' : 'text-bg-light text-body-secondary' }}">
                                                @if($complete)<i class="bi bi-check-circle-fill me-1"></i>@endif
                                                {{ $done }} / {{ $total }} generated
                                            </span>
                                            <i class="bi bi-chevron-down gd-card-chevron"></i>
                                        </button>

                                        <div id="{{ $panelId }}" class="collapse {{ $isFirstCard ? 'show' : '' }}">
                                            <div class="gd-card-body">
                                                <div class="gd-format-grid">
                                                    @foreach($entries as $i => $entry)
                                                        <div class="gd-format {{ $entry->status === 'generated' ? 'is-done' : '' }}">
                                                            <div class="gd-format-label">
                                                                @if($entry->variantLabel())
                                                                    <span class="gd-format-letter text-{{ $accent }}">{{ chr(65 + $i) }}.</span> {{ $entry->variantLabel() }}
                                                                @else
                                                                    Standard Format
                                                                @endif
                                                            </div>

                                                            <span class="badge text-bg-{{ $entry->statusColor() }} gd-format-badge">{{ $entry->statusLabel() }}</span>

                                                            @if($entry->status === 'generated')
                                                                <div class="small text-body-secondary mt-1 mb-2">
                                                                    <i class="bi bi-clock-history me-1"></i>{{ $entry->generated_at?->format('d M Y, H:i') }}
                                                                </div>
                                                            @endif

                                                            <div class="mt-auto d-flex gap-2 pt-2">
                                                                @if($canGenerate && isset($generatorRoutes[$code]))
                                                                    <a href="{{ $generatorRoutes[$code]($entry->variant_code) }}"
                                                                       class="btn btn-sm {{ $entry->status === 'generated' ? 'btn-outline-secondary' : 'btn-'.$accent }} flex-fill"
                                                                       target="_blank">
                                                                        <i class="bi bi-file-earmark-pdf me-1"></i>
                                                                        {{ $entry->status === 'generated' ? 'Regenerate' : 'Generate' }}
                                                                    </a>
                                                                    @if($entry->hasFile())
                                                                        <a href="{{ $entry->fileUrl() }}" target="_blank" rel="noopener"
                                                                           class="btn btn-sm btn-outline-secondary" title="View generated file">
                                                                            <i class="bi bi-eye"></i>
                                                                        </a>
                                                                    @endif
                                                                @elseif(! isset($generatorRoutes[$code]))
                                                                    <span class="badge text-bg-light text-body-secondary d-block py-2 w-100">Not built yet</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>

            {{-- ================= Items Shipped ================= --}}
            <div class="tab-pane fade" id="tab-items" role="tabpanel">
                @if($document->items->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Design No.</th>
                                    <th>Product</th>
                                    <th>Colour / Size</th>
                                    <th>Unit</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($document->items as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td class="fw-semibold">{{ $item->design_no ?: '—' }}</td>
                                        <td>{{ $item->product?->name ?? '—' }}</td>
                                        <td>
                                            @forelse($item->colours as $colour)
                                                <div class="small mb-1">
                                                    @if($colour->colour)<span class="badge text-bg-light border me-1">{{ $colour->colour }}</span>@endif
                                                    @forelse($colour->sizes as $size)
                                                        <span class="text-body-secondary">{{ $size->size }}:{{ $size->qty }}</span>@if(! $loop->last), @endif
                                                    @empty
                                                        <span class="text-body-secondary">—</span>
                                                    @endforelse
                                                </div>
                                            @empty
                                                —
                                            @endforelse
                                        </td>
                                        <td>{{ $item->unit ?? '—' }}</td>
                                        <td class="text-end">{{ $item->qty }}</td>
                                        <td class="text-end">{{ number_format((float) $item->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="fw-semibold table-light">
                                    <td colspan="6" class="text-end">Total</td>
                                    <td class="text-end">{{ number_format($document->totalAmount(), 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <p class="text-body-secondary small mb-0">No items on this Export Document yet.</p>
                @endif
            </div>

            {{-- ================= Checklist ================= --}}
            <div class="tab-pane fade" id="tab-checklist" role="tabpanel">
                <p class="text-body-secondary small mb-3">
                    Upload and manual-record rows for this shipment. Uploading the eBRC row closes it. For B/L, LEO,
                    container/seal and a few bank docs, choose a file then use <strong>Extract with AI</strong> to
                    suggest the reference fields before Save. System-generated documents live on the
                    <strong>Generate Documents</strong> tab, not here.
                </p>

                @foreach(['uploaded' => 'Upload & Record Date', 'manual' => 'Manual / Record Only'] as $category => $groupLabel)
                    @php $rows = $document->checklist->where('type.category', $category)->sortBy('id'); @endphp
                    @continue($rows->isEmpty())

                    <h6 class="text-body-secondary small text-uppercase mt-4 mb-2">{{ $groupLabel }}</h6>
                    <div class="table-responsive mb-2">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:28%">Document</th>
                                    <th style="width:12%">Status</th>
                                    <th style="width:18%">File / Reference</th>
                                    <th style="width:14%">Date</th>
                                    @if($canEdit)
                                        <th>Update</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $entry)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $entry->type->name }}</div>
                                            @if($entry->variantLabel())
                                                <div class="small text-body-secondary">{{ $entry->variantLabel() }}</div>
                                            @endif
                                            @if($entry->type->description)
                                                <div class="small text-body-secondary">{{ $entry->type->description }}</div>
                                            @endif
                                        </td>
                                        <td><span class="badge text-bg-{{ $entry->statusColor() }}">{{ $entry->statusLabel() }}</span></td>
                                        <td class="small">
                                            @if($entry->hasFile())
                                                <a href="{{ $entry->fileUrl() }}" target="_blank" rel="noopener">
                                                    <i class="bi bi-paperclip me-1"></i>{{ $entry->original_name ?? 'File' }}
                                                </a><br>
                                            @endif
                                            {{ $entry->reference_no ?: ($entry->hasFile() ? '' : '—') }}
                                            @if($entry->matchedBuyer || $entry->matchedSupplier || $entry->matchedOrderConfirmation)
                                                <div class="mt-1 text-body-secondary">
                                                    @if($entry->matchedBuyer)
                                                        <div><i class="bi bi-person-badge me-1"></i>Buyer: {{ $entry->matchedBuyer->display_code }} — {{ $entry->matchedBuyer->company_name }}</div>
                                                    @endif
                                                    @if($entry->matchedSupplier)
                                                        <div><i class="bi bi-truck me-1"></i>Supplier: {{ $entry->matchedSupplier->display_code }} — {{ $entry->matchedSupplier->company_name }}</div>
                                                    @endif
                                                    @if($entry->matchedOrderConfirmation)
                                                        <div><i class="bi bi-journal-check me-1"></i>OC: {{ $entry->matchedOrderConfirmation->oc_num }}</div>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                        <td class="small">{{ ($entry->uploaded_at ?? $entry->generated_at)?->format('d M Y') ?? '—' }}</td>
                                        @if($canEdit)
                                            <td>
                                                <details>
                                                    <summary class="small text-primary" style="cursor:pointer">Update</summary>

                                                    @if($entry->type->code === 'insurance')
                                                        <div class="mt-2 small text-body-secondary mb-2">
                                                            Sheet #16 — cancel the draft, or upload the certificate with B/L details.
                                                        </div>

                                                        <form action="{{ route('export.documents.checklist.update', [$document, $entry]) }}"
                                                              method="POST" class="row g-2 mb-3">
                                                            @csrf
                                                            <input type="hidden" name="insurance_action" value="cancel_draft">
                                                            <input type="hidden" name="mark_done" value="1">
                                                            <div class="col-12">
                                                                <button class="btn btn-sm btn-outline-warning" type="submit"
                                                                        onclick="return confirm('Cancel / delete the insurance draft for this shipment?')">
                                                                    <i class="bi bi-x-circle me-1"></i> Option 1 — Cancel draft
                                                                </button>
                                                            </div>
                                                        </form>

                                                        <form action="{{ route('export.documents.checklist.update', [$document, $entry]) }}"
                                                              method="POST" enctype="multipart/form-data"
                                                              class="row g-2 js-checklist-form"
                                                              data-type-code="insurance"
                                                              data-ocr-url="{{ route('export.documents.checklist.ocr', [$document, $entry]) }}"
                                                              data-ocr-supported="1">
                                                            @csrf
                                                            <input type="hidden" name="insurance_action" value="upload_certificate">
                                                            <input type="hidden" name="mark_done" value="1">
                                                            <input type="hidden" name="matched_buyer_id" class="js-matched-buyer-id"
                                                                   value="{{ $entry->matched_buyer_id }}">
                                                            <input type="hidden" name="matched_supplier_id" class="js-matched-supplier-id"
                                                                   value="{{ $entry->matched_supplier_id }}">
                                                            <input type="hidden" name="matched_order_confirmation_id" class="js-matched-oc-id"
                                                                   value="{{ $entry->matched_order_confirmation_id }}">

                                                            <div class="col-md-4">
                                                                <label class="form-label small mb-0">Certificate file <span class="text-danger">*</span></label>
                                                                <input type="file" name="file" class="form-control form-control-sm js-ocr-file" required
                                                                       accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/*,application/pdf">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label small mb-0">B/L number <span class="text-danger">*</span></label>
                                                                <input type="text" name="bl_number" class="form-control form-control-sm"
                                                                       value="{{ old('bl_number', $entry->insuranceBlNumber()) }}" required
                                                                       placeholder="From final B/L">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label small mb-0">B/L date <span class="text-danger">*</span></label>
                                                                <input type="date" name="bl_date" class="form-control form-control-sm"
                                                                       value="{{ old('bl_date', $entry->insuranceBlDate()) }}" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label small mb-0">Policy / cert no.</label>
                                                                <input type="text" name="reference_no" value="{{ $entry->reference_no }}"
                                                                       class="form-control form-control-sm js-ocr-reference" placeholder="Filled by OCR">
                                                            </div>
                                                            <div class="col-md-5">
                                                                <label class="form-label small mb-0">Remarks</label>
                                                                <input type="text" name="remarks" value="{{ $entry->remarks }}"
                                                                       class="form-control form-control-sm js-ocr-remarks" placeholder="Extra notes">
                                                            </div>
                                                            <div class="col-md-3 d-flex flex-wrap gap-1 align-items-end">
                                                                <button type="button" class="btn btn-sm btn-outline-primary js-ocr-extract"
                                                                        @disabled(! $ocrEnabled)
                                                                        title="{{ $ocrEnabled ? 'Read the certificate with Gemini' : 'Set GEMINI_API_KEY in .env to enable' }}">
                                                                    <i class="bi bi-stars me-1"></i>Extract
                                                                </button>
                                                                <button class="btn btn-sm btn-primary" type="submit">
                                                                    Option 2 — Save certificate
                                                                </button>
                                                                @if($entry->status !== 'pending')
                                                                    <button class="btn btn-sm btn-outline-danger" type="submit"
                                                                            form="reset-checklist-{{ $entry->id }}">Reset</button>
                                                                @endif
                                                            </div>
                                                            <div class="col-12 small text-body-secondary js-ocr-status d-none"></div>
                                                            <div class="col-12 small js-ocr-parties d-none"></div>
                                                        </form>
                                                    @else
                                                    <form action="{{ route('export.documents.checklist.update', [$document, $entry]) }}"
                                                          method="POST" enctype="multipart/form-data"
                                                          class="row g-2 mt-2 js-checklist-form"
                                                          data-type-code="{{ $entry->type->code }}"
                                                          data-ocr-url="{{ route('export.documents.checklist.ocr', [$document, $entry]) }}"
                                                          data-ocr-supported="{{ in_array($entry->type->code, $ocrTypes, true) ? '1' : '0' }}">
                                                        @csrf
                                                        <input type="hidden" name="mark_done" value="1">
                                                        <input type="hidden" name="matched_buyer_id" class="js-matched-buyer-id"
                                                               value="{{ $entry->matched_buyer_id }}">
                                                        <input type="hidden" name="matched_supplier_id" class="js-matched-supplier-id"
                                                               value="{{ $entry->matched_supplier_id }}">
                                                        <input type="hidden" name="matched_order_confirmation_id" class="js-matched-oc-id"
                                                               value="{{ $entry->matched_order_confirmation_id }}">

                                                        @if($entry->type->requiresFile())
                                                            <div class="col-md-3">
                                                                <input type="file" name="file" class="form-control form-control-sm js-ocr-file"
                                                                       accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,image/*,application/pdf">
                                                            </div>
                                                        @endif
                                                        <div class="col-md-3">
                                                            <input type="text" name="reference_no" value="{{ $entry->reference_no }}"
                                                                   class="form-control form-control-sm js-ocr-reference" placeholder="Reference no.">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <input type="text" name="remarks" value="{{ $entry->remarks }}"
                                                                   class="form-control form-control-sm js-ocr-remarks" placeholder="Remarks">
                                                        </div>
                                                        <div class="col-md-3 d-flex flex-wrap gap-1">
                                                            @if($entry->type->requiresFile() && in_array($entry->type->code, $ocrTypes, true))
                                                                <button type="button" class="btn btn-sm btn-outline-primary js-ocr-extract"
                                                                        @disabled(! $ocrEnabled)
                                                                        title="{{ $ocrEnabled ? 'Read the selected file with Gemini' : 'Set GEMINI_API_KEY in .env to enable' }}">
                                                                    <i class="bi bi-stars me-1"></i>Extract with AI
                                                                </button>
                                                            @endif
                                                            <button class="btn btn-sm btn-primary" type="submit">Save</button>
                                                            @if($entry->status !== 'pending')
                                                                <button class="btn btn-sm btn-outline-danger" type="submit"
                                                                        form="reset-checklist-{{ $entry->id }}">Reset</button>
                                                            @endif
                                                        </div>
                                                        <div class="col-12 small text-body-secondary js-ocr-status d-none"></div>
                                                        <div class="col-12 small js-ocr-parties d-none"></div>
                                                    </form>
                                                    @endif

                                                    @if($entry->status !== 'pending')
                                                        <form id="reset-checklist-{{ $entry->id }}"
                                                              action="{{ route('export.documents.checklist.reset', [$document, $entry]) }}"
                                                              method="POST" class="d-none">
                                                            @csrf
                                                            @method('DELETE')
                                                        </form>
                                                    @endif
                                                </details>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        </div>
    </x-ui.card>

    @push('styles')
    <style>
        /* ---- Generate Documents tab ------------------------------------- */
        .gd-summary-ring {
            --pct: 0;
            --size: 3.25rem;
            width: var(--size);
            height: var(--size);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .7rem;
            font-weight: 600;
            color: var(--bs-body-color);
            background:
                radial-gradient(closest-side, var(--bs-body-bg) 72%, transparent 73% 100%),
                conic-gradient(var(--bs-primary) calc(var(--pct) * 1%), var(--bs-secondary-bg) 0);
        }

        .gd-group-dot {
            width: 1.75rem;
            height: 1.75rem;
            border-radius: .5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: .85rem;
            flex-shrink: 0;
        }

        .gd-card {
            border: 1px solid var(--bs-border-color);
            border-radius: .75rem;
            background: var(--bs-body-bg);
            overflow: hidden;
            transition: box-shadow .15s ease, border-color .15s ease;
        }

        .gd-card:hover {
            box-shadow: 0 2px 10px rgba(0, 0, 0, .05);
        }

        .gd-card-header {
            width: 100%;
            display: flex;
            align-items: center;
            gap: .875rem;
            padding: .9rem 1.1rem;
            background: transparent;
            border: 0;
            text-align: left;
            cursor: pointer;
        }

        .gd-card-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: .6rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .gd-card-title {
            flex: 1 1 auto;
            min-width: 0;
        }

        .gd-card-status {
            flex-shrink: 0;
            font-weight: 500;
        }

        .gd-card-chevron {
            flex-shrink: 0;
            color: var(--bs-secondary-color);
            transition: transform .2s ease;
        }

        .gd-card-header[aria-expanded="true"] .gd-card-chevron {
            transform: rotate(180deg);
        }

        .gd-card-body {
            padding: 0 1.1rem 1.1rem;
            border-top: 1px solid var(--bs-border-color-translucent);
            padding-top: 1rem;
        }

        .gd-format-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: .75rem;
        }

        .gd-format {
            border: 1px solid var(--bs-border-color);
            border-radius: .6rem;
            padding: .85rem;
            display: flex;
            flex-direction: column;
            background: var(--bs-tertiary-bg, rgba(0, 0, 0, .015));
            transition: border-color .15s ease;
        }

        .gd-format.is-done {
            border-color: var(--bs-success-border-subtle, #a3cfbb);
        }

        .gd-format-label {
            font-weight: 600;
            font-size: .85rem;
            margin-bottom: .4rem;
        }

        .gd-format-letter {
            font-weight: 700;
        }

        @media (max-width: 575.98px) {
            .gd-card-header {
                flex-wrap: wrap;
            }

            .gd-card-status {
                order: 3;
            }
        }
    </style>
    @endpush

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-checklist-form').forEach(function (form) {
            const btn = form.querySelector('.js-ocr-extract');
            if (! btn) return;

            const fileInput = form.querySelector('.js-ocr-file');
            const refInput = form.querySelector('.js-ocr-reference');
            const remarksInput = form.querySelector('.js-ocr-remarks');
            const statusEl = form.querySelector('.js-ocr-status');
            const partiesEl = form.querySelector('.js-ocr-parties');
            const buyerIdInput = form.querySelector('.js-matched-buyer-id');
            const supplierIdInput = form.querySelector('.js-matched-supplier-id');
            const ocIdInput = form.querySelector('.js-matched-oc-id');

            btn.addEventListener('click', async function () {
                if (! fileInput || ! fileInput.files.length) {
                    statusEl.classList.remove('d-none', 'text-success');
                    statusEl.classList.add('text-danger');
                    statusEl.textContent = 'Choose a PDF or image first.';
                    return;
                }

                const csrf = form.querySelector('input[name="_token"]')?.value
                    || document.querySelector('meta[name="csrf-token"]')?.content;

                const xsrfMatch = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
                const xsrf = xsrfMatch ? decodeURIComponent(xsrfMatch[1]) : '';

                const body = new FormData();
                body.append('file', fileInput.files[0]);
                body.append('type_code', form.dataset.typeCode);
                body.append('_token', csrf);

                btn.disabled = true;
                statusEl.classList.remove('d-none', 'text-danger', 'text-success');
                statusEl.classList.add('text-body-secondary');
                statusEl.textContent = 'Reading document with Gemini…';

                try {
                    const headers = {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    };
                    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
                    if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;

                    const res = await fetch(form.dataset.ocrUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers,
                        body,
                    });

                    const data = await res.json().catch(function () { return {}; });

                    if (res.status === 419) {
                        throw new Error('Session expired (CSRF). Refresh the page (Ctrl+F5) and try again.');
                    }
                    if (! res.ok) {
                        throw new Error(data.message || ('OCR failed (' + res.status + ')'));
                    }

                    if (data.reference_no != null && data.reference_no !== '') {
                        refInput.value = data.reference_no;
                    }
                    if (data.remarks != null && data.remarks !== '') {
                        remarksInput.value = data.remarks;
                    }

                    const blNumberInput = form.querySelector('[name="bl_number"]');
                    const blDateInput = form.querySelector('[name="bl_date"]');
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

                    const parties = data.parties || null;
                    if (buyerIdInput) buyerIdInput.value = parties?.buyer?.id || '';
                    if (supplierIdInput) supplierIdInput.value = parties?.supplier?.id || '';
                    if (ocIdInput) ocIdInput.value = parties?.order_confirmation?.id || '';
                    if (partiesEl) {
                        const bits = [];
                        if (parties?.buyer) {
                            bits.push('Buyer: ' + (parties.buyer.display_code || '') + ' — ' + parties.buyer.company_name);
                        } else if (parties?.buyer_name) {
                            bits.push('Buyer text: ' + parties.buyer_name + ' (no master match)');
                        }
                        if (parties?.supplier) {
                            bits.push('Supplier: ' + (parties.supplier.display_code || '') + ' — ' + parties.supplier.company_name);
                        } else if (parties?.supplier_name) {
                            bits.push('Supplier text: ' + parties.supplier_name + ' (no master match)');
                        }
                        if (parties?.order_confirmation) {
                            bits.push('OC: ' + parties.order_confirmation.oc_num
                                + (parties.order_confirmation.buyer_ref ? (' (' + parties.order_confirmation.buyer_ref + ')') : ''));
                        } else if (parties?.invoice_no) {
                            bits.push('Invoice ' + parties.invoice_no + ' (no OC match)');
                        }
                        if (data.order_context && data.order_context.summary) {
                            bits.push(data.order_context.summary);
                        }
                        if (data.order_context && data.order_context.payment) {
                            const pending = data.order_context.payment.pending_labels || [];
                            if (data.order_context.payment.realised) {
                                bits.push('Payment realised');
                            } else if (pending.length) {
                                bits.push('Payment pending: ' + pending.join(', '));
                            }
                        }
                        if (parties?.selected_buyer_matches === false) {
                            bits.push('Warning: scanned buyer differs from this Export Document buyer.');
                        } else if (parties?.selected_buyer_matches === true) {
                            bits.push('Export Document buyer matches the scan.');
                        }
                        if (parties?.selected_oc_matches === false) {
                            bits.push('Warning: matched OC differs from this shipment OC.');
                        } else if (parties?.selected_oc_matches === true) {
                            bits.push('Order Confirmation matches this shipment.');
                        }
                        partiesEl.classList.toggle('d-none', bits.length === 0);
                        partiesEl.className = 'col-12 small ' + ((parties?.selected_buyer_matches === false || parties?.selected_oc_matches === false) ? 'text-warning' : 'text-success');
                        partiesEl.textContent = bits.join(' · ');
                    }

                    statusEl.classList.remove('text-body-secondary', 'text-danger');
                    statusEl.classList.add('text-success');
                    statusEl.textContent = 'Fields filled + parties matched — review them, then click Save.';
                } catch (err) {
                    statusEl.classList.remove('text-body-secondary', 'text-success');
                    statusEl.classList.add('text-danger');
                    statusEl.textContent = err.message || 'OCR failed.';
                } finally {
                    btn.disabled = false;
                }
            });
        });
    });
    </script>
    @endpush
</x-app-layout>
