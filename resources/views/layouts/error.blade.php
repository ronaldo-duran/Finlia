<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title') · Finlia</title>
    @include('layouts.partials.favicon')
    @include('layouts.partials.theme-head')
    @vite(['resources/css/app.css'])
</head>
<body class="d-flex flex-column min-vh-100">
    <main class="d-flex flex-grow-1 align-items-center justify-content-center py-5 px-3">
        <div class="w-100 text-center" style="max-width: 460px;">
            <div class="d-inline-flex align-items-center gap-2 mb-4">
                <x-brandmark :size="40" />
                <span class="fs-3 fw-bold text-finlia">Finlia</span>
            </div>
            <div class="card border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="error-code text-body-tertiary fw-bold lh-1 mb-3">@yield('code')</div>

                    <h1 class="h4 mb-2">@yield('title')</h1>
                    <p class="text-muted mb-4">@yield('message')</p>
                    @yield('actions')
                </div>
            </div>
        </div>
    </main>
</body>
</html>
