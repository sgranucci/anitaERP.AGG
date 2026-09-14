@extends("theme.$theme.layout")
@section('titulo')
    Locales de venta
@endsection
@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/local_venta/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Ventas\LocalVentaListadoFiltros;
    $limpiarUrl = route('facturacion_local_locales');
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Locales de venta</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-local-venta',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => LocalVentaListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-local-venta',
                        'toggleId' => 'btn-toggle-filtros-local-venta',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_local_venta'),
                        'nuevoRegistroCan' => 'crear-local-venta',
                    ])
                    <a href="{{ route('facturacion_local_turno') }}" class="btn btn-outline-info btn-sm ml-1">
                        Turnos
                    </a>
                    <a href="{{ route('facturacion_local_pos') }}" class="btn btn-outline-info btn-sm ml-1">
                        <i class="fa fa-reply-all"></i> POS
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('facturacion_local_locales') }}" id="form-filtros-local-venta" class="mb-0">
                @include('ventas.facturacion_local.local_venta.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_local_venta',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>ID</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>PV</th>
                            <th>Depósito</th>
                            <th>Lista</th>
                            <th>Activo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datas as $data)
                        @php
                            $pvsLabel = $data->puntoventas->isNotEmpty()
                                ? $data->puntoventas->map(fn ($p) => trim(($p->codigo ?? '').' '.($p->nombre ?? '')))->implode(', ')
                                : trim(($data->puntoventa->codigo ?? '').' '.($data->puntoventa->nombre ?? ''));
                        @endphp
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->codigo }}</td>
                            <td>{{ $data->nombre }}</td>
                            <td>{{ $pvsLabel }}</td>
                            <td>{{ trim(($data->deposito->codigo ?? '').' '.($data->deposito->nombre ?? '')) }}</td>
                            <td>{{ $data->listaprecio->nombre ?? '' }}</td>
                            <td>{{ $data->activo ? 'Sí' : 'No' }}</td>
                            <td class="text-nowrap">
                                @if (can('editar-local-venta', false))
                                    <a href="{{ route('editar_local_venta', $data->id) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('borrar-local-venta', false))
                                    <a href="{{ route('eliminar_local_venta', $data->id) }}" class="btn-accion-tabla tooltipsC eliminar-registro" title="Eliminar">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $datas->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
