<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $document->doc_num }} Export Invoice — {{ $variantTitle }}</title>
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #111; margin: 0; }
    table { border-collapse: collapse; width: 100%; }
    .frame, .frame td, .frame th { border: 1px solid #000; }
    .frame td, .frame th { padding: 5px 7px; vertical-align: top; }
    .title { text-align: center; font-size: 14px; font-weight: bold; margin: 0; }
    .subtitle { text-align: center; font-size: 9px; margin: 2px 0 8px; }
    .lbl { font-size: 8.5px; text-transform: uppercase; letter-spacing: .02em; color: #555; }
    .pre { white-space: pre-line; }
    .right { text-align: right; }
    .center { text-align: center; }
    .items th, .items td { border: 1px solid #000; padding: 4px 6px; font-size: 9px; }
    .items th { background: #f2f2f2; text-align: left; }
    .sign-block { margin-top: 10px; }
    .small { font-size: 8.5px; }
</style>
</head>
<body>

    <x-pdf.letterhead :company="$company" title="Export Invoice" :subtitle="$variantTitle" />

    <p class="title">EXPORT INVOICE</p>
    <p class="subtitle">{{ $variantTitle }}</p>

    <table class="frame" style="margin-bottom:0">
        <tr>
            <td style="width:55%">
                <strong>{{ $company->company_name }}</strong>
                @if($company->tagline)<div class="small">{{ $company->tagline }}</div>@endif
                <div class="pre">{{ $company->address }}</div>
                @if($company->gstin)<div>GSTIN: {{ $company->gstin }}</div>@endif
                @if($company->iec_code)<div>IEC: {{ $company->iec_code }}</div>@endif
                @if($company->email)<div>Email: {{ $company->email }}</div>@endif
                @if($company->phone)<div>Phone: {{ $company->phone }}</div>@endif
            </td>
            <td style="width:45%">
                <table style="width:100%">
                    <tr>
                        <td style="border:none; padding:0 0 4px;">
                            <div class="lbl">Invoice No.</div>
                            {{ $document->invoice_no ?: $document->doc_num }}
                        </td>
                        <td style="border:none; padding:0 0 4px;">
                            <div class="lbl">Date</div>
                            {{ ($document->invoice_date ?? $document->shipment_date)?->format('d-M-Y') ?? '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td style="border:none; padding:0 0 4px;">
                            <div class="lbl">Exporter's Ref</div>
                            {{ $document->exporter_ref ?: ($document->orderConfirmation?->buyer_ref ?? '—') }}
                        </td>
                        <td style="border:none; padding:0 0 4px;">
                            <div class="lbl">Buyer's Order No.</div>
                            {{ $document->buyer_ref_no ?: ($document->orderConfirmation?->oc_num ?? '—') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="frame" style="margin-top:-1px">
        <tr>
            <td style="width:55%">
                <div class="lbl">Buyer / Consignee</div>
                <strong>{{ $document->consignee_name ?: $document->buyer?->company_name }}</strong>
                <div class="pre">{{ $document->consignee_address ?: $document->buyer?->address }}</div>
                <div>{{ $document->buyer?->country?->name }}</div>
            </td>
            <td style="width:45%">
                <div class="lbl">Country of Origin</div>
                <div>{{ $document->country_of_origin ?: 'India' }}</div>
                <div class="lbl" style="margin-top:6px">Country of Final Destination</div>
                <div>{{ $document->buyer?->country?->name ?? '—' }}</div>
                <div class="lbl" style="margin-top:6px">Terms of Delivery &amp; Payment</div>
                <div>{{ $document->incoterm?->code ?? '—' }} — {{ $document->portOfDischarge?->name ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <table class="frame" style="margin-top:-1px">
        <tr>
            <td style="width:25%"><div class="lbl">Pre-Carriage by</div>{{ $document->pre_carriage_by ?: ($document->shipmentMethod?->name ?? '—') }}</td>
            <td style="width:25%"><div class="lbl">Place of Receipt</div>{{ $document->place_of_receipt ?: '—' }}</td>
            <td style="width:25%"><div class="lbl">Vessel / Flight No.</div>{{ $document->vessel_flight_no ?: '—' }}</td>
            <td style="width:25%"><div class="lbl">Port of Loading</div>{{ $document->portOfLoading?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td><div class="lbl">Port of Discharge</div>{{ $document->portOfDischarge?->name ?? '—' }}</td>
            <td><div class="lbl">Final Destination</div>{{ $document->final_destination ?: ($document->portOfDischarge?->name ?? '—') }}</td>
            <td colspan="2"><div class="lbl">Currency &amp; Incoterm</div>{{ $document->currency?->iso_code ?? '—' }} / {{ $document->incoterm?->code ?? '—' }}</td>
        </tr>
    </table>

    @php
        $totalTaxable = 0;
        $sr = 0;
    @endphp

    <table class="items" style="margin-top:8px">
        <thead>
            <tr>
                <th style="width:4%">Sr</th>
                <th>Marks &amp; Nos.</th>
                <th>Description of Goods</th>
                <th style="width:10%">HSN</th>
                <th style="width:8%" class="center">Qty</th>
                <th style="width:6%" class="center">Unit</th>
                <th style="width:10%" class="right">Rate ({{ $document->currency?->iso_code ?? '' }})</th>
                <th style="width:12%" class="right">Amount ({{ $document->currency?->iso_code ?? '' }})</th>
            </tr>
        </thead>
        <tbody>
            @foreach($document->items as $item)
                @php $totalTaxable += (float) $item->amount; @endphp
                <tr>
                    <td class="center">{{ ++$sr }}</td>
                    <td class="small">{{ $document->marks_and_numbers ?: '—' }}</td>
                    <td>{{ $item->design_no ? "{$item->design_no} — " : '' }}{{ $item->description }}</td>
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
                <td colspan="7" class="right"><strong>FOB Value</strong></td>
                <td class="right"><strong>{{ number_format($totalTaxable, 2) }}</strong></td>
            </tr>
            @if((float) $document->freight_amount > 0)
                <tr>
                    <td colspan="7" class="right">Freight</td>
                    <td class="right">{{ number_format((float) $document->freight_amount, 2) }}</td>
                </tr>
            @endif
            @if((float) $document->insurance_amount > 0)
                <tr>
                    <td colspan="7" class="right">Insurance</td>
                    <td class="right">{{ number_format((float) $document->insurance_amount, 2) }}</td>
                </tr>
            @endif
            @php $grandTotal = $totalTaxable + (float) $document->freight_amount + (float) $document->insurance_amount; @endphp
            @if((float) $document->freight_amount > 0 || (float) $document->insurance_amount > 0)
                <tr>
                    <td colspan="7" class="right"><strong>Total ({{ $document->incoterm?->code ?? 'CIF' }})</strong></td>
                    <td class="right"><strong>{{ number_format($grandTotal, 2) }}</strong></td>
                </tr>
            @endif
        </tfoot>
    </table>

    <div class="small" style="margin-top:6px">
        Amount in words: {{ $document->currency?->iso_code ?? '' }}
        {{ ucwords(\App\Support\NumberToWords::indian($grandTotal ?? $totalTaxable, $document->currency?->iso_code ?? 'INR')) }} Only
    </div>

    <table class="frame" style="margin-top:10px">
        <tr>
            <td style="width:55%">
                <div class="small">
                    <div class="lbl">Bank Details</div>
                    @if($company->bank_name){{ $company->bank_name }}<br>@endif
                    @if($company->bank_account_number)A/c No: {{ $company->bank_account_number }}<br>@endif
                    @if($company->bank_ifsc)IFSC: {{ $company->bank_ifsc }}<br>@endif
                    @if($company->bank_swift)SWIFT: {{ $company->bank_swift }}@endif
                </div>
                <div class="small" style="margin-top:6px">
                    Declaration: We declare that this invoice shows the actual price of the
                    goods described and that all particulars are true and correct.
                </div>
            </td>
            <td class="right sign-block">
                for {{ $company->company_name }}<br><br><br>
                {{ $company->signatory_name ?? '' }}<br>
                <span class="small">{{ $company->signatory_designation ?? 'Authorised Signatory' }}</span>
            </td>
        </tr>
    </table>

    <div class="small center" style="margin-top:6px">This is a Computer Generated Export Invoice</div>

    <x-pdf.footer :company="$company" note="Official export invoice issued by Guru Traders." />

</body>
</html>
