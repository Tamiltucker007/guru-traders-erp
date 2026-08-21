<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $document->doc_num }} Packing List — Customs</title>
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 9.5px; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    .frame, .frame td { border: 1px solid #000; }
    .frame td { padding: 4px 6px; vertical-align: top; }
    .title-row { position: relative; text-align: center; margin: 0 0 6px; }
    .title { font-size: 14px; font-weight: bold; }
    .page-no { position: absolute; right: 0; top: 2px; font-size: 9px; }
    .lbl { font-size: 8px; text-transform: uppercase; letter-spacing: .02em; color: #555; }
    .pre { white-space: pre-line; }
    .right { text-align: right; }
    .text-center { text-align: center; }
    .items th, .items td { border: 1px solid #000; padding: 3px 5px; font-size: 8.5px; }
    .items th { background: #f2f2f2; text-align: left; }
    .totals-strip { margin-top: 8px; }
    .totals-strip td { border: 1px solid #000; padding: 4px 8px; font-size: 9px; }
    .sign-block { margin-top: 10px; }
    .small { font-size: 8px; }
    .sheet { page-break-after: always; }
    .sheet:last-child { page-break-after: avoid; }
</style>
</head>
<body>

@php
    // Format C prints carton-by-carton across "continuation sheets" — the
    // meta header repeats (in full on sheet 1, a shorter form after) rather
    // than once for the whole document, so cartons are chunked into pages
    // by hand instead of leaving pagination to a single long table.
    $cartons = $document->cartons;
    $perSheet = 12;
    $sheets = $cartons->isEmpty() ? collect([collect()]) : $cartons->chunk($perSheet)->values();
    $totalSheets = $sheets->count();
@endphp

@if($cartons->isEmpty())
    <x-pdf.letterhead :company="$company" title="Packing List" subtitle="For Export Documentation" />
    <p>No cartons have been recorded for this Export Document yet — add them on the Edit screen before generating this packing list.</p>
    <x-pdf.footer :company="$company" />
@else
    @foreach($sheets as $index => $sheetCartons)
        @php $sheetNo = $index + 1; @endphp
        <div class="sheet">
            @if($sheetNo === 1)
                <x-pdf.letterhead :company="$company" title="Packing List" subtitle="For Export Documentation" />
            @endif
            <div class="title-row">
                <span class="title">PACKING LIST</span>
                <span class="page-no">{{ $sheetNo == 1 ? '' : "Continuation Sheet {$sheetNo}/{$totalSheets}" }}</span>
            </div>

            @if($sheetNo === 1)
                <table class="frame" style="margin-bottom:6px">
                    <tr>
                        <td style="width:50%">
                            <div class="lbl">Exporter</div>
                            <strong>{{ $company->company_name }}</strong>
                            <div class="pre">{{ $company->address }}</div>
                        </td>
                        <td style="width:50%">
                            <div class="lbl">Invoice No. &amp; Date</div>
                            {{ $document->invoice_no ?: $document->doc_num }} &nbsp; {{ ($document->invoice_date ?? $document->shipment_date)?->format('d/m/Y') ?? '—' }}
                            <div class="lbl" style="margin-top:4px">Buyer's Ref. No. &amp; Date</div>
                            {{ $document->buyer_ref_no ?: '—' }} @if($document->buyer_ref_date) &nbsp; {{ $document->buyer_ref_date->format('d/m/Y') }} @endif
                            <div class="lbl" style="margin-top:4px">Other Reference(s)</div>
                            {{ $document->other_reference ?: '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="lbl">Consignee</div>
                            <div class="pre">{{ $document->consignee_name ? $document->consignee_name."\n".$document->consignee_address : ($document->buyer?->company_name."\n".$document->buyer?->address) }}</div>
                        </td>
                        <td>
                            <div class="lbl">Buyer (if other than Consignee)</div>
                            <div class="pre">{{ $document->consignee_name ? $document->buyer?->company_name."\n".$document->buyer?->address : '—' }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="lbl">Country of Origin of Goods</div>
                            {{ $document->country_of_origin }}
                        </td>
                        <td>
                            <div class="lbl">Country of Final Destination</div>
                            {{ $document->countryOfFinalDestination() ?? '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="lbl">Pre-Carriage By</div>
                            {{ $document->pre_carriage_by ?: '—' }}
                        </td>
                        <td>
                            <div class="lbl">Place of Receipt by Pre-Carrier</div>
                            {{ $document->place_of_receipt ?: '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="lbl">Vessel / Flight No.</div>
                            {{ $document->vessel_flight_no ?: '—' }}
                            <div class="lbl" style="margin-top:4px">Port of Loading</div>
                            {{ $document->portOfLoading?->name ?? '—' }}
                        </td>
                        <td>
                            <div class="lbl">Port of Discharge</div>
                            {{ $document->portOfDischarge?->name ?? '—' }}
                            <div class="lbl" style="margin-top:4px">Final Destination</div>
                            {{ $document->final_destination ?: '—' }}
                        </td>
                    </tr>
                </table>
            @else
                <table class="frame" style="margin-bottom:6px">
                    <tr>
                        <td style="width:50%">
                            <strong>{{ $company->company_name }}</strong>
                            <div class="pre">{{ $company->address }}</div>
                        </td>
                        <td style="width:50%">
                            <div class="lbl">Invoice No. &amp; Date</div>
                            {{ $document->invoice_no ?: $document->doc_num }} &nbsp; {{ ($document->invoice_date ?? $document->shipment_date)?->format('d/m/Y') ?? '—' }}
                            <div class="lbl" style="margin-top:4px">Buyer's Ref. No. &amp; Date</div>
                            {{ $document->buyer_ref_no ?: '—' }} @if($document->buyer_ref_date) &nbsp; {{ $document->buyer_ref_date->format('d/m/Y') }} @endif
                        </td>
                    </tr>
                </table>
            @endif

            <table class="items">
                <thead>
                    <tr>
                        <th style="width:15%">Marks &amp; Nos.</th>
                        <th style="width:12%">CTN No.</th>
                        <th>Description</th>
                        <th style="width:12%" class="text-center">Quantity</th>
                        <th style="width:12%" class="text-center">Net Wt.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sheetCartons as $carton)
                        @foreach($carton->lines as $lineIndex => $line)
                            <tr>
                                <td>{{ $lineIndex === 0 ? ($document->marks_and_numbers ?: '—') : '' }}</td>
                                <td>{{ $lineIndex === 0 ? $carton->carton_no : '' }}</td>
                                <td>{{ $line->description }}</td>
                                <td class="text-center">{{ $line->qty }} {{ $line->unit }}</td>
                                <td class="text-center">{{ $lineIndex === 0 && $carton->net_weight !== null ? number_format((float) $carton->net_weight, 2) : '' }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>

            @if($sheetNo === $totalSheets)
                @php $byUnit = $document->cartonQtyByUnit(); @endphp
                <table class="totals-strip">
                    <tr>
                        <td class="lbl" style="width:15%">Totals</td>
                        <td>
                            @foreach($byUnit as $unit => $qty)
                                {{ $unit }}: <strong>{{ rtrim(rtrim(number_format($qty, 2), '0'), '.') }}</strong> &nbsp;&nbsp;
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td class="lbl">Total Gross Wt.</td>
                        <td>{{ $document->gross_weight !== null ? number_format((float) $document->gross_weight, 3).' KGS' : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Total Net Wt.</td>
                        <td>{{ $document->net_weight !== null ? number_format((float) $document->net_weight, 3).' KGS' : '—' }}</td>
                    </tr>
                </table>
            @endif

            <table style="margin-top:16px">
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
    @endforeach
@endif

</body>
</html>
