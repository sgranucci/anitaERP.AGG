@extends("theme.$theme.layout")
@section('titulo')
    Cumplir requisici&oacute;n de compra
@endsection

@section("scripts")
@php
    $opsFiltro = [];
    foreach (\App\Support\Compras\CumplimientoRequisicionCompraListadoFiltros::CAMPOS as $key => $def) {
        $opsFiltro[$key] = \App\Support\Compras\CumplimientoRequisicionCompraListadoFiltros::operadoresParaCampo($key);
    }
@endphp
<script>window.cumplimientoReqCompraFiltroOperadores = @json($opsFiltro);</script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/cumplir_requisicion_compra/filtro_listado.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cumplir requisici&oacute;n de compra</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cumple-req-compra',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => \App\Support\Compras\CumplimientoRequisicionCompraListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('cumplir_requisicion_compra'),
                        'placeholder' => 'B&uacute;squeda r&aacute;pida&hellip;',
                        'toggleTarget' => '#panel-filtros-cumple-req-compra',
                        'toggleId' => 'btn-toggle-filtros-cumple-req-compra',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_cumplir_requisicion_compra'),
                        'nuevoRegistroCan' => 'cumplir-requisicion-compra',
                        'nuevoRegistroLabel' => 'Nuevo cumplimiento',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('cumplir_requisicion_compra') }}" id="form-filtros-cumple-req-compra" class="mb-0">
                @include('compras.cumplir_requisicion_compra.partials.filtros_listado', [
                    'limpiarUrl' => route('cumplir_requisicion_compra'),
                ])
            </form>
            <div class="card-body pb-0">
                <div class="btn-group-app-toolbar mb-2">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'lista_cumplir_requisicion_compra',
                        'queryparams' => $filtrosQuery ?? [],
                    ])
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background-color:#85C1E9;color:#17202A;">
                        <tr>
                            <th>N&ordm;</th>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Empresa</th>
                            <th>Requisiciones</th>
                            <th>L&iacute;neas</th>
                            <th>Estado</th>
                            <th class="width120" data-orderable="false">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($coleccion as $row)
                        @php
                            $reqNros = $row->articulos->pluck('requisicion.numerorequisicion')->filter()->unique()->implode(', ');
                        @endphp
                        <tr>
                            <td>{{ $row->numero }}</td>
                            <td>{{ optional($row->fecha)->format('d/m/Y H:i') }}</td>
                            <td>{{ $row->usuario?->nombre ?? '' }}</td>
                            <td>{{ $row->empresa?->nombre ?? '—' }}</td>
                            <td>{{ $reqNros !== '' ? $reqNros : '—' }}</td>
                            <td class="text-right">{{ $row->articulos_count ?? 0 }}</td>
                            <td>
                                @if ($row->estado === \App\Models\Compras\CumplimientoRequisicionCompra::ESTADO_ACTIVO)
                                    <span class="badge badge-success">ACTIVO</span>
                                @else
                                    <span class="badge badge-secondary">REVERTIDO</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                <a href="{{ route('consultar_cumplir_requisicion_compra', ['id' => $row->id]) }}" class="btn-accion-tabla tooltipsC" title="Consultar">
                                    <i class="fa fa-search"></i>
                                </a>
                                <a href="{{ route('imprimir_pdf_cumplir_requisicion_compra', ['id' => $row->id]) }}" class="btn-accion-tabla tooltipsC" title="Imprimir PDF" target="_blank" rel="noopener">
                                    <i class="fa fa-file-pdf-o text-danger"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center text-muted">Sin cumplimientos registrados</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">
                {{ $coleccion->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
