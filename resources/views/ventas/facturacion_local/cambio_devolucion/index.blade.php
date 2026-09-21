@extends("theme.$theme.layout")
@section('titulo')
    Cambios / devoluciones marketplace
@endsection
@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/cambio_devolucion/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceEstadosSupport;
    use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceListadoFiltros;
    use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceCatalogoSupport;
    $limpiarUrl = route('facturacion_local_cambios_devolucion');
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Cambios / devoluciones marketplace</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cdm',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => CambioDevolucionMarketplaceListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida…',
                        'toggleTarget' => '#panel-filtros-cdm',
                        'toggleId' => 'btn-toggle-filtros-cdm',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_cambio_devolucion_marketplace'),
                        'nuevoRegistroCan' => 'crear-cambio-devolucion-marketplace-facturacion-local',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('facturacion_local_cambios_devolucion') }}" id="form-filtros-cdm" class="mb-0">
                @include('ventas.facturacion_local.cambio_devolucion.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_cambio_devolucion_marketplace',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Nº</th>
                            <th>Estado</th>
                            <th>Canal</th>
                            <th>Local</th>
                            <th>FAC original</th>
                            <th>FAC reemplazo</th>
                            <th>Receptor</th>
                            <th>Diferencia</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                        <tr>
                            <td>{{ $data->numero }}</td>
                            <td>{{ CambioDevolucionMarketplaceEstadosSupport::etiqueta($data->estado) }}</td>
                            <td>{{ CambioDevolucionMarketplaceCatalogoSupport::CANALES[$data->canal] ?? $data->canal }}</td>
                            <td>{{ trim(($data->localVenta->codigo ?? '').' '.($data->localVenta->nombre ?? '')) }}</td>
                            <td>{{ $data->ventaOriginal->codigo ?? $data->venta_original_id }}</td>
                            <td>{{ $data->ventaReemplazo->codigo ?? '—' }}</td>
                            <td>{{ $data->receptor_nombre }}</td>
                            <td class="text-right">
                                @if ((float) $data->diferencia_importe > 0.009)
                                    {{ number_format((float) $data->diferencia_importe, 2, ',', '.') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if (can('ver-cambio-devolucion-marketplace-facturacion-local', false))
                                    <a href="{{ route('editar_cambio_devolucion_marketplace', $data->id) }}" class="btn-accion-tabla tooltipsC" title="Abrir legajo">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Sin legajos.</td>
                        </tr>
                        @endforelse
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
