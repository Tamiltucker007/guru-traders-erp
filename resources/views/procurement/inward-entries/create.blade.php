<x-app-layout>
    <x-slot name="header">New Goods Inward</x-slot>

    <x-ui.card title="Record Goods Inward Receipt" variant="primary">
        <x-slot name="actions">
            <a href="{{ route('procurement.inward-entries.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to Goods Inward
            </a>
        </x-slot>

        <form action="{{ route('procurement.inward-entries.store') }}" method="POST">
            @csrf

            @include('procurement.inward-entries._form')

            <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                <a href="{{ route('procurement.inward-entries.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Save Inward Receipt
                </button>
            </div>
        </form>
    </x-ui.card>
</x-app-layout>
