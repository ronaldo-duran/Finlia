@php
    /** @var \App\Models\Household|null $household */
    $currencies = [
        'COP' => 'Peso colombiano (COP)',
        'USD' => 'Dólar estadounidense (USD)',
        'EUR' => 'Euro (EUR)',
    ];
    $timezones = [
        'America/Bogota' => 'Colombia (Bogotá)',
        'America/Mexico_City' => 'México (Ciudad de México)',
        'America/Lima' => 'Perú (Lima)',
        'America/Argentina/Buenos_Aires' => 'Argentina (Buenos Aires)',
        'America/Santiago' => 'Chile (Santiago)',
    ];
@endphp

<x-form-input
    name="name"
    label="Nombre del hogar"
    :value="old('name', $household?->name)"
    required
    autofocus
    placeholder="Ej: Ronaldo & Vanessa"
    help="Como quieres llamar a este hogar. Lo verán todos sus miembros."
/>

<div class="mb-3">
    <label for="currency" class="form-label fw-semibold">
        Moneda <span class="text-danger" aria-hidden="true">*</span>
    </label>
    <select id="currency" name="currency"
            class="form-select @error('currency') is-invalid @enderror" required>
        @foreach ($currencies as $code => $label)
            <option value="{{ $code }}"
                @selected(old('currency', $household?->currency ?? 'COP') === $code)>
                {{ $label }}
            </option>
        @endforeach
    </select>
    @error('currency')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="timezone" class="form-label fw-semibold">
        Zona horaria <span class="text-danger" aria-hidden="true">*</span>
    </label>
    @php($tz = old('timezone', $household?->timezone ?? 'America/Bogota'))
    <select id="timezone" name="timezone"
            class="form-select @error('timezone') is-invalid @enderror" required>
        @foreach ($timezones as $code => $label)
            <option value="{{ $code }}" @selected($tz === $code)>{{ $label }}</option>
        @endforeach
    </select>
    @error('timezone')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    <div class="form-text">Las fechas y recordatorios usarán esta zona horaria.</div>
</div>
