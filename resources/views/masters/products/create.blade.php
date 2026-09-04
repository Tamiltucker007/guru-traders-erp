<x-app-layout>
    <x-slot name="header">{{ ! empty($isDuplicate) ? 'Duplicate Product' : 'Add Product' }}</x-slot>

    <x-ui.card :title="! empty($isDuplicate) ? 'Duplicate Product' : 'New Product'" variant="primary">
        @if(! empty($isDuplicate))
            <div class="alert alert-info small mb-3">
                Pre-filled from the source product. Enter a <strong>new Item Code</strong> and adjust Price Band / GST as needed.
            </div>
        @endif
        <form action="{{ route('masters.products.store') }}" method="POST">
            @csrf
            @include('masters.products._form', ['product' => $product ?? null])
        </form>
    </x-ui.card>
</x-app-layout>
