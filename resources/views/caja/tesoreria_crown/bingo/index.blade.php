@extends("theme.$theme.layout")

@section('titulo')
    Bingo Crown
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}?v={{ filemtime(public_path('assets/pages/scripts/includes/listado-filtros.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/listado.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/listado.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.mensaje')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'tesoreria-crown-overlay',
    'tituloId' => 'tesoreria-crown-overlay-titulo',
    'subtituloId' => 'tesoreria-crown-overlay-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Generando el archivo.',
])
<div class="row">
    <div class="col-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Bingo</h3>
                <div class="card-tools">
                    @include('includes.listado.filtros_toolbar', [
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => $tieneCriterios ?? false,
                        'limpiarUrl' => route('tesoreria_crown_bingo', $sala ? ['sala_id' => $sala->id] : []),
                        'placeholder' => 'Fecha o importe',
                        'toggleTarget' => '#panel-filtros-tesoreria-crown-bingo',
                        'formId' => 'form-filtros-tesoreria-crown-bingo',
                        'nuevoRegistroUrl' => $sala ? route('crear_tesoreria_crown_bingo', ['sala_id' => $sala->id]) : null,
                        'nuevoRegistroCan' => 'crear-tesoreria-crown-bingo',
                        'nuevoRegistroLabel' => 'Nuevo bingo',
                    ])
                </div>
            </div>
            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'tesoreria_crown_bingo',
                'exportRuta' => 'listar_tesoreria_crown_bingo',
                'exportQueryparams' => $filtrosQuery ?? [],
            ])
            <form id="form-filtros-tesoreria-crown-bingo" method="get" action="{{ route('tesoreria_crown_bingo') }}">
                <div id="panel-filtros-tesoreria-crown-bingo" class="collapse">
                <input type="hidden" name="sala_id" value="{{ $sala->id ?? '' }}">
                <input type="hidden" name="filtro_valor" id="filtro_valor" value="{{ $filtros['valor'] ?? '' }}">
                <div class="card-body border-bottom py-2">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-3 mb-2">
                            <label for="fecha_desde" class="small text-muted mb-0">Desde</label>
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control form-control-sm" value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <div class="form-group col-md-3 mb-2">
                            <label for="fecha_hasta" class="small text-muted mb-0">Hasta</label>
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control form-control-sm" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                        <div class="form-group col-md-3 mb-2">
                            <button type="submit" class="btn btn-sm btn-primary">Aplicar filtros</button>
                        </div>
                    </div>
                    <p class="text-muted small mb-0">Cartones − premios − RSE = resultado. La carga del día también se hace en la jornada (panel Bingo).</p>
                </div>
                </div>
            </form>
            <div class="card-body table-responsive p-0">
                <table id="tabla-paginada" class="table table-sm table-bordered table-hover mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Fecha</th>
                            <th class="text-right">Cartones</th>
                            <th class="text-right">Premios</th>
                            <th class="text-right">RSE</th>
                            <th class="text-right">Resultado</th>
                            <th class="text-left" style="width:90px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $fila)
                            <tr>
                                <td>
                                    @php
                                        $claveJ = (int) $fila->sala_id.'|'.$fila->fecha->format('Y-m-d');
                                        $jornadaId = $jornadasPorFecha[$claveJ] ?? null;
                                    @endphp
                                    @if ($jornadaId)
                                        <a class="text-primary" href="{{ route('ver_tesoreria_crown_jornada', $jornadaId) }}?panel=bingo" target="_blank" rel="noopener">
                                            {{ $fila->fecha?->format('d/m/Y') }}
                                        </a>
                                    @else
                                        {{ $fila->fecha?->format('d/m/Y') }}
                                    @endif
                                </td>
                                <td class="text-right">{{ number_format((float) $fila->tot_carton, 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) $fila->tot_premio, 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) $fila->tot_rse, 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format((float) $fila->resultado, 2, ',', '.') }}</td>
                                <td class="text-left text-nowrap">
                                    @if (can('editar-tesoreria-crown-bingo', false))
                                        <a href="{{ route('editar_tesoreria_crown_bingo', ['id' => $fila->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('eliminar-tesoreria-crown-bingo', false))
                                        <form action="{{ route('eliminar_tesoreria_crown_bingo', $fila->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar bingo del {{ $fila->fecha?->format('d/m/Y') }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-accion-tabla tooltipsC" title="Eliminar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">
                                    {{ ($tieneCriterios ?? false) ? 'No hay bingo para este filtro.' : 'Sin movimientos de bingo.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer clearfix">
                @if ($filas->total() > 0)
                    <span class="text-muted">{{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}</span>
                @endif
                {{ $filas->appends($filtrosQuery ?? [])->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
