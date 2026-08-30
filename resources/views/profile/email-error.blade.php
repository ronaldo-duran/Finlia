@extends('layouts.app', ['title' => 'Cambio de correo'])

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card border-0 text-center">
                <div class="card-body p-4">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-finlia-subtle"
                         style="width: 64px; height: 64px;">
                        <i class="bi bi-envelope-x-fill fs-3 text-finlia"></i>
                    </div>

                    <h1 class="h4 mb-2">El enlace no funcionó</h1>
                    <p class="text-muted mb-4">{{ $message }}</p>

                    @auth
                        <a href="{{ route('profile.edit') }}" class="btn btn-finlia">
                            <i class="bi bi-person-circle me-1"></i> Volver a mi perfil
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-finlia">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar sesión
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </div>
@endsection
