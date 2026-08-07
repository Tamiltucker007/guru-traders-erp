@props([
    'name' => null,
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
    'placeholder' => null,
    'col' => 'col-md-6',
    // Nested names like incentives[rosctl][percent_1] validate under the dotted
    // key incentives.rosctl.percent_1, so the error lookup needs it separately.
    'errorKey' => null,
    // One field per line, label on the left. Used where the form is short
    // enough to read top to bottom; $col is ignored in that mode.
    'horizontal' => false,
])

@php $errorKey ??= $name; @endphp

<div class="{{ $horizontal ? 'row form-line' : $col.' mb-3' }}">
    @if($label)
        <label for="{{ $name }}"
               class="{{ $horizontal ? 'col-sm-4 col-lg-3 col-form-label' : 'form-label' }} fw-semibold">
            {{ $label }}
            @if($required)<span class="req">*</span>@endif
        </label>
    @endif

    <div class="{{ $horizontal ? 'col-sm-8 col-lg-9' : '' }}">
        <input
            type="{{ $type }}"
            id="{{ $name }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            placeholder="{{ $placeholder }}"
            @if($required) required @endif
            {{ $attributes->merge(['class' => 'form-control'.($errors->has($errorKey) ? ' is-invalid' : '')]) }}
        >

        @error($errorKey)
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror

        @if($hint)
            <div class="form-text">{{ $hint }}</div>
        @endif
    </div>
</div>
