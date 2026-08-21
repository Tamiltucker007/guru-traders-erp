<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $document->doc_num }} Bill of Lading Draft</title>
<style>
    body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #111; }
    table { border-collapse: collapse; width: 100%; }
    .frame, .frame td, .frame th { border: 1px solid #000; }
    .frame td, .frame th { padding: 5px 7px; vertical-align: top; }
    .lbl { font-size: 9px; font-weight: normal; }
    .pre { white-space: pre-line; }
    .center { text-align: center; }
    .right { text-align: right; }
    .bold { font-weight: bold; }
    .title { text-align: center; font-size: 16px; font-weight: bold; letter-spacing: .05em; }
</style>
</head>
<body>

    <x-pdf.letterhead :company="$company" title="Bill of Lading" subtitle="Draft — for shipping line confirmation" />

    <table class="frame" style="margin-bottom:10px">
        <tr>
            <td style="width:58%; padding:0; vertical-align:top;">
                <table style="width:100%">
                    <tr>
                        <td style="border-bottom:1px solid #000; border-top:none; border-left:none; border-right:1px solid #000;">
                            <div class="lbl">Shipper</div>
                            <strong>{{ $company->company_name }}</strong>
                            <div class="pre">{{ $company->address }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-bottom:1px solid #000; border-top:none; border-left:none; border-right:1px solid #000;">
                            <div class="lbl">Consignee</div>
                            <div>{{ $document->consignee_name ?: $document->buyer?->company_name }}</div>
                            <div class="pre">{{ $document->consignee_address ?: $document->buyer?->address }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:none; border-left:none; border-right:1px solid #000; border-bottom:none;">
                            <div class="lbl">Notify party</div>
                            <div>{{ $document->notifyPartyName() }}</div>
                            <div class="pre">{{ $document->notifyPartyAddress() }}</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width:42%; padding:0; vertical-align:top;">
                <table style="width:100%">
                    <tr>
                        <td style="width:50%; border-bottom:1px solid #000; border-top:none; border-right:1px solid #000;">
                            <div class="lbl">BOOKING NO.</div>
                            {{ $document->booking_no ?: '—' }}
                        </td>
                        <td style="width:50%; border-bottom:1px solid #000; border-top:none;">
                            <div class="lbl">B/L No.</div>
                            {{ $document->bl_no ?: '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="title" style="border:none; padding-top:14px;">
                            B/L DRAFT
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="frame" style="margin-bottom:0">
        <tr>
            <td style="width:34%">
                <div class="lbl">Ocean vessel</div>
                {{ $document->vessel_flight_no ?: '—' }}
            </td>
            <td style="width:16%">
                <div class="lbl">Voy. No.</div>
                {{ $document->voyage_no ?: '—' }}
            </td>
            <td style="width:50%">
                <div class="lbl">Port of Loading</div>
                {{ $document->portOfLoading?->name ?? '—' }}
            </td>
        </tr>
    </table>

    <table class="frame" style="margin-top:-1px">
        <tr>
            <td style="width:34%">
                <div class="lbl">Port of discharge</div>
                {{ $document->portOfDischarge?->name ?? '—' }}
            </td>
            <td style="width:33%">
                <div class="lbl">For transshipment to (If transshipped at port of discharge)</div>
                {{ $document->transshipment_port ?: '—' }}
            </td>
            <td style="width:33%">
                <div class="lbl">Final destination for the merchant reference</div>
                {{ $document->final_destination ?: trim(($document->portOfDischarge?->name ?? '').($document->countryOfFinalDestination() ? ', '.$document->countryOfFinalDestination() : ''), ', ') ?: '—' }}
            </td>
        </tr>
    </table>

    <table class="frame" style="margin-top:-1px">
        <tr>
            <td style="width:22%">
                <div class="lbl">Marks &amp; numbers</div>
                <div class="pre">{{ $document->marks_and_numbers ?: '—' }}</div>
            </td>
            <td style="width:10%">
                <div class="lbl">No. of pkgs<br>Or units</div>
                {{ $document->total_cartons ?: '—' }}
            </td>
            <td style="width:53%">
                <div class="lbl">Kind of packages : Description of goods</div>
                <div class="pre">
SAID TO CONTAIN
{{ $document->total_cartons }} {{ $document->package_kind ?: 'CARTONS' }} CONTAINING

{{ $document->goods_description ?: '—' }} AS PER
INVOICE NO. {{ $document->invoice_no ?: $document->doc_num }} DATED {{ ($document->invoice_date ?? $document->shipment_date)?->format('d.m.Y') ?? '—' }}


TOTAL NET.WT : {{ $document->net_weight ? number_format((float) $document->net_weight, 3).' KGS' : '—' }}
TOTAL GRS. WT : {{ $document->gross_weight ? number_format((float) $document->gross_weight, 3).' KGS' : '—' }}

FREIGHT {{ $document->freight_terms ?: 'PREPAID' }}
                </div>
            </td>
            <td style="width:15%">
                <div class="lbl">measurement</div>
                {{ $document->total_measurement ? number_format((float) $document->total_measurement, 3) : '—' }}
            </td>
        </tr>
    </table>

    <table class="frame" style="margin-top:10px">
        <tr>
            <td style="width:16%">
                <div class="lbl">EX.RATE</div>
                {{ $document->ex_rate ?: '—' }}
            </td>
            <td style="width:24%">
                <div class="lbl">Freight prepaid at</div>
                {{ $document->freight_prepaid_at ?: '—' }}
            </td>
            <td style="width:24%">
                <div class="lbl">Freight payable at</div>
                {{ $document->freight_payable_at ?: '—' }}
            </td>
            <td style="width:36%" rowspan="2">
                <div class="lbl">Place and date of issue</div>
                <div>{{ $document->bl_place_of_issue ?: '—' }}</div>
                @if($document->bl_date_of_issue)
                    <div>{{ $document->bl_date_of_issue->format('d.m.Y') }}</div>
                @endif
            </td>
        </tr>
        <tr>
            <td style="width:16%; border-top:none;">&nbsp;</td>
            <td style="width:24%; border-top:none;">
                <div class="lbl">Total prepaid in</div>
                {{ $document->total_prepaid_in ?: '—' }}
            </td>
            <td style="width:24%; border-top:none;">
                <div class="lbl">No of original B(s)/L</div>
                {{ $document->no_of_original_bls ?: '—' }}
            </td>
        </tr>
    </table>

    <x-pdf.footer :company="$company" note="Computer generated draft — confirm with the shipping line before the final B/L is cut." />

</body>
</html>
