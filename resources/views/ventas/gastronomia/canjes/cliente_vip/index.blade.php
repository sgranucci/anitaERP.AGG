@extends("theme.$theme.layout")
@section('titulo')
    Clientes VIP — Canjes gastronomía
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/index.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/includes/listado-filtros.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/ventas/gastronomia/canjes/cliente_vip/filtro.js")}}" type="text/javascript"></script>
@endsection

<?php use App\Support\Ventas\ClienteVipGastronomiaListadoFiltros; ?>

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (! empty($sinRegistros ?? false))
        <div class="alert alert-info">
            @if (config('app.anita_sync_cliente_vip_gastronomia_index'))
            No hay clientes VIP en el ERP. Para importar desde Anita (Biyemas, Kandiko y Rebisco) ejecute en el servidor:
            <code>php artisan cliente-vip-gastronomia:sincronizar-anita</code>
            @else
            No hay clientes VIP cargados. Cree registros con <strong>Nuevo registro</strong>.
            @endif
        </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Clientes VIP gastronomía</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.ventas.boton-manual-canjes')
                    @if (config('app.anita_sync_cliente_vip_gastronomia_index') && can('actualizar-cliente-vip-gastronomia', false))
                    <form action="{{ route('sincronizar_cliente_vip_gastronomia_anita') }}" method="POST" class="d-inline mr-1">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-sm" title="Importar desde Anita (base_admin.clivipg)">
                            <i class="fa fa-sync"></i> Sincronizar Anita
                        </button>
                    </form>
                    @endif
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cliente-vip',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => ClienteVipGastronomiaListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_cliente_vip_gastronomia'),
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-cliente-vip',
                        'toggleId' => 'btn-toggle-filtros-cliente-vip',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_cliente_vip_gastronomia'),
                        'nuevoRegistroCan' => 'crear-cliente-vip-gastronomia',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_cliente_vip_gastronomia') }}" id="form-filtros-cliente-vip" class="mb-0">
                @include('ventas.gastronomia.canjes.cliente_vip.partials.filtros_listado', [
                    'limpiarUrl' => route('consultar_cliente_vip_gastronomia'),
                    'empresa_query' => $empresa_query ?? collect(),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_cliente_vip_gastronomia',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nro Anita</th>
                            <th>Documento</th>
                            <th>Apellido</th>
                            <th>Nombre</th>
                            <th>Nickname</th>
                            <th>Localidad</th>
                            <th>Empresa</th>
                            <th>Fecha alta</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->numeroid }}</td>
                            <td>{{ $data->nrodocumento }}</td>
                            <td>{{ $data->apellido }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->nickname }}</td>
                            <td>{{ $data->localidad }}</td>
                            <td>{{ optional($data->empresa)->nombre }}</td>
                            <td>{{ $data->fecha_alta_formato }}</td>
                            <td>
                                @if (can('editar-cliente-vip-gastronomia', false))
                                    <a href="{{ route('editar_cliente_vip_gastronomia', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-cliente-vip-gastronomia', false))
                                <form action="{{ route('eliminar_cliente_vip_gastronomia', ['id' => $data->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{{ $datas->appends($filtrosQuery ?? [])->links() }}
@endsection
