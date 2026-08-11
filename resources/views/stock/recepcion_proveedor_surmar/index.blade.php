@extends("theme.$theme.layout")
@section('titulo')
Recepción Surmar
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor_surmar/filtro.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Stock\RecepcionProveedorSurmarListadoFiltros;
    $tieneCriterios = RecepcionProveedorSurmarListadoFiltros::tieneCriteriosAplicados($filtros ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-truck"></i> Recepción Surmar</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-recepcion-surmar',
                        'filtroValor' => $filtros['filtro_valor'] ?? '',
                        'tieneCriterios' => $tieneCriterios,
                        'limpiarUrl' => route('recepcion_proveedor_surmar'),
                        'placeholder' => 'Nº, proveedor, estado…',
                        'toggleTarget' => '#panel-filtros-recepcion-surmar',
                        'toggleId' => 'btn-toggle-filtros-recepcion-surmar',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_recepcion_proveedor_surmar'),
                        'nuevoRegistroCan' => 'crear-recepcion-proveedor-surmar',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('recepcion_proveedor_surmar') }}" id="form-filtros-recepcion-surmar" class="mb-0">
                @include('stock.recepcion_proveedor_surmar.partials.filtros_listado')
            </form>
            <div class="card-body table-responsive p-0">
                <table id="tabla-paginada" class="table table-hover table-striped table-sm">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Nº</th>
                            <th>Fecha</th>
                            <th>OC</th>
                            <th>Proveedor</th>
                            <th>Estado</th>
                            <th>Ítems</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($coleccion as $item)
                            <tr>
                                <td>{{ $item->numerorecepcion }}</td>
                                <td>{{ optional($item->fecha)->format('d/m/Y') }}</td>
                                <td>{{ $item->numeroordencompra ?? '—' }}</td>
                                <td>{{ $item->nombreproveedor }}</td>
                                <td>
                                    @if ($item->estado === 'BORRADOR')
                                        <span class="badge badge-warning">Provisorio</span>
                                    @elseif ($item->estado === 'CONFIRMADA')
                                        <span class="badge badge-success">Confirmada</span>
                                    @else
                                        <span class="badge badge-secondary">{{ $item->estado }}</span>
                                    @endif
                                </td>
                                <td>{{ $item->recepcion_proveedor_articulos_count ?? '—' }}</td>
                                <td class="text-nowrap">
                                    @if (can('editar-recepcion-proveedor-surmar', false))
                                        <a href="{{ route('cargar_recepcion_proveedor_surmar', $item->id) }}" class="btn-accion-tabla tooltipsC" title="Abrir / continuar carga">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">Sin recepciones Surmar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (method_exists($coleccion, 'links'))
                <div class="card-footer clearfix">
                    {{ $coleccion->appends($filtrosQuery ?? [])->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
