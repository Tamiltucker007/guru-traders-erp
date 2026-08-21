<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $document->doc_num }} Packing List</title>
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    .frame, .frame td { border: 1px solid #000; }
    .frame td { padding: 5px 7px; vertical-align: top; }
    .title { text-align: center; font-size: 15px; font-weight: bold; margin: 0 0 10px; }
    .lbl { font-size: 8.5px; text-transform: uppercase; letter-spacing: .02em; color: #555; }
    .pre { white-space: pre-line; }
    .right { text-align: right; }
    .text-center { text-align: center; }
    .items th, .items td { border: 1px solid #000; padding: 4px 6px; font-size: 9px; }
    .items th { background: #f2f2f2; text-align: left; }
    .supplier th, .supplier td { border: 1px solid #000; padding: 4px 6px; font-size: 9px; }
    .supplier th { background: #f2f2f2; text-align: left; }
    .totals td { border: 1px solid #000; padding: 4px 7px; font-size: 9.5px; }
    .sign-block { margin-top: 10px; }
    .small { font-size: 8.5px; }
</style>
</head>
<body>

    <x-pdf.letterhead :company="$company" title="Packing List" subtitle="For Our Record (with Supplier)" />

    <table class="frame" style="margin-bottom:8px">
        <tr>
            <td style="width:55%">
                <div class="lbl">Exporter</div>
                <strong>{{ $company->company_name }}</strong>
                <div class="pre">{{ $company->address }}</div>
            </td>
            <td style="width:45%">
                <div class="lbl">Invoice No. &amp; Date</div>
                {{ $document->invoice_no ?: $document->doc_num }} &nbsp;
                {{ ($document->invoice_date ?? $document->shipment_date)?->format('d-M-y') ?? '—' }}
                <div class="lbl" style="margin-top:4px">Buyer</div>
                {{ $document->buyer?->company_name ?? '—' }}
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:8%">#</th>
                <th>Description</th>
                <th style="width:14%">Unit</th>
                <th style="width:14%" class="text-center">Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach($document->items as $item)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $item->design_no ? "{$item->design_no} — " : '' }}{{ $item->description }}</td>
                    <td>{{ $item->unit ?? '—' }}</td>
                    <td class="text-center">{{ $item->qty }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        // Supplier is read off each item's own source OC line, not this
        // document's items directly — an Export Document item has no
        // supplier column of its own, same reasoning as
        // ExportDocumentCartonLine's free-text description.
        $bySupplier = $document->items
            ->groupBy(fn ($item) => $item->sourceItem?->supplier?->label ?? 'Unassigned')
            ->map(fn ($items) => $items->sum('qty'));
    @endphp

    <table class="supplier" style="margin-top:10px">
        <thead>
            <tr>
                <th>Supplier</th>
                <th style="width:20%" class="text-center">Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bySupplier as $supplier => $qty)
                <tr>
                    <td>{{ $supplier }}</td>
                    <td class="text-center">{{ $qty }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top:10px; width:50%">
        <tr>
            <td class="lbl">Total Quantity</td>
            <td class="right">{{ $document->items->sum('qty') }}</td>
        </tr>
        <tr>
            <td class="lbl">Total Net Weight</td>
            <td class="right">{{ $document->net_weight !== null ? number_format((float) $document->net_weight, 3) : '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Total Gross Weight</td>
            <td class="right">{{ $document->gross_weight !== null ? number_format((float) $document->gross_weight, 3) : '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Carton / Bale Dimension</td>
            <td class="right">{{ $document->carton_dimensions ?: '—' }}</td>
        </tr>
    </table>

    <table style="margin-top:20px">
        <tr>
            <td class="right sign-block" style="border:none">
                For {{ $company->company_name }}<br><br><br>
                <span class="small">{{ $company->signatory_name ?: 'Authorised Signatory' }}</span>
                @if($company->signatory_designation)
                    <br><span class="small">{{ $company->signatory_designation }}</span>
                @endif
            </td>
        </tr>
    </table>

    <x-pdf.footer :company="$company" />

</body>
</html>
