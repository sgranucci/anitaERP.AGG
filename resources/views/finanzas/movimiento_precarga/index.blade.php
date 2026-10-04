@extends("theme.$theme.layout")
@section('titulo', 'Precargas de cash flow')

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/listado-workbench.css') }}?v={{ filemtime(public_path('assets/css/listado-workbench.css')) }}">
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/listado/workbench-qbe-grupos.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-qbe-grupos.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/listado/workbench-orden.js') }}?v={{ filemtime(public_path('assets/pages/scripts/listado/workbench-orden.js')) }}"></script>
<script src="{{ asset('assets/pages/scripts/finanzas/movimiento_precarga/index.js') }}?v={{ filemtime(public_path('assets/pages/scripts/finanzas/movimiento_precarga/index.js')) }}"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'precarga-export-overlay',
    'tituloId' => 'precarga-export-titulo',
    'subtituloId' => 'precarga-export-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Generando el archivo. Pulse Esc para cerrar este aviso.',
])
<div class="card card-info">
    <div class="card-header">
        <h3 class="card-title">Precargas de cash flow</h3>
        <div class="card-tools d-flex flex-wrap align-items-center">
            @if (can('crear-finanza-movimiento-precarga', false))
                <a href="{{ route('crear_finanza_movimiento_precarga') }}" class="btn btn-sm btn-primary mr-2">
                    <i class="fa fa-plus"></i> Nueva precarga
                </a>
            @endif
            @include('includes.exportar-tabla-queryparams', [
                'ruta' => 'lista_finanza_movimiento_precarga',
                'queryparams' => $filtrosQuery ?? [],
                'variant' => 'compact',
            ])
        </div>
    </div>
    <div class="card-body">
        @include('includes.mensaje')
        <p class="text-muted mb-3">
            Movimientos previstos del día. Alimentan RRHH, SUSS, descubierto, transferencias y otras operaciones
            de la posición bancaria. No generan asiento hasta contabilizarlos.
        </p>
        <form method="get" action="{{ route('finanza_movimiento_precarga') }}" id="form-precarga-index">
            @include('finanzas.movimiento_precarga.partials.filtros_externos')
            @if ((int) ($filtros['empresa_id'] ?? 0) > 0)
                <input type="hidden" name="empresa_id" value="{{ (int) $filtros['empresa_id'] }}">
            @endif
            @if (($filtros['filtro_estado'] ?? 'todos') !== 'todos')
                <input type="hidden" name="filtro_estado" value="{{ $filtros['filtro_estado'] }}">
            @endif
            <div class="form-row align-items-end mb-2">
                <div class="form-group col-md-3">
                    <label for="fecha_desde">Fecha desde</label>
                    <input type="date" class="form-control" name="fecha_desde" id="fecha_desde" value="{{ $filtros['fecha_desde'] ?? '' }}">
                </div>
                <div class="form-group col-md-3">
                    <label for="fecha_hasta">Fecha hasta</label>
                    <input type="date" class="form-control" name="fecha_hasta" id="fecha_hasta" value="{{ $filtros['fecha_hasta'] ?? '' }}">
                </div>
                <div class="form-group col-md-6">
                    <button type="submit" class="btn btn-info">Consultar</button>
                    <button type="button" class="btn btn-outline-info collapsed" data-toggle="collapse" data-target="#lw-qbe-panel" aria-expanded="false" aria-controls="lw-qbe-panel">
                        <i class="fa fa-filter"></i> QBE
                    </button>
                    <a href="{{ route('finanza_movimiento_precarga') }}" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </div>
            @include('finanzas.movimiento_precarga.partials.qbe')
        </form>

        <div class="table-responsive mt-3">
            <table class="table table-sm table-bordered table-hover mb-0" id="tabla-paginada">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Empresa</th>
                        <th>Tipo</th>
                        <th>Rubro</th>
                        <th>Detalle</th>
                        <th>Cuenta</th>
                        <th class="text-right">Monto</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($datas as $row)
                        <tr>
                            <td>
                                <a class="text-primary" target="_blank" rel="noopener"
                                   href="{{ route('editar_finanza_movimiento_precarga', ['id' => $row->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">
                                    {{ $row->id }}
                                </a>
                            </td>
                            <td>{{ $row->fecha?->format('d/m/Y') }}</td>
                            <td>{{ $row->empresa->nombre ?? '' }}</td>
                            <td>{{ $row->etiquetaTipo() }}</td>
                            <td>{{ $row->etiquetaRubro() }}</td>
                            <td>{{ $row->detalle }}</td>
                            <td>{{ $row->etiquetaCuenta() }}</td>
                            <td class="text-right">{{ number_format((float) $row->monto, 2, ',', '.') }}</td>
                            <td>
                                @if ($row->estaCerrada())
                                    <span class="badge badge-success">Contabilizada</span>
                                @elseif ($row->movimientoRevertido())
                                    <span class="badge badge-warning">Reabierta</span>
                                @else
                                    <span class="badge badge-info">Abierta</span>
                                @endif
                            </td>
                            <td class="text-nowrap text-right">
                                @if (can('editar-finanza-movimiento-precarga', false))
                                    <a href="{{ route('editar_finanza_movimiento_precarga', $row->id) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('convertir-finanza-movimiento-precarga', false) && ! $row->estaCerrada())
                                    <a href="{{ route('contabilizar_finanza_movimiento_precarga', $row->id) }}" class="btn-accion-tabla tooltipsC" title="Contabilizar">
                                        <i class="fa fa-check text-success"></i>
                                    </a>
                                @endif
                                @if (can('borrar-finanza-movimiento-precarga', false) && ! $row->estaCerrada())
                                    <form action="{{ route('eliminar_finanza_movimiento_precarga', $row->id) }}" method="post" class="d-inline" onsubmit="return confirm('¿Eliminar la precarga?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-accion-tabla" title="Eliminar">
                                            <i class="fa fa-times-circle text-danger"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted">No hay precargas con estos filtros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-2 d-flex justify-content-between align-items-center">
            <small class="text-muted">
                @if ($datas->total() > 0)
                    {{ $datas->firstItem() }}–{{ $datas->lastItem() }} de {{ $datas->total() }}
                @endif
            </small>
            {{ $datas->links() }}
        </div>
    </div>
</div>
@endsection
