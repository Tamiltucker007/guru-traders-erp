@php
    $ctx = $orderContext ?? ['available' => false];
@endphp

<div class="border rounded-3 p-3" id="ocr-order-context-inner">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
        <div class="fw-semibold">
            <i class="bi bi-diagram-3 me-1"></i> Order context for this shipment
        </div>
        <div class="small text-body-secondary" id="ocr-ctx-summary">
            {{ $ctx['summary'] ?? '' }}
        </div>
    </div>

    @if(! ($ctx['available'] ?? false))
        <div class="small text-body-secondary" id="ocr-ctx-empty">Select an Export Document to load order details.</div>
    @else
        <div class="row g-3 small" id="ocr-ctx-body">
            <div class="col-md-3">
                <div class="text-body-secondary">Export Document</div>
                <div class="fw-semibold" id="ocr-ctx-doc">
                    {{ $ctx['export_document']['doc_num'] ?? '—' }}
                    @if(! empty($ctx['export_document']['invoice_no']))
                        <div class="fw-normal text-body-secondary">Invoice {{ $ctx['export_document']['invoice_no'] }}</div>
                    @endif
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-body-secondary">Order Confirmation</div>
                <div class="fw-semibold" id="ocr-ctx-oc">
                    {{ $ctx['order_confirmation']['oc_num'] ?? '—' }}
                    @if(! empty($ctx['order_confirmation']['buyer_ref']))
                        <div class="fw-normal text-body-secondary">Ref {{ $ctx['order_confirmation']['buyer_ref'] }}</div>
                    @endif
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-body-secondary">Buyer / payment terms</div>
                <div class="fw-semibold" id="ocr-ctx-buyer">
                    @if(! empty($ctx['buyer']))
                        {{ $ctx['buyer']['display_code'] }} — {{ $ctx['buyer']['company_name'] }}
                        <div class="fw-normal text-body-secondary">
                            {{ $ctx['buyer']['payment_term'] ?? 'No payment term' }}
                            @if(($ctx['buyer']['payment_term_days'] ?? null) !== null)
                                ({{ $ctx['buyer']['payment_term_days'] }} days)
                            @endif
                            @if($ctx['buyer']['advance_percent'] !== null)
                                · Adv {{ $ctx['buyer']['advance_percent'] }}%
                            @endif
                            @if($ctx['buyer']['sight_percent'] !== null)
                                · Sight {{ $ctx['buyer']['sight_percent'] }}%
                            @endif
                        </div>
                    @else
                        —
                    @endif
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-body-secondary">Shipment amount</div>
                <div class="fw-semibold" id="ocr-ctx-amount">
                    @php
                        $amt = $ctx['export_document']['amount'] ?? ($ctx['order_confirmation']['amount'] ?? null);
                        $cur = $ctx['export_document']['currency'] ?? ($ctx['order_confirmation']['currency'] ?? '');
                    @endphp
                    {{ $amt !== null ? number_format((float) $amt, 2).($cur ? ' '.$cur : '') : '—' }}
                </div>
            </div>

            <div class="col-md-6">
                <div class="text-body-secondary mb-1">Suppliers on this order</div>
                <ul class="mb-0 ps-3" id="ocr-ctx-suppliers">
                    @forelse(($ctx['suppliers'] ?? []) as $supplier)
                        <li>
                            {{ $supplier['display_code'] }} — {{ $supplier['company_name'] }}
                            @if($supplier['discount_percent'] !== null)
                                <span class="text-body-secondary">(disc {{ $supplier['discount_percent'] }}%)</span>
                            @endif
                        </li>
                    @empty
                        <li class="text-body-secondary">No supplier linked on OC / export lines yet.</li>
                    @endforelse
                </ul>
            </div>

            <div class="col-md-6">
                <div class="text-body-secondary mb-1">Payment verification</div>
                <div id="ocr-ctx-payment">
                    @php $pay = $ctx['payment'] ?? []; @endphp
                    <div>Swift / Payment:
                        <span class="badge text-bg-{{ ($pay['payment_received']['status'] ?? 'pending') === 'pending' ? 'secondary' : 'success' }}">
                            {{ $pay['payment_received']['label'] ?? 'Pending' }}
                        </span>
                        @if(! empty($pay['payment_received']['reference']))
                            <span class="text-body-secondary">{{ $pay['payment_received']['reference'] }}</span>
                        @endif
                    </div>
                    <div class="mt-1">EEFC:
                        <span class="badge text-bg-{{ ($pay['eefc_upload']['status'] ?? 'pending') === 'pending' ? 'secondary' : 'success' }}">
                            {{ $pay['eefc_upload']['label'] ?? 'Pending' }}
                        </span>
                    </div>
                    <div class="mt-1">eBRC:
                        <span class="badge text-bg-{{ ($pay['ebrc']['status'] ?? 'pending') === 'pending' ? 'secondary' : 'success' }}">
                            {{ $pay['ebrc']['label'] ?? 'Pending' }}
                        </span>
                    </div>
                    @if(! empty($pay['due_note']))
                        <div class="mt-1 text-body-secondary" id="ocr-ctx-due">{{ $pay['due_note'] }}</div>
                    @endif
                    @if(! empty($pay['pending_labels']))
                        <div class="text-warning mt-1">Still pending: {{ implode(', ', $pay['pending_labels']) }}</div>
                    @elseif(! empty($pay['realised']))
                        <div class="text-success mt-1">Payment looks realised for this order.</div>
                    @endif
                </div>
            </div>

            <div class="col-md-6">
                <div class="text-body-secondary mb-1">Jobbers</div>
                <ul class="mb-0 ps-3" id="ocr-ctx-jobbers">
                    @forelse(($ctx['jobbers'] ?? []) as $jobber)
                        <li>
                            {{ $jobber['display_code'] }} — {{ $jobber['company_name'] }}
                            @if(! empty($jobber['via_label']))
                                <span class="text-body-secondary">({{ $jobber['via_label'] }})</span>
                            @endif
                        </li>
                    @empty
                        <li class="text-body-secondary">No jobber linked to this buyer / products yet.</li>
                    @endforelse
                </ul>
            </div>

            <div class="col-md-6">
                <div class="text-body-secondary mb-1">Inventory (PO / Inward)</div>
                <div class="table-responsive" id="ocr-ctx-inventory-wrap">
                    <table class="table table-sm mb-0 align-middle" id="ocr-ctx-inventory">
                        <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Order</th>
                            <th class="text-end">PO</th>
                            <th class="text-end">Received</th>
                            <th class="text-end">Balance</th>
                            <th class="text-end">QC passed (all)</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse(($ctx['inventory']['rows'] ?? []) as $inv)
                            <tr>
                                <td>{{ $inv['product'] ?: '—' }}</td>
                                <td class="text-end">{{ number_format((float) $inv['order_qty'], 2) }}</td>
                                <td class="text-end">{{ number_format((float) $inv['po_ordered_qty'], 2) }}</td>
                                <td class="text-end">{{ number_format((float) $inv['received_qty'], 2) }}</td>
                                <td class="text-end">{{ number_format((float) $inv['balance_to_receive'], 2) }}</td>
                                <td class="text-end">{{ number_format((float) $inv['global_passed_qty'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-body-secondary">No product qty to show yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    @if(! empty($ctx['inventory']['note']))
                        <div class="text-body-secondary mt-1" id="ocr-ctx-inventory-note">{{ $ctx['inventory']['note'] }}</div>
                    @endif
                </div>
            </div>

            <div class="col-md-6">
                <div class="text-body-secondary mb-1">Price / profit (auto Markup)</div>
                <div id="ocr-ctx-pricing">
                    @php $pricing = $ctx['pricing'] ?? []; @endphp
                    @if(($pricing['sample_profit'] ?? null) !== null)
                        Markup {{ $pricing['markup_percent'] }}% ·
                        Client {{ number_format($pricing['sample_client_price'], 2) }} ·
                        Our cost {{ number_format($pricing['sample_our_cost'], 2) }} ·
                        Profit {{ number_format($pricing['sample_profit'], 2) }}
                        @if(($pricing['total_line_profit'] ?? null) !== null)
                            · Line total profit {{ number_format($pricing['total_line_profit'], 2) }}
                        @endif
                        <div class="text-body-secondary">{{ $pricing['note'] ?? '' }}</div>
                    @else
                        <span class="text-body-secondary">{{ $pricing['note'] ?? 'No markup sample yet.' }}</span>
                    @endif
                </div>
            </div>

            <div class="col-md-6">
                <div class="text-body-secondary mb-1">Minimum available price (multi supplier / buyer)</div>
                <div class="table-responsive" id="ocr-ctx-min-wrap">
                    <table class="table table-sm mb-0 align-middle" id="ocr-ctx-min-prices">
                        <thead>
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Min cost</th>
                            <th>Best supplier</th>
                            <th class="text-end">Min list</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse(($ctx['min_prices'] ?? []) as $mp)
                            <tr>
                                <td>{{ $mp['product'] ?: '—' }}</td>
                                <td class="text-end">
                                    {{ ($mp['min_cost_price'] ?? null) !== null ? number_format((float) $mp['min_cost_price'], 2) : '—' }}
                                </td>
                                <td>{{ $mp['min_cost_supplier'] ?: '—' }}</td>
                                <td class="text-end">
                                    {{ ($mp['min_list_price'] ?? null) !== null ? number_format((float) $mp['min_list_price'], 2) : '—' }}
                                    @if(! empty($mp['min_list_party']))
                                        <div class="text-body-secondary fw-normal">{{ $mp['min_list_party'] }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-body-secondary">Link products on OC lines to compare supplier prices.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-12">
                <div class="text-body-secondary mb-1">OC lines — auto price calc</div>
                <div class="table-responsive" id="ocr-ctx-lines-wrap">
                    <table class="table table-sm mb-0 align-middle" id="ocr-ctx-lines">
                        <thead>
                        <tr>
                            <th>Item</th>
                            <th>Supplier</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">List / Cost</th>
                            <th class="text-end">Client</th>
                            <th class="text-end">Our cost</th>
                            <th class="text-end">Profit</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse(($ctx['line_calcs'] ?? ($ctx['lines'] ?? [])) as $line)
                            <tr>
                                <td>{{ $line['product'] ?: '—' }}</td>
                                <td>{{ $line['supplier'] ?: '—' }}</td>
                                <td class="text-end">{{ number_format((float) ($line['qty'] ?? 0), 2) }} {{ $line['unit'] ?? '' }}</td>
                                <td class="text-end">
                                    {{ number_format((float) ($line['list_price'] ?? $line['price'] ?? 0), 2) }}
                                    @if(($line['cost_price'] ?? null) !== null)
                                        <div class="text-body-secondary">cost {{ number_format((float) $line['cost_price'], 2) }}</div>
                                    @endif
                                </td>
                                <td class="text-end">{{ ($line['client_price'] ?? null) !== null ? number_format((float) $line['client_price'], 2) : '—' }}</td>
                                <td class="text-end">{{ ($line['our_cost'] ?? null) !== null ? number_format((float) $line['our_cost'], 2) : '—' }}</td>
                                <td class="text-end">
                                    @if(($line['unit_profit'] ?? null) !== null)
                                        {{ number_format((float) $line['unit_profit'], 2) }}
                                        @if(($line['line_profit'] ?? null) !== null)
                                            <div class="text-body-secondary">line {{ number_format((float) $line['line_profit'], 2) }}</div>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-body-secondary">No OC lines loaded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
