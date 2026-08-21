<x-app-layout>
    <x-slot name="header">Inquiry {{ $inquiry->inquiry_no }}</x-slot>

    <x-ui.card :title="$inquiry->inquiry_no" variant="primary">
        <x-slot name="actions">
            {{-- Posts to OrderConfirmationController::convertFromInquiry, gated
                 by order-confirmation.create there — the button is gated the
                 same way here so it never shows to someone who'd get a 403
                 on click. --}}
            @can('order-confirmation.create')
                @if($inquiry->status !== 'converted_to_oc' && $inquiry->items->where('status', 'confirmed')->isNotEmpty())
                    <form action="{{ route('sales.inquiries.convert-to-oc', $inquiry) }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="bi bi-arrow-right-circle me-1"></i> Convert to OC
                        </button>
                    </form>
                @endif
            @endcan
            @can('inquiry.edit')
                <a href="{{ route('sales.inquiries.edit', $inquiry) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
            <a href="{{ route('sales.inquiries.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <x-order-context-modal :order-context="$orderContext ?? ['available' => false]" modal-id="inquiryOrderContextModal" />
        </x-slot>

        <dl class="row mb-4">
            <dt class="col-sm-3 text-body-secondary fw-normal">Status</dt>
            <dd class="col-sm-9">
                <span class="badge text-bg-{{ $inquiry->statusColor() }}">{{ $inquiry->statusLabel() }}</span>
            </dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Inquiry Date</dt>
            <dd class="col-sm-9">{{ $inquiry->inquiry_date?->format('d M Y') }}</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Buyer's Ref / Season</dt>
            <dd class="col-sm-9">{{ $inquiry->buyer_ref ?: '—' }}</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Source</dt>
            <dd class="col-sm-9">{{ $inquiry->sourceLabel() }}</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Buyer</dt>
            <dd class="col-sm-9">{{ $inquiry->buyer?->company_name }} ({{ $inquiry->buyer?->display_code }})</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Category</dt>
            <dd class="col-sm-9">{{ $inquiry->category?->name ?? '—' }}</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Order Format</dt>
            <dd class="col-sm-9">{{ $inquiry->format?->name ?? '—' }} <span class="text-body-secondary">({{ $inquiry->format?->module }})</span></dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Agent</dt>
            <dd class="col-sm-9">
                {{ $inquiry->agent?->name ?? '—' }}
                @if($inquiry->agent_commission_value)
                    <span class="text-body-secondary">
                        ({{ rtrim(rtrim(number_format((float) $inquiry->agent_commission_value, 4, '.', ''), '0'), '.') }}{{ $inquiry->agent_commission_type === 'percent' ? '%' : ' '.$inquiry->currency?->iso_code }})
                    </span>
                @endif
            </dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Currency / Exchange Rate</dt>
            <dd class="col-sm-9">{{ $inquiry->currency?->iso_code ?? '—' }} @if($inquiry->exchange_rate) &middot; {{ $inquiry->exchange_rate }} @endif</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Expected Shipment</dt>
            <dd class="col-sm-9">{{ $inquiry->expected_shipment_date?->format('d M Y') ?? '—' }}</dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Remarks</dt>
            <dd class="col-sm-9">{{ $inquiry->remarks ?: '—' }}</dd>
        </dl>

        <h6 class="fw-semibold mb-2">Items &amp; Costing</h6>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Design No.</th>
                        <th>Product</th>
                        <th>Supplier</th>
                        <th>Colour / Size Breakdown</th>
                        <th>Unit</th>
                        <th>FOB</th>
                        <th class="text-end">Price</th>
                        <th class="text-end text-body-secondary">Cost Price <span class="fw-normal small">(internal)</span></th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($inquiry->items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td class="fw-semibold">
                                {{ $item->design_no ?: '—' }}
                                @if($item->custom_values)
                                    <div class="small text-body-secondary mt-1">
                                        @foreach($item->custom_values as $key => $value)
                                            @if(filled($value))
                                                <div>{{ \Illuminate\Support\Str::headline($key) }}: {{ $value }}</div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>{{ $item->product?->name ?? '—' }}</td>
                            <td>{{ $item->supplier?->company_name ?? '—' }}</td>
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
                            <td>{{ $item->fobValue?->name ?? '—' }}</td>
                            <td class="text-end">{{ $item->price !== null ? number_format((float) $item->price, 2) : '—' }}</td>
                            <td class="text-end text-body-secondary">{{ $item->cost_price !== null ? number_format((float) $item->cost_price, 2) : '—' }}</td>
                            <td class="text-end">{{ $item->qty }}</td>
                            <td class="text-end">{{ number_format((float) $item->amount, 2) }}</td>
                            <td><span class="badge text-bg-{{ $item->statusColor() }}">{{ $item->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="12" icon="bi-box-seam" title="No items added" />
                    @endforelse
                </tbody>
                @if($inquiry->items->isNotEmpty())
                    <tfoot>
                        <tr class="fw-semibold table-light">
                            <td colspan="10" class="text-end">Total</td>
                            <td class="text-end">{{ number_format($inquiry->totalAmount(), 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <h6 class="fw-semibold mb-2">Delivery details</h6>
                <div class="border rounded p-3 bg-body-tertiary small" style="min-height:5rem">
                    {{ $inquiry->delivery_details ?: 'None recorded.' }}
                </div>
            </div>
            <div class="col-lg-6">
                <h6 class="fw-semibold mb-2">Packing details</h6>
                <div class="border rounded p-3 bg-body-tertiary small" style="min-height:5rem">
                    {{ $inquiry->packing_details ?: 'None recorded.' }}
                </div>
            </div>
        </div>

        <h6 class="fw-semibold mb-2">Buyer Follow-up</h6>
        @forelse($inquiry->followUps as $followUp)
            <div class="d-flex gap-3 mb-2 small">
                <div class="text-body-secondary" style="min-width:6.5rem">{{ $followUp->follow_up_date?->format('d M Y') }}</div>
                <div class="flex-grow-1">{{ $followUp->comment }}</div>
                <div class="text-body-secondary">{{ $followUp->creator?->name }}</div>
            </div>
        @empty
            <p class="text-body-secondary small">No follow-up entries recorded.</p>
        @endforelse

        <dl class="row mb-0 mt-4 pt-3 border-top">
            <dt class="col-sm-3 text-body-secondary fw-normal">Created</dt>
            <dd class="col-sm-9 text-body-secondary small">
                {{ $inquiry->created_at?->format('d M Y, H:i') }}
                @if($inquiry->creator) by {{ $inquiry->creator->name }} @endif
            </dd>

            <dt class="col-sm-3 text-body-secondary fw-normal">Last updated</dt>
            <dd class="col-sm-9 text-body-secondary small">
                {{ $inquiry->updated_at?->format('d M Y, H:i') }}
                @if($inquiry->updater) by {{ $inquiry->updater->name }} @endif
            </dd>
        </dl>
    </x-ui.card>
</x-app-layout>
