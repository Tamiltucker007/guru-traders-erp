<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $document->doc_num }} Delivery Challan</title>
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    .frame, .frame td { border: 1px solid #000; }
    .frame td { padding: 5px 7px; vertical-align: top; }
    .no-border, .no-border td { border: none; padding: 0; }
    .title { text-align: center; font-size: 15px; font-weight: bold; }
    .right { text-align: right; }
    .lbl { font-size: 9px; text-transform: uppercase; letter-spacing: .02em; color: #555; }
    .pre { white-space: pre-line; }
    .cha-box { background: #fbeceb; }
    .items th, .items td { border: 1px solid #000; padding: 4px 6px; font-size: 9px; }
    .items th { background: #f2f2f2; text-align: left; }
    .items .cat-row td { font-weight: bold; background: #f7f7f7; }
    .text-center { text-align: center; }
</style>
</head>
<body>

    <x-pdf.letterhead :company="$company" title="Delivery Challan" subtitle="Original for Recipient" />

    <table class="frame" style="margin-bottom:0">
        <tr>
            <td class="title" style="border-bottom:none">DELIVERY CHALLAN</td>
            <td class="right" style="border-bottom:none; width:160px;">ORIGINAL FOR RECIPIENT</td>
        </tr>
    </table>

    <table style="margin-top:0">
        <tr>
            <td style="width:58%; vertical-align:top; padding:0;">
                <table class="frame" style="border-top:none">
                    <tr>
                        <td>
                            <div class="lbl">Exporter</div>
                            <strong>{{ $company->company_name }}</strong>
                            <div class="pre">{{ $company->address }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="lbl">Consignee</div>
                            <div>{{ $document->buyer?->company_name }}</div>
                            <div class="pre">{{ $document->buyer?->address }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="width:50%">
                            <div class="lbl">Pre Carriage by</div>
                            {{ $document->shipmentMethod?->name ?? '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="lbl">Mode of Transport</div>
                            {{ $document->shipmentMethod?->name ?? '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="lbl">Port of Loading</div>
                            {{ $document->portOfLoading?->name ?? '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="lbl">Port of Discharge</div>
                            {{ $document->portOfDischarge?->name ?? '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="lbl">Final Destination</div>
                            {{ $document->final_destination ?: ($document->portOfDischarge?->name ?? '—') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="width:65%">
                            <div class="lbl">Marks &amp; Nos.</div>
                            <div class="pre">{{ $document->marks_and_numbers ?: '—' }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="lbl">No. &amp; Kind of Pkgs.</div>
                            {{ $document->total_cartons ? "TOTAL {$document->total_cartons} {$document->package_kind}" : '—' }}
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width:42%; vertical-align:top; padding:0 0 0 8px;">
                <table class="frame">
                    <tr>
                        <td>
                            <div class="lbl">Invoice No. &amp; Date</div>
                            {{ $document->invoice_no ?: $document->doc_num }}
                            DATED {{ ($document->invoice_date ?? $document->shipment_date)?->format('d.m.Y') ?? '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="lbl">Exporter's Ref</div>
                            {{ $document->exporter_ref ?: ($document->orderConfirmation?->buyer_ref ?? '—') }}
                        </td>
                    </tr>
                    <tr>
                        <td class="cha-box">
                            @if($document->total_cartons)
                                <strong>{{ $document->total_cartons }} {{ $document->package_kind }} DESPATCHED TO {{ $document->forwarder_name ? 'CHA' : '—' }}</strong><br><br>
                            @endif
                            @if($document->forwarder_name)
                                {{ $document->forwarder_name }}<br>
                                <span class="pre">{{ $document->forwarder_address }}</span><br><br>
                            @endif
                            @if($document->vehicle_no)
                                TEMPO/VEHICLE NO. {{ $document->vehicle_no }}<br>
                            @endif
                            @if($document->driver_cell)
                                DRIVER CELL NO. {{ $document->driver_cell }}
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @php
        $grouped = $document->items->groupBy(fn ($item) => $item->product?->category?->name ?? 'Items');
    @endphp

    <table class="items" style="margin-top:10px">
        <thead>
            <tr>
                <th style="width:6%">SR. NO.</th>
                <th>DESCRIPTION</th>
                <th style="width:12%">HSN</th>
                <th style="width:10%" class="text-center">QTY</th>
                <th style="width:14%" class="text-center">UNIT QTY CODE</th>
            </tr>
        </thead>
        <tbody>
            @php $sr = 0; @endphp
            @foreach($grouped as $category => $items)
                <tr class="cat-row"><td colspan="5">{{ strtoupper($category) }}</td></tr>
                @foreach($items as $item)
                    <tr>
                        <td class="text-center">{{ ++$sr }}</td>
                        <td>{{ $item->design_no ? "{$item->design_no} — " : '' }}{{ $item->description }}</td>
                        <td>{{ $item->product?->hsn_code ?? '—' }}</td>
                        <td class="text-center">{{ $item->qty }}</td>
                        <td class="text-center">{{ $item->unit ?? '—' }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <x-pdf.footer :company="$company" />

</body>
</html>
