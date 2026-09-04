<x-app-layout>
    <x-slot name="header">Products</x-slot>

    <x-ui.card title="Product Master" variant="primary">
        <x-slot name="actions">
            @can('product.create')
                <a href="{{ route('masters.products.create') }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add Product
                </a>
            @endcan
        </x-slot>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label small text-body-secondary mb-1">Search</label>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                       class="form-control form-control-sm" placeholder="Code, name, HSN or barcode">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-body-secondary mb-1">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All categories</option>
                    @foreach($categories as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-body-secondary mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-secondary"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('masters.products.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th style="width:120px">Item Code</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th style="width:110px">HSN</th>
                        <th style="width:80px">Unit</th>
                        <th style="width:80px" class="text-end">GST</th>
                        <th style="width:110px">Status</th>
                        <th class="text-end" style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td class="text-body-secondary">{{ $loop->iteration + ($products->currentPage() - 1) * $products->perPage() }}</td>
                            <td><span class="badge text-bg-light border font-monospace">{{ $product->item_group_code }}</span></td>
                            <td>
                                <span class="fw-semibold">{{ $product->name }}</span>
                                @if($product->name_on_export_document && $product->name_on_export_document !== $product->name)
                                    <div class="small text-body-secondary">
                                        <i class="bi bi-file-earmark-text me-1"></i>{{ $product->name_on_export_document }}
                                    </div>
                                @endif
                            </td>
                            <td class="text-body-secondary">{{ $product->category?->name ?: '—' }}</td>
                            <td class="text-body-secondary font-monospace">{{ $product->hsn_code ?: '—' }}</td>
                            <td class="text-body-secondary">{{ $product->unit_po ?: '—' }}</td>
                            <td class="text-end text-body-secondary">{{ $product->gstRate?->label ?: '—' }}</td>
                            <td>
                                @can('product.edit')
                                    <form action="{{ route('masters.products.toggle-status', $product) }}" method="POST">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent"
                                                data-bs-toggle="tooltip" title="Click to toggle">
                                            <x-ui.status-badge :status="$product->status === 'active'" />
                                        </button>
                                    </form>
                                @else
                                    <x-ui.status-badge :status="$product->status === 'active'" />
                                @endcan
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('product.view')
                                        <a href="{{ route('masters.products.show', $product) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan
                                    @can('product.create')
                                        <a href="{{ route('masters.products.duplicate', $product) }}"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Duplicate">
                                            <i class="bi bi-copy"></i>
                                        </a>
                                    @endcan
                                    @can('product.edit')
                                        <a href="{{ route('masters.products.edit', $product) }}"
                                           class="btn btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan
                                    @can('product.delete')
                                        <x-ui.delete-form
                                            :action="route('masters.products.destroy', $product)"
                                            :confirm="'Delete product &quot;'.$product->name.'&quot;?'" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-state :colspan="9" icon="bi-box-seam"
                                          title="No products yet"
                                          message="Add a category first, then create products under it." />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($products->hasPages())
            <div class="mt-3">{{ $products->links('pagination::bootstrap-5') }}</div>
        @endif

        <div class="text-body-secondary small mt-2">
            Showing {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} of {{ $products->total() }}
        </div>
    </x-ui.card>
</x-app-layout>
