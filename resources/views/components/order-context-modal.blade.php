{{-- Reusable Bootstrap modal — Order cockpit (buyer / supplier / jobber / payment / stock / min price). --}}
@props([
    'orderContext' => ['available' => false],
    'modalId' => 'orderContextModal',
    'buttonLabel' => 'Order cockpit',
])

@if($orderContext['available'] ?? false)
    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
        <i class="bi bi-diagram-3 me-1"></i> {{ $buttonLabel }}
    </button>

    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $modalId }}Label">
                        <i class="bi bi-diagram-3 me-1"></i> Order cockpit
                        @if(! empty($orderContext['summary']))
                            <span class="fs-6 fw-normal text-body-secondary d-block d-md-inline md-ms-2">{{ $orderContext['summary'] }}</span>
                        @endif
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('export.ocr._order-context', ['orderContext' => $orderContext])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endif
