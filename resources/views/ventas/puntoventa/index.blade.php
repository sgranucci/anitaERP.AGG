@extends("theme.$theme.layout")
@section('titulo')
    Puntos de Venta
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/puntoventa/filtro.js') }}" type="text/javascript"></script>
@if(($empresasArca ?? collect())->isNotEmpty())
<script>
    window.PUNTOVENTA_ARCA_PRELOAD = {
        url: @json(route('puntoventa_arca_puntos_venta')),
        empresas: @json(($empresasArca ?? collect())->map(fn ($e) => ['id' => $e->id, 'nombre' => $e->nombre])->values())
    };
</script>
<script src="{{ asset('assets/pages/scripts/ventas/puntoventa/index-arca.js') }}" type="text/javascript"></script>
@endif
@endsection

@php
    use App\Support\Ventas\PuntoventaListadoFiltros;
@endphp

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (! empty($sinPuntosCargados ?? false))
        <div class="alert alert-info">
            @if (config('app.anita_sync_puntoventa_index'))
            No hay puntos de venta en el ERP. Para importar desde Anita ejecute en el servidor:
            <code>php artisan puntoventa:sincronizar-anita</code>
            @else
            No hay puntos de venta en el ERP. Creá un registro con <strong>Nuevo registro</strong>
            o activá <code>ANITA_SYNC_PUNTOVENTA_INDEX</code> para sincronizar al abrir este listado.
            @endif
        </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Puntos de venta</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-puntoventa',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => PuntoventaListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('puntoventa'),
                        'placeholder' => 'Búsqueda rápida…',
                        'toggleTarget' => '#panel-filtros-puntoventa',
                        'toggleId' => 'btn-toggle-filtros-puntoventa',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_puntoventa', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-puntos-de-venta',
                    ])
                    @if (config('app.anita_sync_puntoventa_index') && can('actualizar-puntos-de-venta', false))
                    <form action="{{ route('sincronizar_puntoventa_anita') }}" method="POST" class="d-inline ml-1"
                          onsubmit="return confirm('La sincronizaci\u00f3n puede tardar varios minutos. Si aparece error 504 (tiempo de espera), ejecute en el servidor:\nphp artisan puntoventa:sincronizar-anita\n\n\u00bfContinuar?');">
                        @csrf
                        <button type="submit" class="btn btn-light btn-sm"
                                title="Importar o actualizar desde Anita (tabla sucursal)">
                            <i class="fa fa-fw fa-refresh"></i> Sincronizar Anita
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            <form method="get" action="{{ route('puntoventa') }}" id="form-filtros-puntoventa" class="mb-0">
                @include('ventas.puntoventa.partials.filtros_listado', [
                    'limpiarUrl' => route('puntoventa'),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                <div class="px-2 pt-1 d-flex flex-nowrap align-items-center">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'lista_puntoventa',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                </div>
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">ID</th>
                            <th>Nombre</th>
                            <th>Código</th>
                            <th>Empresa</th>
                            <th>Domicilio</th>
                            <th>Localidad</th>
                            <th>Provincia</th>
                            <th>Modo Facturación</th>
                            <th>Estado</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->empresas->nombre ?? '' }}</td>
                            <td>{{ $data->domicilio }}</td>
                            <td>{{ $data->localidades->nombre ?? '' }}</td>
                            <td>{{ $data->provincias->nombre ?? '' }}</td>
                            <td>{{ $modofacturacionEnum[$data->modofacturacion] ?? $data->modofacturacion }}</td>
                            <td>{{ $estadoEnum[$data->estado] ?? $data->estado }}</td>
                            <td class="text-nowrap">
                                @if (can('editar-puntos-de-venta', false))
                                <a href="{{ route('editar_puntoventa', ['id' => $data->id] + $retornoListadoQuery) }}"
                                   class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                </a>
                                @endif
                                @if (can('borrar-puntos-de-venta', false))
                                <form action="{{ route('eliminar_puntoventa', ['id' => $data->id]) }}"
                                      class="d-inline form-eliminar" method="POST">
                                    @csrf @method("delete")
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC"
                                            title="Eliminar este registro">
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
            @if (method_exists($datas, 'links'))
                <div class="card-footer clearfix">
                    {{ $datas->appends($filtrosQuery ?? [])->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
