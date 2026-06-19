@php
    $modoConsulta = request()->input('vista') === 'consulta';
@endphp
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $modoConsulta ? '[Consulta] ' : '' }}@yield('titulo', 'Anita ERP') | Anita ERP</title>
    <!-- Tell the browser to be responsive to screen width -->
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{asset("assets/$theme/plugins/fontawesome-free/css/all.min.css")}}">
    <link rel="stylesheet" href="{{asset("assets/$theme/plugins/fontawesome-free/css/v4-shims.min.css")}}">
  	<!-- DataTables -->
  	<link rel="stylesheet" href="{{asset("assets/$theme/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css")}}">
  	<link rel="stylesheet" href="{{asset("assets/$theme/plugins/datatables-responsive/css/responsive.bootstrap4.min.css")}}">
  	<link rel="stylesheet" href="{{asset("assets/$theme/plugins/datatables-buttons/css/buttons.bootstrap4.min.css")}}">
	<link rel="stylesheet" href="{{asset("assets/css/datatable.css")}}">
    <!-- Ionicons -->
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{asset("assets/$theme/dist/css/adminlte.min.css")}}">
    <!-- AdminLTE Skins. Choose a skin from the css/skins -->
    <!-- Google Font: Source Sans Pro -->
    <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    @yield("styles")

    <link rel="stylesheet" href="{{asset("assets/css/custom.css")}}">
    <link rel="stylesheet" href="{{asset("assets/css/sidebar.css")}}">
    <link rel="stylesheet" href="{{asset("assets/css/barra-tareas.css")}}">

    @routes
    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
      <![endif]-->

    <!-- Google Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
</head>

<body class="hold-transition sidebar-mini{{ $modoConsulta ? ' modo-consulta sidebar-collapse' : '' }}">
    <!-- Site wrapper -->
    <div class="wrapper">
        <!-- Inicio Header -->
        @include("theme/$theme/header")
        <!-- Fin Header -->
        @if (!$modoConsulta)
            <!-- Inicio Aside -->
            @include("theme/$theme/aside")
            <!-- Fin Aside -->
        @endif
        <div class="content-wrapper">
            <!-- Content Header (Page header) -->
            <section class="content-header">

            </section>
            <section class="content">
                <div class="container-fluid">
                    @yield('contenido')
                </div>
            </section>
        </div>
        <!--Inicio Footer -->
        @include("theme/$theme/footer")
        <!-- Fin Footer -->
        <!-- Control Sidebar -->
        <aside class="control-sidebar control-sidebar-dark">
          <!-- Control sidebar content goes here -->
        </aside>
        <!-- /.control-sidebar -->
        <!--Inicio de ventana modal para login con más de un rol -->
		@if(session()->get("roles") && count(session()->get("roles")) > 1)
            @csrf
            <div class="modal fade" id="modal-seleccionar-rol" data-rol-set="{{empty(session()->get("rol_id")) ? 'NO' : 'SI'}}" tabindex="-1" data-backdrop="static" data-keyboard="false">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">Roles de Usuario</h4>
                        </div>
                        <div class="modal-body">
                            <p>Cuentas con mas de un Rol en la plataforma, a continuación seleccione con cual de ellos desea trabajar</p>
                            @foreach(session()->get("roles") as $key => $rol)
                                <li>
                                    <a href="#" class="asignar-rol" data-rolid="{{$rol['id']}}" data-rolnombre="{{$rol["nombre"]}}">
                                        {{$rol["nombre"]}}
                                    </a>
                                </li>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
    <script>
        @php
            $laravelUsuario = null;
            if (auth()->check()) {
                $u = auth()->user()->loadMissing(['centrocostos', 'sectorLegajocompra', 'oficinacompras', 'vendedores']);
                $cc = $u->centrocostos;
                $laravelUsuario = [
                    'id' => $u->id,
                    'usuario' => $u->usuario,
                    'nombre' => $u->nombre,
                    'email' => $u->email,
                    'centrocosto_id' => $u->centrocosto_id,
                    'centrocosto_codigo' => $cc?->codigo,
                    'centrocosto_nombre' => $cc?->nombre,
                    'sector_legajocompra_id' => $u->sector_legajocompra_id,
                    'sector_legajocompra_nombre' => $u->sectorLegajocompra?->nombre,
                    'vendedor_id' => $u->vendedor_id,
                    'vendedor_nombre' => $u->vendedores?->nombre,
                    'oficinacompra_id' => $u->oficinacompra_id,
                    'oficinacompra_nombre' => $u->oficinacompras?->nombre,
                ];
            }
        @endphp
        window.Laravel = {
            baseUrl: '{{ url('/') }}',
            usuario: @json($laravelUsuario),
        };
        var carpetaBase = @json(rtrim((string) config('app.app_carpeta', ''), '/'));
        function resolverCarpetaBaseApp() {
            if (typeof carpetaBase !== 'undefined' && carpetaBase != null && String(carpetaBase).trim() !== '') {
                return String(carpetaBase).replace(/\/$/, '');
            }
            var loc = window.location.pathname || '';
            var m = loc.match(/^(.+)\/(ventas|caja|stock|compras|contable|seguridad|presupuesto|ticket|admin|uif)\//);
            if (m && m[1]) {
                return m[1];
            }
            return '';
        }
        carpetaBase = resolverCarpetaBaseApp();
    </script>
    <script src="{{asset("assets/$theme/plugins/jquery/jquery.min.js")}}"></script>
    <!-- Bootstrap 4 -->
    <script src="{{asset("assets/$theme/plugins/bootstrap/js/bootstrap.bundle.min.js")}}"></script>
    <!-- AdminLTE App -->
    <script src="{{asset("assets/$theme/dist/js/adminlte.min.js")}}"></script>
	<!-- DataTables  & Plugins -->
	<script src="{{asset("assets/$theme/plugins/datatables/jquery.dataTables.min.js")}}"></script>
	<script src="{{asset("assets/$theme/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js")}}"></script>
	<script src="{{asset("assets/$theme/plugins/datatables-responsive/js/dataTables.responsive.min.js")}}"></script>
	<script src="{{asset("assets/$theme/plugins/datatables-responsive/js/responsive.bootstrap4.min.js")}}"></script>
	<script src="{{asset("assets/$theme/plugins/datatables-buttons/js/dataTables.buttons.js")}}"></script>
	<script src="{{asset("assets/$theme/plugins/datatables-buttons/js/dataTables.buttons.min.js")}}"></script>
	<script src="{{asset("assets/$theme/plugins/datatables-buttons/js/buttons.bootstrap4.min.js")}}"></script>
    <script src="{{asset("assets/$theme/plugins/jszip/jszip.min.js")}}"></script>
	<script src="{{asset("assets/$theme/plugins/pdfmake/pdfmake.min.js")}}"></script>
	<script src="{{asset("assets/$theme/plugins/pdfmake/vfs_fonts.js")}}"></script>
	<script src="{{asset("assets/$theme/plugins/datatables-buttons/js/buttons.html5.min.js")}}"></script>
	<script src="{{asset("assets/$theme/plugins/datatables-buttons/js/buttons.print.min.js")}}"></script>
	<script src="{{asset("assets/$theme/plugins/datatables-buttons/js/buttons.colVis.min.js")}}"></script>
    <!-- AdminLTE for demo purposes -->
    @yield("scriptsPlugins")
    <script src="{{asset("assets/js/jquery-validation/jquery.validate.min.js")}}"></script>
    <script src="{{asset("assets/js/jquery-validation/localization/messages_es.min.js")}}"></script>
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="{{asset("assets/js/scripts.js")}}"></script>
    <script src="{{asset("assets/js/funciones.js")}}"></script>
    <script src="{{asset('assets/js/modo-consulta.js')}}"></script>
    @auth
        <script src="{{ asset('assets/js/barra-tareas.js') }}"></script>
    @endauth
    @yield("scripts")
</body>

</html>
