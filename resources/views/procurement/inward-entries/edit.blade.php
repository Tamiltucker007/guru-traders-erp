<x-app-layout>
    <x-slot name="header">Edit Goods Inward {{ $inwardEntry->inward_no }}</x-slot>

    <x-ui.card title="Edit Goods Inward Receipt — {{ $inwardEntry->inward_no }}" variant="primary">
        <x-slot name="actions">
            <a href="{{ route('procurement.inward-entries.show', $inwardEntry) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-eye me-1"></i> View Receipt
            </a>
            <a href="{{ route('procurement.inward-entries.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
        </x-slot>

        <form action="{{ route('procurement.inward-entries.update', $inwardEntry) }}" method="POST">
            @csrf
            @method('PUT')

            @include('procurement.inward-entries._form')

            <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                <a href="{{ route('procurement.inward-entries.show', $inwardEntry) }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Update Inward Receipt
                </button>
            </div>
        </form>
    </x-ui.card>
</x-app-layout>
