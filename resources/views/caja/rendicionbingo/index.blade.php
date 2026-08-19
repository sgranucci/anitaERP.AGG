@extends("theme.$theme.layout")
@section('titulo')
    Rendiciones bingo (caja)
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/rendicion_bingo/filtro.js') }}" type="text/javascript"></script>
<script>
    function eliminarRendicionBingo(event) {
        if (!confirm('¿Eliminar esta presentación de rendición bingo?')) {
            event.preventDefault();
        }
    }
</script>
@if (session('url_comprobante_pdf'))
<script>
(function () {
    var url = @json(session('url_comprobante_pdf'));
    if (url) {
        window.open(url, '_blank', 'noopener');
    }
})();
</script>
@endif
@endsection

<?php
use App\Support\Caja\Bingo\RendicionBingoCajaListadoFiltros;
?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
    $limpiarUrl = route('rendicionbingo', RendicionBingoCajaListadoFiltros::paraQueryStringEmpresa($filtros ?? []));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (!empty($errores))
        <div class="alert alert-danger">
            @foreach ((array) $errores as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Presentaciones rendición bingo en caja</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-rendicion-bingo',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => RendicionBingoCajaListadoFiltros::tieneCriteriosUsuario($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (código, ID, empresa, terminal…)',
                        'toggleTarget' => '#panel-filtros-rendicion-bingo',
                        'toggleId' => 'btn-toggle-filtros-rendicion-bingo',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_rendicionbingo', $retornoListadoQuery),
                        'nuevoRegistroCan' => 'crear-rendicion-bingo-caja',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('rendicionbingo') }}" id="form-filtros-rendicion-bingo" class="mb-0">
                @include('caja.rendicionbingo.partials.filtros_listado')
            </form>
            @include('caja.rendicionbingo.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_rendicionbingo',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th class="width20">ID</th>
                            <th>Código</th>
                            <th>Fecha rendición</th>
                            <th>Empresa</th>
                            <th>Jornada</th>
                            <th>Turno</th>
                            <th>Terminal</th>
                            <th class="text-right">Recaudación</th>
                            <th class="text-right">Depósito</th>
                            <th>Anita</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rendiciones as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ $row->codigo }}</td>
                            <td>{{ $row->fecharendicion?->format('d/m/Y H:i') }}</td>
                            <td>{{ $row->empresa?->nombre }}</td>
                            <td>{{ $row->fecha_jornada?->format('d/m/Y') ?? $row->jornada?->fecha_jornada?->format('d/m/Y') }}</td>
                            <td>
                                @if ($row->turnoOperativo?->turno?->nombre)
                                    {{ $row->turnoOperativo->turno->nombre }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td><small>{{ $row->turnoOperativo?->identificador_pc ?? '—' }}</small></td>
                            <td class="text-right text-nowrap">${{ number_format((float) $row->total_cartones, 2, ',', '.') }}</td>
                            <td class="text-right text-nowrap">${{ number_format((float) ($row->deposito ?? $row->saldo_final), 2, ',', '.') }}</td>
                            <td>
                                @if ($row->anita_sincronizado_en)
                                    <span class="badge badge-success" title="rendbingo">rendbingo {{ $row->anita_sincronizado_en->format('d/m/Y H:i') }}</span>
                                @else
                                    <span class="badge badge-secondary">Pendiente</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if (can('imprimir-rendicion-bingo-caja', false))
                                <a href="{{ route('imprimir_rendicion_bingo', ['id' => $row->id, 'inline' => 1]) }}"
                                   class="btn-accion-tabla tooltipsC" title="Comprobante rendición" target="_blank" rel="noopener">
                                    <i class="fa fa-print"></i>
                                </a>
                                @endif
                                @if ($row->turno_operativo_bingo_id && can('ver-comprobante-cierre-turno-bingo', false))
                                <a href="{{ route('bingo_cierre_turno_comprobante_cierre', ['id' => $row->turno_operativo_bingo_id, 'inline' => 1]) }}"
                                   class="btn-accion-tabla tooltipsC" title="Comprobante cierre turno" target="_blank" rel="noopener">
                                    <i class="fa fa-file-pdf-o text-danger"></i>
                                </a>
                                @endif
                                @if (
                                    can('borrar-rendicion-bingo-caja', false)
                                    && \App\Support\Caja\Bingo\RendicionBingoCajaPermiso::puedeEliminarPorFecha($row)
                                )
                                <form action="{{ route('eliminar_rendicionbingo', ['id' => $row->id] + $retornoListadoQuery) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method('delete')
                                    <button type="submit" onclick="eliminarRendicionBingo(event)" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar esta presentación">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="11" class="text-center text-muted">Sin rendiciones presentadas en caja.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (method_exists($rendiciones, 'links'))
            <div class="card-footer">
                {{ $rendiciones->appends($filtrosQuery ?? [])->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
