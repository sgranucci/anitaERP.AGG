<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo_pagina', 'Aprobación requisición') | Anita ERP</title>
    @php $themePortal = 'lte'; @endphp
    <link rel="stylesheet" href="{{ asset("assets/$themePortal/plugins/fontawesome-free/css/all.min.css") }}">
    <link rel="stylesheet" href="{{ asset("assets/$themePortal/dist/css/adminlte.min.css") }}">
    <style>
        body { background: #e9ecef; }
        .portal-wrap { max-width: 720px; margin: 0 auto; }
        .kv dt { font-weight: 600; color: #495057; }
        .kv dd { margin-bottom: .35rem; }
        @media (max-width: 575.98px) {
            .portal-card .card-body { padding: .75rem; }
        }
    </style>
    @stack('styles')
</head>
<body class="layout-top-nav text-sm">
    <div class="wrapper">
        <nav class="main-header navbar navbar-expand-md navbar-light navbar-white border-bottom-0 shadow-sm">
            <div class="container portal-wrap d-flex flex-wrap align-items-center justify-content-between">
                <span class="navbar-brand mb-0 h6 font-weight-bold">Anita ERP — @yield('portal_nav_subtitulo', 'Aprobación')</span>
            </div>
        </nav>
        <div class="content-wrapper py-3 py-md-4">
            <div class="content">
                <div class="container portal-wrap">
                    @if ($errors->any())
                    <div class="alert alert-danger shadow-sm">
                        <strong>No se pudo enviar el formulario.</strong>
                        <ul class="mb-0 pl-3 mt-2">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                    @yield('content')
                </div>
            </div>
        </div>
    </div>
    @include('includes.proceso_overlay_aviso', [
        'overlayId' => 'grabacion-overlay-global',
        'titulo' => 'Grabando…',
        'subtitulo' => 'No cierre ni recargue la pantalla y no vuelva a apretar el botón: cada envío extra genera un registro duplicado.',
    ])
    <script src="{{ asset("assets/$themePortal/plugins/jquery/jquery.min.js") }}"></script>
    <script src="{{ asset("assets/$themePortal/plugins/bootstrap/js/bootstrap.bundle.min.js") }}"></script>
    <script src="{{ asset('assets/js/grabacion-bloqueo-submit.js') }}?v={{ @filemtime(public_path('assets/js/grabacion-bloqueo-submit.js')) ?: time() }}"></script>
    @stack('scripts')
</body>
</html>
