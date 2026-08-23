@extends("theme.$theme.layout")
@section('titulo')
    Carga de Tickets - Ingreso de Proveedores
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/seguridad/ingreso_proveedor/filtro.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/seguridad/ingreso_proveedor/autorizar.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Seguridad\IngresoProveedorListadoFiltros;
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('ingreso_proveedor', IngresoProveedorListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    {{ !empty($bandejaPendientes) ? 'Pendientes de autorizar' : 'Carga de Tickets - Ingreso de Proveedores' }}
                </h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-ingreso-proveedor',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => IngresoProveedorListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda…',
                        'toggleTarget' => '#panel-filtros-ingreso-proveedor',
                        'toggleId' => 'btn-toggle-filtros-ingreso-proveedor',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => empty($bandejaPendientes) ? route('crear_ingreso_proveedor', $retornoListadoQuery) : null,
                        'nuevoRegistroCan' => empty($bandejaPendientes) ? 'crear-ingreso-proveedor' : null,
                    ])
                    @if (empty($bandejaPendientes) && can('autorizar-ingreso-proveedor', false))
                        <a href="{{ route('pendientes_ingreso_proveedor') }}" class="btn btn-outline-warning btn-sm ml-2">
                            <i class="fa fa-clock-o"></i> Pendientes
                        </a>
                    @endif
                    @if (!empty($bandejaPendientes))
                        <a href="{{ route('ingreso_proveedor') }}" class="btn btn-outline-info btn-sm ml-2">
                            <i class="fa fa-fw fa-reply-all"></i> Todos los tickets
                        </a>
                    @endif
                </div>
            </div>
            <form method="get" action="{{ !empty($bandejaPendientes) ? route('pendientes_ingreso_proveedor') : route('ingreso_proveedor') }}" id="form-filtros-ingreso-proveedor" class="mb-0">
                @include('seguridad.ingreso_proveedor.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            @include('seguridad.ingreso_proveedor.partials.filtros_externos', [
                'rutaIndex' => !empty($bandejaPendientes) ? 'pendientes_ingreso_proveedor' : 'ingreso_proveedor',
            ])
            @if (!empty($alcanceListado))
                <div class="px-3 pt-2">
                    <div class="alert alert-warning py-2 mb-0">{{ $alcanceListado }}</div>
                </div>
            @endif
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_ingreso_proveedor',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                @include('seguridad.ingreso_proveedor.partials.tabla_datos', [
                    'puede_ver_editar' => true,
                    'retornoListadoQuery' => $retornoListadoQuery,
                ])
            </div>
        </div>
    </div>
</div>
{{ $datas->appends($filtrosQuery ?? [])->links() }}
@include('seguridad.ingreso_proveedor.partials.modal_rechazo')
@endsection
