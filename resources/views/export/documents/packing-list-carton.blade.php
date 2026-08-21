<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $document->doc_num }} Packing List — Carton</title>
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    .frame, .frame td { border: 1px solid #000; }
    .frame td { padding: 5px 7px; vertical-align: top; }
    .title { text-decoration: underline; font-weight: bold; font-size: 11px; }
    .company-name { font-size: 13px; font-weight: bold; }
    .company-tag { font-size: 9px; }
    .lbl { font-size: 8.5px; text-transform: uppercase; letter-spacing: .02em; color: #555; }
    .pre { white-space: pre-line; }
    .right { text-align: right; }
    .text-center { text-align: center; }
    .items th, .items td { border: 1px solid #000; padding: 4px 6px; font-size: 9px; }
    .items th { background: #f2f2f2; text-align: left; }
    .totals td { border: 1px solid #000; padding: 4px 7px; font-size: 9.5px; }
    .sign-block { margin-top: 10px; }
    .small { font-size: 8.5px; }
    .carton-page { page-break-after: always; }
    .carton-page:last-child { page-break-after: avoid; }
</style>
</head>
<body>

    @forelse($document->cartons as $carton)
        <div class="carton-page">
            <x-pdf.letterhead :company="$company" title="Packing Slip" :subtitle="'Carton / Bale No. '.$carton->carton_no" />

            <table class="frame" style="margin-bottom:8px">
                <tr>
                    <td style="width:45%">
                        <div class="lbl">Carton / Bale No.</div>
                        <div><strong>{{ $carton->carton_no }}</strong></div>
                        <div class="lbl" style="margin-top:6px">Invoice No.</div>
                        <div>{{ $document->invoice_no ?: $document->doc_num }}</div>
                        <div class="lbl" style="margin-top:6px">Date</div>
                        <div>{{ ($document->invoice_date ?? $document->shipment_date)?->format('d-m-Y') ?? '—' }}</div>
                    </td>
                    <td style="width:55%">
                        <div class="lbl">Exporter</div>
                        <div class="company-name">{{ $company->company_name }}</div>
                        @if($company->tagline)<div class="company-tag">{{ $company->tagline }}</div>@endif
                        <div class="pre" style="margin-top:4px">{{ $company->address }}</div>
                        @if($company->phone)<div>TEL: {{ $company->phone }}</div>@endif
                        @if($company->email)<div>EMAIL: {{ $company->email }}</div>@endif
                    </td>
                </tr>
            </table>

            <table class="items">
                <thead>
                    <tr>
                        <th>Particular</th>
                        <th style="width:18%" class="text-center">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($carton->lines as $line)
                        <tr>
                            <td>{{ $line->description }}</td>
                            <td class="text-center">{{ $line->qty }} {{ $line->unit }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="totals" style="margin-top:10px; width:60%">
                <tr>
                    <td class="lbl">Total Quantity</td>
                    <td class="right">{{ $carton->totalQty() }}</td>
                </tr>
                <tr>
                    <td class="lbl">Total Net Weight</td>
                    <td class="right">{{ $carton->net_weight !== null ? number_format((float) $carton->net_weight, 3) : '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Total Gross Weight</td>
                    <td class="right">{{ $carton->gross_weight !== null ? number_format((float) $carton->gross_weight, 3) : '—' }}</td>
                </tr>
                <tr>
                    <td class="lbl">Carton / Bale Dimension</td>
                    <td class="right">{{ $carton->dimensions ?: '—' }}</td>
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
        </div>
    @empty
        <x-pdf.letterhead :company="$company" title="Packing List" subtitle="Without Supplier (for Carton)" />
        <p>No cartons have been recorded for this Export Document yet — add them on the Edit screen before generating this packing list.</p>
        <x-pdf.footer :company="$company" />
    @endforelse

</body>
</html>
