@props([
    'label',
    'name',
    'id' => null,
    'options' => [],
    'valueKey' => 'id',
    'labelKey' => 'name',
    'selected' => null,
    'required' => false,
    'help' => null,
    'placeholder' => null,
])

@php
    // $options puede ser una colección de modelos o un array [valor => etiqueta].
    $isAssoc = is_array($options) && (! empty($options) && array_keys($options) !== range(0, count($options) - 1));
    // El id puede diferir del name (alta + modal de edición en la misma página).
    $selectId = $id ?? $name;
@endphp

<div class="mb-3">
    <label for="{{ $selectId }}" class="form-label fw-semibold">
        {{ $label }}
        @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif
    </label>

    <select
        id="{{ $selectId }}"
        name="{{ $name }}"
        class="form-select @error($name) is-invalid @enderror"
        @if ($required) required @endif
        aria-describedby="@error($name){{ $selectId }}-error @endif"
    >
        @if ($placeholder)
            <option value="" @selected(! old($name, $selected))>{{ $placeholder }}</option>
        @endif

        @foreach ($options as $key => $option)
            @php
                if ($isAssoc) {
                    $optValue = $key;
                    $optLabel = $option;
                } else {
                    $optValue = is_object($option) ? data_get($option, $valueKey) : data_get($option, $valueKey, $key);
                    $optLabel = is_object($option) ? data_get($option, $labelKey) : data_get($option, $labelKey, $option);
                }
            @endphp
            <option value="{{ $optValue }}" @selected((string) old($name, $selected) === (string) $optValue)>
                {{ $optLabel }}
            </option>
        @endforeach
    </select>

    @error($name)
        <div id="{{ $selectId }}-error" class="invalid-feedback d-block">{{ $message }}</div>
    @enderror

    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
