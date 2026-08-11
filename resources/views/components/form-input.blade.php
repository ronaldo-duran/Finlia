@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'autocomplete' => null,
    'autofocus' => false,
    'help' => null,
    'placeholder' => '',
])

<div class="mb-3">
    <label for="{{ $name }}" class="form-label fw-semibold">
        {{ $label }}
        @if ($required)<span class="text-danger" aria-hidden="true">*</span>@endif
    </label>

    <input
        id="{{ $name }}"
        type="{{ $type }}"
        name="{{ $name }}"
        class="form-control @error($name) is-invalid @enderror"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($autofocus) autofocus @endif
        @if ($required) required @endif
        aria-describedby="@error($name){{ $name }}-error @endif"
    >

    @error($name)
        <div id="{{ $name }}-error" class="invalid-feedback d-block">{{ $message }}</div>
    @enderror

    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
