@php
    $title = 'Contacto — Finlia';
    $description = 'Escríbenos por alianzas, temas comerciales o para dejarnos una sugerencia sobre Finlia.';
@endphp

@extends('marketing.layout', ['title' => $title, 'description' => $description])

@section('content')

    <section class="seccion">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-lg-7">
                    <div class="text-center mb-5">
                        <p class="etiqueta-seccion">Contacto</p>
                        <h1 class="titulo-seccion">Cuéntanos</h1>
                        <p class="texto-seccion mx-auto" style="max-width: 48ch;">
                            Alianzas, temas comerciales o una idea para mejorar Finlia. Te respondemos
                            al correo que dejes.
                        </p>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-success" role="status">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div class="card p-4 p-md-5">
                        <form method="POST" action="{{ route('contact.store') }}" novalidate>
                            @csrf

                            <div class="mb-3">
                                <label for="reason" class="form-label fw-semibold">
                                    Motivo <span class="text-danger" aria-hidden="true">*</span>
                                </label>
                                <select id="reason" name="reason" class="form-select @error('reason') is-invalid @enderror" required>
                                    <option value="">Elige un motivo</option>
                                    @foreach ($motivos as $motivo)
                                        <option value="{{ $motivo->value }}" @selected(old('reason') === $motivo->value)>
                                            {{ $motivo->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <x-form-input label="Nombre" name="name" :value="old('name', auth()->user()?->name)"
                                          required placeholder="Ej: Camila Restrepo" />

                            <x-form-input label="Correo" name="email" type="email"
                                          :value="old('email', auth()->user()?->email)"
                                          required placeholder="Ej: camila@ejemplo.com" />

                            <div class="mb-3">
                                <label for="body" class="form-label fw-semibold">
                                    Mensaje <span class="text-danger" aria-hidden="true">*</span>
                                </label>
                                <textarea id="body" name="body" rows="6" required
                                          class="form-control @error('body') is-invalid @enderror"
                                          placeholder="Cuéntanos en qué podemos ayudarte.">{{ old('body') }}</textarea>
                                @error('body')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Campo trampa para bots: oculto y sin foco posible. Una
                                 persona nunca lo ve; un bot que rellena todo, sí. --}}
                            <div class="campo-trampa" aria-hidden="true">
                                <label for="sitio_web">No rellenes este campo</label>
                                <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off" value="{{ old('sitio_web') }}">
                            </div>

                            <button type="submit" class="btn btn-finlia btn-lg w-100 mt-2">Enviar mensaje</button>

                            <p class="small text-secondary mt-3 mb-0">
                                Al escribirnos, tratamos tus datos según
                                <a href="{{ route('data.policy') }}" class="enlace-nav">nuestra política</a>.
                                ¿Encontraste un error en la aplicación?
                                <a href="{{ route('bug-report.create') }}" class="enlace-nav">repórtalo desde tu cuenta</a>,
                                así nos llega con el detalle técnico.
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection
