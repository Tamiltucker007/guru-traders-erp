{{-- Shared PDF footer — company stamp line for generated export docs. --}}
@props([
    'company',
    'note' => 'This is a computer generated document of Guru Traders.',
])

<style>
    .pdf-footer {
        margin-top: 16px;
        padding-top: 6px;
        border-top: 1px solid #c5ced9;
        font-size: 8px;
        color: #555;
        text-align: center;
        line-height: 1.4;
    }
    .pdf-footer .name { font-weight: bold; color: #1e3a5f; }
</style>

<div class="pdf-footer">
    <span class="name">{{ $company->company_name }}</span>
    @if($company->phone) · {{ $company->phone }}@endif
    @if($company->email) · {{ $company->email }}@endif
    <br>{{ $note }}
</div>
