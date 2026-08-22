@props([
    'label',
    'name',
    'id' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'autocomplete' => null,
    'autofocus' => false,
    'help' => null,
    'placeholder' => '',
    'step' => null,
])

@php
    // El id puede diferir del name: en una misma página puede haber dos
    // formularios con el mismo campo (p. ej. alta + modal de edición).
    $inputId = $id ?? $name;
@endphp

<div class="mb-3">
    <label for="{{ $inputId }}" class="form-label fw-semibold">
        {{ $label }}
        @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif
    </label>

    <input
        id="{{ $inputId }}"
        type="{{ $type }}"
        name="{{ $name }}"
        class="form-control @error($name) is-invalid @enderror"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($step) step="{{ $step }}" @endif
        @if ($autofocus) autofocus @endif
        @if ($required) required @endif
        aria-describedby="@error($name){{ $inputId }}-error @endif"
    >

    @error($name)
        <div id="{{ $inputId }}-error" class="invalid-feedback d-block">{{ $message }}</div>
    @enderror

    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
