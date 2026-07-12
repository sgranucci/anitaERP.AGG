@extends("theme.$theme.layout")
@section('titulo')
    Cumplir requisici&oacute;n de sala
@endsection

@section("scripts")
@php
    $opsFiltro = [];
    foreach (\App\Support\Sala\CumplimientoRequisicionSalaListadoFiltros::CAMPOS as $key => $def) {
        $opsFiltro[$key] = \App\Support\Sala\CumplimientoRequisicionSalaListadoFiltros::operadoresParaCampo($key);
    }
@endphp
<script>window.cumplimientoReqSalaFiltroOperadores = @json($opsFiltro);</script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sala/cumplir_requisicion_sala/filtro_listado.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (!empty($requisicionSalaId))
            <div class="alert alert-info py-2">
                Filtrado por requisici&oacute;n id {{ $requisicionSalaId }}.
                <a href="{{ route('cumplir_requisicion_sala') }}" class="ml-2">Ver todos</a>
            </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Cumplir requisici&oacute;n de sala</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-cumple-req-sala',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => \App\Support\Sala\CumplimientoRequisicionSalaListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('cumplir_requisicion_sala'),
                        'placeholder' => 'B&uacute;squeda r&aacute;pida&hellip;',
                        'toggleTarget' => '#panel-filtros-cumple-req-sala',
                        'toggleId' => 'btn-toggle-filtros-cumple-req-sala',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_cumplir_requisicion_sala'),
                        'nuevoRegistroCan' => 'cumplir-requisicion-sala',
                        'nuevoRegistroLabel' => 'Nuevo cumplimiento',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('cumplir_requisicion_sala') }}" id="form-filtros-cumple-req-sala" class="mb-0">
                @if (!empty($requisicionSalaId))
                    <input type="hidden" name="requisicion_sala_id" value="{{ $requisicionSalaId }}">
                @endif
                @include('sala.cumplir_requisicion_sala.partials.filtros_listado', [
                    'limpiarUrl' => route('cumplir_requisicion_sala', !empty($requisicionSalaId) ? ['requisicion_sala_id' => $requisicionSalaId] : []),
                ])
            </form>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>N&ordm;</th>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Empresa</th>
                            <th>L&iacute;neas</th>
                            <th>Estado</th>
                            <th class="width120" data-orderable="false">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($coleccion as $row)
                        <tr>
                            <td>{{ $row->numero }}</td>
                            <td>{{ optional($row->fecha)->format('d/m/Y H:i') }}</td>
                            <td>{{ $row->usuario?->nombre ?? '' }}</td>
                            <td>{{ $row->empresa?->nombre ?? '—' }}</td>
                            <td class="text-right">{{ $row->articulos_count ?? 0 }}</td>
                            <td>
                                @if ($row->estado === \App\Models\Sala\CumplimientoRequisicionSala::ESTADO_ACTIVO)
                                    <span class="badge badge-success">ACTIVO</span>
                                @else
                                    <span class="badge badge-secondary">REVERTIDO</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                <a href="{{ route('consultar_cumplir_requisicion_sala', ['id' => $row->id]) }}" class="btn-accion-tabla tooltipsC" title="Consultar">
                                    <i class="fa fa-search"></i>
                                </a>
                                <a href="{{ route('imprimir_pdf_cumplir_requisicion_sala', ['id' => $row->id]) }}" class="btn-accion-tabla tooltipsC" title="Imprimir PDF" target="_blank" rel="noopener">
                                    <i class="fa fa-file-pdf-o text-danger"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted">Sin cumplimientos registrados</td></tr>
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
