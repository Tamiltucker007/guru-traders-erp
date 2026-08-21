<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $document->doc_num }} Docs to Bank — {{ $variantTitle }}</title>
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

    <x-pdf.letterhead :company="$company" title="Documents to Bank" :subtitle="$variantTitle.' — '.$document->doc_num" />

    @if($variant === 'gr-waiver')
        <p class="title">GR WAIVER — DOCUMENTS TO BANK</p>
        <p class="subtitle">{{ $document->doc_num }}</p>

        <table class="frame" style="margin-bottom:10px">
            <tr>
                <td style="width:50%">
                    <div class="lbl">From (Exporter)</div>
                    <strong>{{ $company->company_name }}</strong>
                    <div style="white-space:pre-line;">{{ $company->address }}</div>
                    @if($company->iec_code)<div>IEC: {{ $company->iec_code }}</div>@endif
                    @if($company->gstin)<div>GSTIN: {{ $company->gstin }}</div>@endif
                </td>
                <td style="width:50%">
                    <div class="lbl">To (Bank)</div>
                    <strong>{{ $company->bank_name ?: '—' }}</strong>
                    @if($company->bank_account_number)<div>A/c No: {{ $company->bank_account_number }}</div>@endif
                    @if($company->bank_ifsc)<div>IFSC: {{ $company->bank_ifsc }}</div>@endif
                    @if($company->bank_swift)<div>SWIFT: {{ $company->bank_swift }}</div>@endif
                </td>
            </tr>
        </table>

        <div style="margin-bottom:8px">
            <strong>Subject: GR Waiver — Recording of Export Shipment</strong>
        </div>

        <div class="small" style="margin-bottom:10px">
            Dear Sir/Madam,<br><br>
            We request you to kindly record the following export shipment details in your records.
            The proceeds of this shipment will be received directly by us and the purpose of this
            submission is for EDPMS reporting and RBI compliance.
        </div>

        <table class="data-table">
            <tr><th style="width:35%">Invoice No.</th><td>{{ $document->invoice_no ?: $document->doc_num }}</td></tr>
            <tr><th>Invoice Date</th><td>{{ ($document->invoice_date ?? $document->shipment_date)?->format('d-M-Y') ?? '—' }}</td></tr>
            <tr>
                <th>Invoice Amount</th>
                <td>{{ $document->currency?->iso_code ?? '' }} {{ number_format($document->totalAmount(), 2) }}</td>
            </tr>
            <tr><th>B/L No.</th><td>{{ $document->bl_no ?: '—' }}</td></tr>
            <tr><th>B/L Date</th><td>{{ $document->bl_date_of_issue?->format('d-M-Y') ?? '—' }}</td></tr>
            <tr><th>Port of Loading</th><td>{{ $document->portOfLoading?->name ?? '—' }}</td></tr>
            <tr><th>Port of Discharge</th><td>{{ $document->portOfDischarge?->name ?? '—' }}</td></tr>
            <tr><th>Buyer Name</th><td>{{ $document->buyer?->company_name ?? '—' }}</td></tr>
            <tr><th>Country</th><td>{{ $document->buyer?->country?->name ?? '—' }}</td></tr>
            <tr><th>Shipping Bill No.</th><td>{{ $document->exporter_ref ?: '—' }}</td></tr>
            <tr><th>IEC Code</th><td>{{ $company->iec_code ?: '—' }}</td></tr>
        </table>

    @else
        <p class="title">BILL OF EXCHANGE</p>
        <p class="subtitle">{{ $document->doc_num }}</p>

        <table class="frame" style="margin-bottom:10px">
            <tr>
                <td style="width:50%">
                    <div class="lbl">Drawer (Exporter)</div>
                    <strong>{{ $company->company_name }}</strong>
                    <div style="white-space:pre-line;">{{ $company->address }}</div>
                </td>
                <td style="width:50%">
                    <div class="lbl">Date</div>
                    <div class="bold">{{ now()->format('d-M-Y') }}</div>
                </td>
            </tr>
        </table>

        @php $total = $document->totalAmount(); @endphp

        <div style="margin-bottom:10px; font-size:11px;">
            <strong>Exchange for {{ $document->currency?->iso_code ?? '' }} {{ number_format($total, 2) }}</strong>
        </div>

        <div class="small" style="margin-bottom:10px; line-height:1.6">
            At <strong>sight</strong> of this FIRST Bill of Exchange (Second of the same tenor and date being unpaid),
            pay to the order of <strong>{{ $company->bank_name ?: '________' }}</strong>
            the sum of <strong>{{ $document->currency?->iso_code ?? '' }}
            {{ ucwords(\App\Support\NumberToWords::indian($total, $document->currency?->iso_code ?? 'INR')) }} Only</strong>
            ({{ $document->currency?->iso_code ?? '' }} {{ number_format($total, 2) }}).
        </div>

        <div class="small" style="margin-bottom:10px;">
            Value received. Drawn under Invoice No. <strong>{{ $document->invoice_no ?: $document->doc_num }}</strong>
            dated <strong>{{ ($document->invoice_date ?? $document->shipment_date)?->format('d-M-Y') ?? '—' }}</strong>.
        </div>

        <table class="data-table" style="margin-bottom:10px">
            <tr><th style="width:35%">Drawee (Buyer)</th><td>{{ $document->buyer?->company_name ?? '—' }}</td></tr>
            <tr><th>Buyer Address</th><td style="white-space:pre-line;">{{ $document->buyer?->address ?? '—' }}</td></tr>
            <tr><th>Country</th><td>{{ $document->buyer?->country?->name ?? '—' }}</td></tr>
            <tr><th>B/L No.</th><td>{{ $document->bl_no ?: '—' }}</td></tr>
            <tr><th>B/L Date</th><td>{{ $document->bl_date_of_issue?->format('d-M-Y') ?? '—' }}</td></tr>
        </table>
    @endif

    <table class="frame" style="margin-top:14px">
        <tr>
            <td style="width:55%">
                <div class="small" style="margin-top:4px">
                    Documents enclosed herewith:<br>
                    1. Export Invoice<br>
                    2. Packing List<br>
                    3. Bill of Lading<br>
                    4. Insurance Certificate (if applicable)
                </div>
            </td>
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
