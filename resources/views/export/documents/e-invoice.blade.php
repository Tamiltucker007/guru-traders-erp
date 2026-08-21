<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $document->doc_num }} E-Invoice</title>
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    .frame, .frame td { border: 1px solid #000; }
    .frame td { padding: 5px 7px; vertical-align: top; }
    .title { text-align: center; font-size: 15px; font-weight: bold; margin: 0; }
    .subtitle { text-align: center; font-size: 8px; margin: 2px 0 8px; }
    .lbl { font-size: 8.5px; text-transform: uppercase; letter-spacing: .02em; color: #555; }
    .pre { white-space: pre-line; }
    .right { text-align: right; }
    .text-center { text-align: center; }
    .items th, .items td { border: 1px solid #000; padding: 4px 6px; font-size: 9px; }
    .items th { background: #f2f2f2; text-align: left; }
    .tax-summary th, .tax-summary td { border: 1px solid #000; padding: 4px 6px; font-size: 9px; }
    .tax-summary th { background: #f2f2f2; }
    .sign-block { margin-top: 10px; }
    .small { font-size: 8.5px; }
</style>
</head>
<body>

    <x-pdf.letterhead :company="$company" title="Tax Invoice" subtitle="Supply meant for export on payment of IGST" />

    <p class="title">Tax Invoice</p>
    <p class="subtitle">(SUPPLY MEANT FOR EXPORT ON PAYMENT OF IGST)</p>

    <table class="frame" style="margin-bottom:0">
        <tr>
            <td style="width:70%">
                <div class="lbl">IRN</div>
                <div>{{ $document->checklist->firstWhere('type.code', 'e_invoice')?->reference_no ?: '— to be filled in after upload to the GST e-Invoice portal —' }}</div>
                <table class="no-border" style="margin-top:4px">
                    <tr>
                        <td style="border:none;padding:0;width:50%"><span class="lbl">Ack. No.</span></td>
                        <td style="border:none;padding:0"><span class="lbl">Ack. Date</span></td>
                    </tr>
                </table>
            </td>
            <td class="right" style="width:30%">e-Invoice</td>
        </tr>
    </table>

    <table style="margin-top:0">
        <tr>
            <td style="width:55%; vertical-align:top; padding:0">
                <table class="frame" style="border-top:none">
                    <tr>
                        <td>
                            <strong>{{ $company->company_name }}</strong>
                            <div class="pre">{{ $company->address }}</div>
                            @if($company->gstin)<div>GSTIN/UIN: {{ $company->gstin }}</div>@endif
                            @if($company->email)<div>E-Mail: {{ $company->email }}</div>@endif
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="lbl">Buyer (Bill to)</div>
                            <strong>{{ $document->buyer?->company_name }}</strong>
                            <div class="pre">{{ $document->buyer?->address }}</div>
                            <div>{{ $document->buyer?->country?->name }}</div>
                            @if($document->buyer?->gst_vat_no)<div>GSTIN/VAT No.: {{ $document->buyer->gst_vat_no }}</div>@endif
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width:45%; vertical-align:top; padding:0 0 0 8px">
                <table class="frame">
                    <tr>
                        <td style="width:50%">
                            <div class="lbl">Invoice No.</div>
                            {{ $document->invoice_no ?: $document->doc_num }}
                        </td>
                        <td>
                            <div class="lbl">Dated</div>
                            {{ ($document->invoice_date ?? $document->shipment_date)?->format('d-M-y') ?? '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="lbl">Buyer's Order No.</div>
                            {{ $document->exporter_ref ?: ($document->orderConfirmation?->buyer_ref ?? '—') }}
                        </td>
                        <td>
                            <div class="lbl">Dispatched through</div>
                            {{ $document->shipmentMethod?->name ?? '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="lbl">Destination</div>
                            {{ $document->final_destination ?: ($document->portOfDischarge?->name ?? '—') }}
                        </td>
                        <td>
                            <div class="lbl">Country</div>
                            {{ $document->buyer?->country?->name ?? '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <div class="lbl">Place of Supply</div>
                            {{ $document->portOfLoading?->name ?? '—' }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top:8px">
        <thead>
            <tr>
                <th style="width:5%">Sl No.</th>
                <th>Description of Goods</th>
                <th style="width:12%">HSN/SAC</th>
                <th style="width:8%" class="text-center">GST %</th>
                <th style="width:14%" class="text-center">Amount</th>
            </tr>
        </thead>
        <tbody>
            @php $sr = 0; $totalTaxable = 0; @endphp
            @foreach($document->items as $item)
                @php
                    $rate = (float) ($item->product?->gstRate?->rate ?? 0);
                    $totalTaxable += (float) $item->amount;
                @endphp
                <tr>
                    <td class="text-center">{{ ++$sr }}</td>
                    <td>{{ $item->design_no ? "{$item->design_no} — " : '' }}{{ $item->description }}</td>
                    <td>{{ $item->product?->hsn_code ?? '—' }}</td>
                    <td class="text-center">{{ $rate ? rtrim(rtrim(number_format($rate, 2), '0'), '.').'%' : '—' }}</td>
                    <td class="text-center">{{ number_format((float) $item->amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $summary = $document->items
            ->groupBy(fn ($item) => ($item->product?->hsn_code ?? '—').'|'.(float) ($item->product?->gstRate?->rate ?? 0))
            ->map(function ($group) {
                $first = $group->first();
                $rate = (float) ($first->product?->gstRate?->rate ?? 0);
                $taxable = (float) $group->sum('amount');

                return [
                    'hsn'     => $first->product?->hsn_code ?? '—',
                    'rate'    => $rate,
                    'taxable' => $taxable,
                    'igst'    => round($taxable * $rate / 100, 2),
                ];
            })
            ->values();
        $totalIgst = $summary->sum('igst');
    @endphp

    <table class="tax-summary" style="margin-top:10px">
        <thead>
            <tr>
                <th>HSN/SAC</th>
                <th class="text-center">Taxable Value</th>
                <th class="text-center">IGST Rate</th>
                <th class="text-center">IGST Amount</th>
                <th class="text-center">Total Tax Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($summary as $row)
                <tr>
                    <td>{{ $row['hsn'] }}</td>
                    <td class="text-center">{{ number_format($row['taxable'], 2) }}</td>
                    <td class="text-center">{{ $row['rate'] }}%</td>
                    <td class="text-center">{{ number_format($row['igst'], 2) }}</td>
                    <td class="text-center">{{ number_format($row['igst'], 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>Total</strong></td>
                <td class="text-center"><strong>{{ number_format($totalTaxable, 2) }}</strong></td>
                <td></td>
                <td class="text-center"><strong>{{ number_format($totalIgst, 2) }}</strong></td>
                <td class="text-center"><strong>{{ number_format($totalIgst, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="small" style="margin-top:6px">
        Amount Chargeable (in words): {{ $document->currency?->iso_code ?? '' }}
        {{ ucwords(\App\Support\NumberToWords::indian($totalTaxable + $totalIgst, $document->currency?->iso_code ?? 'INR')) }} Only
    </div>

    <table class="frame" style="margin-top:8px">
        <tr>
            <td style="width:55%">
                @php $pan = $company->gstin && strlen($company->gstin) >= 12 ? substr($company->gstin, 2, 10) : null; @endphp
                <div class="small">Company's PAN/IEC Code: {{ $pan ?? $company->iec_code ?? '—' }}</div>
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

    <x-pdf.footer :company="$company" note="Computer generated tax invoice." />

</body>
</html>
