<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $document->doc_num }} Buyer Docs — {{ $variantTitle }}</title>
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    .frame, .frame td { border: 1px solid #000; }
    .frame td { padding: 5px 7px; vertical-align: top; }
    .title { text-align: center; font-size: 14px; font-weight: bold; margin: 0 0 4px; }
    .subtitle { text-align: center; font-size: 9px; margin: 0 0 10px; color: #555; }
    .right { text-align: right; }
    .center { text-align: center; }
    .small { font-size: 8.5px; }
    .lbl { font-size: 8.5px; text-transform: uppercase; color: #555; }
    .bold { font-weight: bold; }
    .data-table th, .data-table td { border: 1px solid #000; padding: 5px 7px; font-size: 9px; }
    .data-table th { background: #f2f2f2; text-align: left; }
</style>
</head>
<body>

    <x-pdf.letterhead :company="$company" title="Buyer Documents" :subtitle="$variantTitle.' — '.$document->doc_num" />

    @if($variant === 'gr-release-email')
        <p class="title">SHIPMENT DOCUMENTS — FOR BUYER</p>
        <p class="subtitle">GR Release — {{ $document->doc_num }}</p>

        <table class="frame" style="margin-bottom:10px">
            <tr>
                <td style="width:50%">
                    <div class="lbl">From</div>
                    <strong>{{ $company->company_name }}</strong>
                    <div style="white-space:pre-line;">{{ $company->address }}</div>
                    @if($company->email)<div>Email: {{ $company->email }}</div>@endif
                    @if($company->phone)<div>Phone: {{ $company->phone }}</div>@endif
                </td>
                <td style="width:50%">
                    <div class="lbl">To (Buyer)</div>
                    <strong>{{ $document->buyer?->company_name ?? '—' }}</strong>
                    <div style="white-space:pre-line;">{{ $document->buyer?->address ?? '' }}</div>
                    <div>{{ $document->buyer?->country?->name ?? '' }}</div>
                </td>
            </tr>
        </table>

        <div class="small" style="margin-bottom:10px; line-height:1.6">
            Dear {{ $document->buyer?->company_name ?? 'Sir/Madam' }},<br><br>
            Please find enclosed herewith the shipping documents for your order. The GR has been released
            by our bank and you may proceed to clear the goods at your end.
        </div>

        <table class="data-table" style="margin-bottom:10px">
            <tr><th style="width:35%">Invoice No.</th><td>{{ $document->invoice_no ?: $document->doc_num }}</td></tr>
            <tr><th>Invoice Date</th><td>{{ ($document->invoice_date ?? $document->shipment_date)?->format('d-M-Y') ?? '—' }}</td></tr>
            <tr>
                <th>Invoice Amount</th>
                <td>{{ $document->currency?->iso_code ?? '' }} {{ number_format($document->totalAmount(), 2) }}</td>
            </tr>
            <tr><th>B/L No.</th><td>{{ $document->bl_no ?: '—' }}</td></tr>
            <tr><th>B/L Date</th><td>{{ $document->bl_date_of_issue?->format('d-M-Y') ?? '—' }}</td></tr>
            <tr><th>Vessel / Voyage</th><td>{{ $document->vessel_flight_no ?: '—' }} / {{ $document->voyage_no ?: '—' }}</td></tr>
            <tr><th>Port of Loading</th><td>{{ $document->portOfLoading?->name ?? '—' }}</td></tr>
            <tr><th>Port of Discharge</th><td>{{ $document->portOfDischarge?->name ?? '—' }}</td></tr>
            <tr><th>No. of Packages</th><td>{{ $document->total_cartons ? "{$document->total_cartons} {$document->package_kind}" : '—' }}</td></tr>
            <tr><th>Gross Weight</th><td>{{ $document->gross_weight ? number_format((float) $document->gross_weight, 3).' KGS' : '—' }}</td></tr>
            <tr><th>Net Weight</th><td>{{ $document->net_weight ? number_format((float) $document->net_weight, 3).' KGS' : '—' }}</td></tr>
        </table>

        <div class="small" style="margin-bottom:10px;">
            <strong>Documents enclosed:</strong><br>
            1. Original Bill of Lading<br>
            2. Export Invoice<br>
            3. Packing List<br>
            4. Insurance Certificate (if applicable)<br>
            5. Certificate of Origin (if applicable)
        </div>

    @else
        <p class="title">SHIPMENT INTIMATION</p>
        <p class="subtitle">Bill of Exchange — {{ $document->doc_num }}</p>

        <table class="frame" style="margin-bottom:10px">
            <tr>
                <td style="width:50%">
                    <div class="lbl">From</div>
                    <strong>{{ $company->company_name }}</strong>
                    <div style="white-space:pre-line;">{{ $company->address }}</div>
                    @if($company->email)<div>Email: {{ $company->email }}</div>@endif
                </td>
                <td style="width:50%">
                    <div class="lbl">To (Buyer)</div>
                    <strong>{{ $document->buyer?->company_name ?? '—' }}</strong>
                    <div style="white-space:pre-line;">{{ $document->buyer?->address ?? '' }}</div>
                    <div>{{ $document->buyer?->country?->name ?? '' }}</div>
                </td>
            </tr>
        </table>

        <div class="small" style="margin-bottom:10px; line-height:1.6">
            Dear {{ $document->buyer?->company_name ?? 'Sir/Madam' }},<br><br>
            We wish to inform you that we have shipped your order as per the details below. The documents
            have been routed through our bank via a Bill of Exchange. Kindly arrange to accept/pay the
            Bill of Exchange upon presentation by your bank and collect the documents for customs clearance.
        </div>

        <table class="data-table" style="margin-bottom:10px">
            <tr><th style="width:35%">Invoice No.</th><td>{{ $document->invoice_no ?: $document->doc_num }}</td></tr>
            <tr><th>Invoice Date</th><td>{{ ($document->invoice_date ?? $document->shipment_date)?->format('d-M-Y') ?? '—' }}</td></tr>
            <tr>
                <th>Invoice Amount</th>
                <td>{{ $document->currency?->iso_code ?? '' }} {{ number_format($document->totalAmount(), 2) }}</td>
            </tr>
            <tr><th>B/L No.</th><td>{{ $document->bl_no ?: '—' }}</td></tr>
            <tr><th>B/L Date</th><td>{{ $document->bl_date_of_issue?->format('d-M-Y') ?? '—' }}</td></tr>
            <tr><th>Vessel / Voyage</th><td>{{ $document->vessel_flight_no ?: '—' }} / {{ $document->voyage_no ?: '—' }}</td></tr>
            <tr><th>Port of Loading</th><td>{{ $document->portOfLoading?->name ?? '—' }}</td></tr>
            <tr><th>Port of Discharge</th><td>{{ $document->portOfDischarge?->name ?? '—' }}</td></tr>
            <tr><th>No. of Packages</th><td>{{ $document->total_cartons ? "{$document->total_cartons} {$document->package_kind}" : '—' }}</td></tr>
        </table>

        <div class="small" style="margin-bottom:10px;">
            <strong>Note:</strong> The original shipping documents will be delivered to you through your
            bank upon acceptance/payment of the Bill of Exchange. No documents are enclosed with this intimation.
        </div>
    @endif

    <table class="frame" style="margin-top:10px">
        <tr>
            <td style="width:55%">&nbsp;</td>
            <td class="right">
                for {{ $company->company_name }}<br><br><br>
                {{ $company->signatory_name ?? '' }}<br>
                <span class="small">{{ $company->signatory_designation ?? 'Authorised Signatory' }}</span>
            </td>
        </tr>
    </table>

    <x-pdf.footer :company="$company" />

</body>
</html>
