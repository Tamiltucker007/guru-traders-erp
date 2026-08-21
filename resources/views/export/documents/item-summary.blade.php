<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $document->doc_num }} Item Summary — {{ $variantTitle }}</title>
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    .frame, .frame td { border: 1px solid #000; }
    .frame td { padding: 5px 7px; vertical-align: top; }
    .title { text-align: center; font-size: 14px; font-weight: bold; margin: 0 0 4px; }
    .subtitle { text-align: center; font-size: 9px; margin: 0 0 8px; color: #555; }
    .items th, .items td { border: 1px solid #000; padding: 4px 6px; font-size: 9px; }
    .items th { background: #f2f2f2; text-align: left; }
    .right { text-align: right; }
    .center { text-align: center; }
    .small { font-size: 8.5px; }
    .lbl { font-size: 8.5px; text-transform: uppercase; color: #555; }
</style>
</head>
<body>

    <x-pdf.letterhead :company="$company" title="Item Summary" :subtitle="$variantTitle.' — '.$document->doc_num" />

    <p class="title">ITEM SUMMARY</p>
    <p class="subtitle">{{ $variantTitle }} — {{ $document->doc_num }}</p>

    <table class="frame" style="margin-bottom:8px">
        <tr>
            <td style="width:50%">
                <div class="lbl">Exporter</div>
                <strong>{{ $company->company_name }}</strong>
            </td>
            <td style="width:25%">
                <div class="lbl">Invoice No.</div>
                {{ $document->invoice_no ?: $document->doc_num }}
            </td>
            <td style="width:25%">
                <div class="lbl">Invoice Date</div>
                {{ ($document->invoice_date ?? $document->shipment_date)?->format('d-M-Y') ?? '—' }}
            </td>
        </tr>
        <tr>
            <td>
                <div class="lbl">Buyer</div>
                {{ $document->buyer?->company_name ?? '—' }}
            </td>
            <td colspan="2">
                <div class="lbl">Destination</div>
                {{ $document->final_destination ?: ($document->portOfDischarge?->name ?? '—') }}
            </td>
        </tr>
    </table>

    @if($variant === 'raw-data-all-details')
        {{-- Format A: Raw Data — all items with full details --}}
        <table class="items">
            <thead>
                <tr>
                    <th style="width:4%">Sr</th>
                    <th style="width:10%">Design No.</th>
                    <th>Description</th>
                    <th style="width:12%">Supplier</th>
                    <th style="width:8%">HSN</th>
                    <th style="width:7%" class="center">Qty</th>
                    <th style="width:6%" class="center">Unit</th>
                    <th style="width:10%" class="right">Rate</th>
                    <th style="width:12%" class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @php $total = 0; $sr = 0; @endphp
                @foreach($document->items as $item)
                    @php $total += (float) $item->amount; @endphp
                    <tr>
                        <td class="center">{{ ++$sr }}</td>
                        <td>{{ $item->design_no ?: '—' }}</td>
                        <td>{{ $item->description }}</td>
                        <td class="small">{{ $item->sourceItem?->supplier?->company_name ?? '—' }}</td>
                        <td>{{ $item->product?->hsn_code ?? '—' }}</td>
                        <td class="center">{{ $item->qty }}</td>
                        <td class="center">{{ $item->unit ?? '—' }}</td>
                        <td class="right">{{ number_format((float) $item->price, 2) }}</td>
                        <td class="right">{{ number_format((float) $item->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="8" class="right"><strong>Total</strong></td>
                    <td class="right"><strong>{{ number_format($total, 2) }}</strong></td>
                </tr>
            </tfoot>
        </table>

    @elseif(str_starts_with($variant, 'split-by-description-price-band'))
        {{-- Format B: Split by Description & Price Band --}}
        @php
            $grouped = $document->items->groupBy(fn ($item) => ($item->description ?? 'Other').' @ '.number_format((float) $item->price, 2));
        @endphp
        @foreach($grouped as $band => $items)
            <h4 style="font-size:11px; margin:10px 0 4px;">{{ $band }}</h4>
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:5%">Sr</th>
                        <th style="width:14%">Design No.</th>
                        <th>Colour / Size</th>
                        <th style="width:8%" class="center">Qty</th>
                        <th style="width:12%" class="right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @php $sr = 0; $bandTotal = 0; @endphp
                    @foreach($items as $item)
                        @php $bandTotal += (float) $item->amount; @endphp
                        <tr>
                            <td class="center">{{ ++$sr }}</td>
                            <td>{{ $item->design_no ?: '—' }}</td>
                            <td class="small">
                                @foreach($item->colours as $colour)
                                    {{ $colour->colour }}@if($colour->sizes->isNotEmpty()): @foreach($colour->sizes as $size){{ $size->size }}({{ $size->qty }})@if(!$loop->last), @endif @endforeach @endif
                                    @if(!$loop->last) | @endif
                                @endforeach
                            </td>
                            <td class="center">{{ $item->qty }}</td>
                            <td class="right">{{ number_format((float) $item->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="right"><strong>Sub-Total</strong></td>
                        <td class="right"><strong>{{ number_format($bandTotal, 2) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        @endforeach

    @else
        {{-- Format C: Supplier-wise Split --}}
        @php
            $bySupplier = $document->items->groupBy(fn ($item) => $item->sourceItem?->supplier?->company_name ?? 'Direct / No Supplier');
        @endphp
        @foreach($bySupplier as $supplierName => $items)
            <h4 style="font-size:11px; margin:10px 0 4px;">{{ $supplierName }}</h4>
            <table class="items">
                <thead>
                    <tr>
                        <th style="width:5%">Sr</th>
                        <th style="width:14%">Design No.</th>
                        <th>Description</th>
                        <th style="width:8%" class="center">Qty</th>
                        <th style="width:8%" class="center">Unit</th>
                        <th style="width:10%" class="right">Rate</th>
                        <th style="width:12%" class="right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @php $sr = 0; $supTotal = 0; @endphp
                    @foreach($items as $item)
                        @php $supTotal += (float) $item->amount; @endphp
                        <tr>
                            <td class="center">{{ ++$sr }}</td>
                            <td>{{ $item->design_no ?: '—' }}</td>
                            <td>{{ $item->description }}</td>
                            <td class="center">{{ $item->qty }}</td>
                            <td class="center">{{ $item->unit ?? '—' }}</td>
                            <td class="right">{{ number_format((float) $item->price, 2) }}</td>
                            <td class="right">{{ number_format((float) $item->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="right"><strong>Sub-Total</strong></td>
                        <td class="right"><strong>{{ number_format($supTotal, 2) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        @endforeach
    @endif

    <x-pdf.footer :company="$company" />

</body>
</html>
