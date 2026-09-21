@extends("theme.$theme.layout")
@section('titulo')
    Remitos internos
@endsection
@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/facturacion_local/remito_interno/filtro.js') }}" type="text/javascript"></script>
@endsection

@php
    use App\Support\Ventas\FacturacionLocal\RemitoInternoEstadosSupport;
    use App\Support\Ventas\FacturacionLocal\RemitoInternoListadoFiltros;
    $limpiarUrl = route('facturacion_local_remitos_internos');
@endphp

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                <h3 class="card-title mb-0">Remitos internos</h3>
                <div class="card-tools ml-auto d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-ri',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => RemitoInternoListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida…',
                        'toggleTarget' => '#panel-filtros-ri',
                        'toggleId' => 'btn-toggle-filtros-ri',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_remito_interno'),
                        'nuevoRegistroCan' => 'crear-remito-interno-facturacion-local',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('facturacion_local_remitos_internos') }}" id="form-filtros-ri" class="mb-0">
                @include('ventas.facturacion_local.remito_interno.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_remito_interno',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Nº</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Local</th>
                            <th>Destinatario</th>
                            <th>Depósito</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($datas as $data)
                        <tr>
                            <td>{{ $data->numero }}</td>
                            <td>{{ optional($data->fecha)->format('d/m/Y') }}</td>
                            <td>{{ RemitoInternoEstadosSupport::etiqueta($data->estado) }}</td>
                            <td>{{ trim(($data->localVenta->codigo ?? '').' '.($data->localVenta->nombre ?? '')) }}</td>
                            <td>{{ $data->destinatario ?: '—' }}</td>
                            <td>{{ trim(($data->deposito->codigo ?? '').' '.($data->deposito->nombre ?? '')) }}</td>
                            <td class="text-nowrap">
                                @if (can('ver-remito-interno-facturacion-local', false))
                                    <a href="{{ route('editar_remito_interno', $data->id) }}" class="btn-accion-tabla tooltipsC" title="Abrir remito">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if ($data->estado !== RemitoInternoEstadosSupport::BORRADOR && can('pdf-remito-interno-facturacion-local', false))
                                    <a href="{{ route('pdf_remito_interno', $data->id) }}" class="btn-accion-tabla tooltipsC" title="PDF" target="_blank" rel="noopener">
                                        <i class="fa fa-file-pdf-o text-danger"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Sin remitos internos.</td>
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
