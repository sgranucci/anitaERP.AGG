{{-- Solo acceso con hash (mail / portal aprobación): sin menú lateral ni cabecera de aplicación. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Requisición') | Anita ERP</title>
    @php $themeV = 'lte'; @endphp
    <link rel="stylesheet" href="{{ asset("assets/$themeV/plugins/fontawesome-free/css/all.min.css") }}">
    <link rel="stylesheet" href="{{ asset("assets/$themeV/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css") }}">
    <link rel="stylesheet" href="{{ asset("assets/$themeV/plugins/datatables-responsive/css/responsive.bootstrap4.min.css") }}">
    <link rel="stylesheet" href="{{ asset("assets/$themeV/plugins/datatables-buttons/css/buttons.bootstrap4.min.css") }}">
    <link rel="stylesheet" href="{{ asset('assets/css/datatable.css') }}">
    <link rel="stylesheet" href="{{ asset("assets/$themeV/dist/css/adminlte.min.css") }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}">
    @stack('styles')
    @routes
</head>
<body class="layout-top-nav text-sm" style="background:#e9ecef;">
    <div class="wrapper">
        <nav class="main-header navbar navbar-expand navbar-light navbar-white border-bottom shadow-sm mb-0">
            <span class="navbar-brand mb-0 h6 font-weight-bold text-secondary">Anita ERP — Consulta de requisición</span>
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="(function(){try{window.close();}catch(e){}setTimeout(function(){if(window.history.length>1){window.history.back();}else{alert('Cierre esta pestaña del navegador o use Atrás.');}},150);})();" title="Cerrar esta solapa">
                        <i class="fa fa-times"></i> Cerrar solapa
                    </button>
                </li>
            </ul>
        </nav>
        <div class="content-wrapper m-0" style="min-height: auto;">
            <section class="content pt-3 pb-4">
                <div class="container-fluid">
                    @yield('contenido')
                </div>
            </section>
        </div>
    </div>
    @include('includes.proceso_overlay_aviso', [
        'overlayId' => 'grabacion-overlay-global',
        'titulo' => 'Grabando…',
        'subtitulo' => 'No cierre ni recargue la pantalla y no vuelva a apretar el botón: cada envío extra genera un registro duplicado.',
    ])
    <script>
        window.Laravel = { baseUrl: '{{ url('/') }}' };
        var carpetaBase = '{{ config('app.app_carpeta') }}';
    </script>
    <script src="{{ asset("assets/$themeV/plugins/jquery/jquery.min.js") }}"></script>
    <script src="{{ asset("assets/$themeV/plugins/bootstrap/js/bootstrap.bundle.min.js") }}"></script>
    <script src="{{ asset("assets/$themeV/dist/js/adminlte.min.js") }}"></script>
    <script src="{{ asset("assets/$themeV/plugins/datatables/jquery.dataTables.min.js") }}"></script>
    <script src="{{ asset("assets/$themeV/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js") }}"></script>
    <script src="{{ asset("assets/$themeV/plugins/datatables-responsive/js/dataTables.responsive.min.js") }}"></script>
    <script src="{{ asset("assets/$themeV/plugins/datatables-responsive/js/responsive.bootstrap4.min.js") }}"></script>
    <script src="{{ asset("assets/$themeV/plugins/datatables-buttons/js/dataTables.buttons.min.js") }}"></script>
    <script src="{{ asset("assets/$themeV/plugins/datatables-buttons/js/buttons.bootstrap4.min.js") }}"></script>
    <script src="{{ asset("assets/$themeV/plugins/jszip/jszip.min.js") }}"></script>
    <script src="{{ asset("assets/$themeV/plugins/pdfmake/pdfmake.min.js") }}"></script>
    <script src="{{ asset("assets/$themeV/plugins/pdfmake/vfs_fonts.js") }}"></script>
    <script src="{{ asset("assets/$themeV/plugins/datatables-buttons/js/buttons.html5.min.js") }}"></script>
    <script src="{{ asset("assets/$themeV/plugins/datatables-buttons/js/buttons.print.min.js") }}"></script>
    <script src="{{ asset("assets/$themeV/plugins/datatables-buttons/js/buttons.colVis.min.js") }}"></script>
    @yield('scriptsPlugins')
    <script src="{{ asset('assets/js/jquery-validation/jquery.validate.min.js') }}"></script>
    <script src="{{ asset('assets/js/jquery-validation/localization/messages_es.min.js') }}"></script>
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="{{ asset('assets/js/scripts.js') }}"></script>
    <script src="{{ asset('assets/js/funciones.js') }}"></script>
    <script src="{{ asset('assets/js/grabacion-bloqueo-submit.js') }}?v={{ @filemtime(public_path('assets/js/grabacion-bloqueo-submit.js')) ?: time() }}"></script>
    @yield('scripts')
</body>
</html>
