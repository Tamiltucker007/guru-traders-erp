<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $document->doc_num }} VGM — {{ $variantTitle }}</title>
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    .frame, .frame td { border: 1px solid #000; }
    .frame td { padding: 5px 7px; vertical-align: top; }
    .title { text-align: center; font-size: 14px; font-weight: bold; margin: 0 0 4px; }
    .subtitle { text-align: center; font-size: 9px; margin: 0 0 8px; color: #555; }
    .right { text-align: right; }
    .center { text-align: center; }
    .small { font-size: 8.5px; }
    .lbl { font-size: 8.5px; text-transform: uppercase; color: #555; }
    .data-table th, .data-table td { border: 1px solid #000; padding: 5px 7px; font-size: 9px; }
    .data-table th { background: #f2f2f2; text-align: left; }
    .bold { font-weight: bold; }
</style>
</head>
<body>

    <x-pdf.letterhead :company="$company" title="VGM Declaration" :subtitle="$variantTitle.' — SOLAS Regulation VI/2'" />

    <p class="title">VERIFIED GROSS MASS (VGM) DECLARATION</p>
    <p class="subtitle">{{ $variantTitle }} — SOLAS Regulation VI/2</p>

    <table class="frame" style="margin-bottom:8px">
        <tr>
            <td style="width:50%">
                <div class="lbl">Shipper / Exporter</div>
                <strong>{{ $company->company_name }}</strong>
                <div style="white-space:pre-line;">{{ $company->address }}</div>
                @if($company->iec_code)<div>IEC: {{ $company->iec_code }}</div>@endif
            </td>
            <td style="width:50%">
                <div class="lbl">Booking No.</div>
                <div class="bold">{{ $document->booking_no ?: '—' }}</div>
                <div class="lbl" style="margin-top:6px">B/L No.</div>
                <div>{{ $document->bl_no ?: '—' }}</div>
            </td>
        </tr>
    </table>

    <table class="frame" style="margin-bottom:8px">
        <tr>
            <td style="width:25%">
                <div class="lbl">Vessel / Voyage</div>
                {{ $document->vessel_flight_no ?: '—' }} / {{ $document->voyage_no ?: '—' }}
            </td>
            <td style="width:25%">
                <div class="lbl">Port of Loading</div>
                {{ $document->portOfLoading?->name ?? '—' }}
            </td>
            <td style="width:25%">
                <div class="lbl">Port of Discharge</div>
                {{ $document->portOfDischarge?->name ?? '—' }}
            </td>
            <td style="width:25%">
                <div class="lbl">Final Destination</div>
                {{ $document->final_destination ?: ($document->portOfDischarge?->name ?? '—') }}
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width:5%">Sr</th>
                <th style="width:30%">Description of Goods</th>
                <th style="width:12%" class="center">No. of Packages</th>
                <th style="width:12%" class="center">Kind of Packages</th>
                <th style="width:14%" class="right">Gross Weight (KGS)</th>
                <th style="width:14%" class="right">Net Weight (KGS)</th>
                <th style="width:13%" class="right">Measurement (CBM)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="center">1</td>
                <td>{{ $document->goods_description ?: ($document->items->first()?->description ?? '—') }}</td>
                <td class="center">{{ $document->total_cartons ?: '—' }}</td>
                <td class="center">{{ $document->package_kind ?: 'CARTONS' }}</td>
                <td class="right">{{ $document->gross_weight ? number_format((float) $document->gross_weight, 3) : '—' }}</td>
                <td class="right">{{ $document->net_weight ? number_format((float) $document->net_weight, 3) : '—' }}</td>
                <td class="right">{{ $document->total_measurement ? number_format((float) $document->total_measurement, 3) : '—' }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" class="right"><strong>Total</strong></td>
                <td class="center bold">{{ $document->total_cartons ?: '—' }}</td>
                <td></td>
                <td class="right bold">{{ $document->gross_weight ? number_format((float) $document->gross_weight, 3) : '—' }}</td>
                <td class="right bold">{{ $document->net_weight ? number_format((float) $document->net_weight, 3) : '—' }}</td>
                <td class="right bold">{{ $document->total_measurement ? number_format((float) $document->total_measurement, 3) : '—' }}</td>
            </tr>
        </tfoot>
    </table>

    @php
        $vgmWeight = (float) ($document->gross_weight ?? 0);
    @endphp

    <table class="frame" style="margin-top:10px">
        <tr>
            <td style="width:50%">
                <div class="lbl">VGM (Verified Gross Mass)</div>
                <div class="bold" style="font-size:14px; margin:6px 0;">{{ number_format($vgmWeight, 3) }} KGS</div>
                <div class="small">Method: {{ $variant === 'lcl-shipment' ? 'Method 2 (Weighing)' : 'Method 1 (Weighing + Tare)' }}</div>
            </td>
            <td style="width:50%">
                <div class="lbl">Weighing Method</div>
                @if($variant === 'lcl-shipment')
                    <div class="small">Total weight of cargo + packing material measured at the warehouse.</div>
                @else
                    <div class="small">Packed container weighed on a calibrated weighbridge after stuffing.</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="frame" style="margin-top:10px">
        <tr>
            <td style="width:55%">
                <div class="small">
                    I hereby declare that the VGM of the packed container(s) / cargo is as stated above
                    and has been obtained by the method indicated, using equipment that meets the
                    accuracy standards and requirements of the relevant national authority.
                </div>
            </td>
            <td class="right" style="width:45%">
                for {{ $company->company_name }}<br><br><br>
                {{ $company->signatory_name ?? '' }}<br>
                <span class="small">{{ $company->signatory_designation ?? 'Authorised Signatory' }}</span><br>
                <span class="small">Date: {{ now()->format('d-M-Y') }}</span>
            </td>
        </tr>
    </table>

    <x-pdf.footer :company="$company" note="Computer generated VGM declaration." />

</body>
</html>
