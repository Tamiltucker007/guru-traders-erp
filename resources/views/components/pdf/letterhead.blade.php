{{--
    Shared PDF letterhead — logo + company block.
    Pass $company (CompanyProfile). DomPDF needs an absolute image path.
--}}
@props([
    'company',
    'title' => null,
    'subtitle' => null,
])

@php
    $logoPath = $company->logoAbsolutePath();
@endphp

<style>
    .pdf-letterhead { width: 100%; border-collapse: collapse; margin: 0 0 10px; }
    .pdf-letterhead td { border: none; vertical-align: middle; padding: 0; }
    .pdf-letterhead .logo-cell { width: 150px; padding-right: 10px; }
    .pdf-letterhead .logo-cell img { max-height: 52px; max-width: 140px; }
    .pdf-letterhead .company-name { font-size: 14px; font-weight: bold; color: #111; margin: 0; }
    .pdf-letterhead .company-tag { font-size: 8px; color: #555; margin: 1px 0 3px; text-transform: uppercase; letter-spacing: .02em; }
    .pdf-letterhead .company-meta { font-size: 8.5px; color: #333; line-height: 1.35; white-space: pre-line; }
    .pdf-letterhead .doc-title { text-align: right; }
    .pdf-letterhead .doc-title .main { font-size: 13px; font-weight: bold; margin: 0; }
    .pdf-letterhead .doc-title .sub { font-size: 8.5px; color: #555; margin: 2px 0 0; }
    .pdf-letterhead-rule { border: none; border-top: 1.5px solid #1e3a5f; margin: 0 0 10px; }
</style>

<table class="pdf-letterhead">
    <tr>
        <td class="logo-cell">
            @if($logoPath)
                <img src="{{ $logoPath }}" alt="{{ $company->company_name }}">
            @else
                <x-brand-logo :size="48" />
            @endif
        </td>
        <td>
            <div class="company-name">{{ $company->company_name }}</div>
            @if($company->tagline)
                <div class="company-tag">{{ $company->tagline }}</div>
            @endif
            <div class="company-meta">{{ $company->address }}
@if($company->phone)Tel: {{ $company->phone }}@endif @if($company->email)· {{ $company->email }}@endif
@if($company->gstin)GSTIN: {{ $company->gstin }}@endif @if($company->iec_code)· IEC: {{ $company->iec_code }}@endif</div>
        </td>
        @if($title)
            <td class="doc-title" style="width:34%">
                <div class="main">{{ $title }}</div>
                @if($subtitle)<div class="sub">{{ $subtitle }}</div>@endif
            </td>
        @endif
    </tr>
</table>
<hr class="pdf-letterhead-rule">
