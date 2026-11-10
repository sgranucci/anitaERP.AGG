@extends("theme.$theme.layout")
@section('titulo')
    Flash
@endsection

@section('styles')
<style>
.fc-index .card-body { padding: .75rem 1rem; }
.fc-index .filtro-bar {
    display: flex; flex-wrap: wrap; align-items: end; gap: .6rem .9rem;
    background: #f8fbfc; border-bottom: 1px solid #d6eaf8;
    padding: .55rem .75rem;
}
.fc-index .filtro-bar label { display: block; margin: 0 0 .15rem; font-size: .72rem; color: #5d6d7e; text-transform: uppercase; }
.fc-index table { font-size: .85rem; }
.fc-index .kpi-row { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .65rem; }
.fc-index .kpi {
    background: #fff; border: 1px solid #d6eaf8; border-left: 3px solid #3498db;
    border-radius: .3rem; padding: .35rem .7rem; min-width: 7rem;
}
.fc-index .kpi .lbl { font-size: .68rem; color: #7f8c8d; text-transform: uppercase; display: block; }
.fc-index .kpi .val { font-weight: 600; color: #1a5276; }
</style>
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/listado.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/listado.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $cm = $consolidado['_metricas'] ?? [];
    $mostrarKpis = ($filtros['consultar'] ?? false) && $consolidado;
    $queryChips = array_filter([
        'fecha' => $filtros['fecha'] ?? null,
        'consultar' => 1,
    ], fn ($v) => $v !== null && $v !== '');
@endphp
@include('includes.mensaje')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'tesoreria-crown-overlay',
    'tituloId' => 'tesoreria-crown-overlay-titulo',
    'subtituloId' => 'tesoreria-crown-overlay-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Generando el archivo.',
])
<div class="row fc-index">
    <div class="col-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title mb-0">Flash diario</h3>
                <div class="card-tools">
                    @if (can('listar-flash-reporte-crown', false))
                        <a href="{{ route('flash_reporte_crown') }}" class="btn btn-outline-success btn-sm">
                            <i class="fa fa-envelope"></i> Informe flash
                        </a>
                    @endif
                </div>
            </div>

            <form method="get" action="{{ route('flash_crown') }}" class="filtro-bar mb-0" autocomplete="off">
                <input type="hidden" name="consultar" value="1">
                <input type="hidden" name="sala_id" value="{{ (int) ($filtros['sala_id'] ?? 0) }}">
                <div>
                    <label for="fecha">Fecha</label>
                    <input type="date" name="fecha" id="fecha" class="form-control form-control-sm" style="width:10.5rem;" value="{{ $filtros['fecha'] ?? '' }}" required>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary btn-sm">Consultar</button>
                </div>
            </form>

            @include('caja.tesoreria_crown.partials.sala_chips', [
                'ruta' => 'flash_crown',
                'salas' => $salas,
                'sala' => $sala,
                'mostrarTodas' => true,
                'etiquetaTodas' => 'Todas (consolidado)',
                'queryExtra' => $queryChips,
                'exportRuta' => ($filtros['consultar'] ?? false) && $filas->isNotEmpty() ? 'lista_flash_crown' : null,
                'exportQueryparams' => $filtrosQuery ?? [],
            ])

            <div class="card-body">
                @foreach ($avisos ?? [] as $aviso)
                    <div class="alert alert-warning py-1">{{ $aviso }}</div>
                @endforeach

                @if ($mostrarKpis)
                    <div class="kpi-row">
                        <div class="kpi">
                            <span class="lbl">Combined</span>
                            <span class="val">{{ number_format((float) ($cm['combined'] ?? 0), 2, ',', '.') }}</span>
                        </div>
                        <div class="kpi">
                            <span class="lbl">Gaming</span>
                            <span class="val">{{ number_format((float) ($cm['gaming'] ?? 0), 2, ',', '.') }}</span>
                        </div>
                        <div class="kpi">
                            <span class="lbl">Win mesas</span>
                            <span class="val">{{ number_format((float) ($cm['win_mesas'] ?? 0), 2, ',', '.') }}</span>
                        </div>
                        <div class="kpi">
                            <span class="lbl">Win slots</span>
                            <span class="val">{{ number_format((float) ($consolidado['slot_r'] ?? 0), 2, ',', '.') }}</span>
                        </div>
                        <div class="kpi">
                            <span class="lbl">Attendance</span>
                            <span class="val">{{ number_format((int) ($consolidado['att'] ?? 0), 0, ',', '.') }}</span>
                        </div>
                    </div>
                @endif

                @if (($filtros['consultar'] ?? false) && $filas->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover table-striped mb-0" id="tabla-paginada">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Sala</th>
                                    <th class="text-right">Att</th>
                                    <th class="text-right">Drop</th>
                                    <th class="text-right">Win mesas</th>
                                    <th class="text-right">Coin in</th>
                                    <th class="text-right">Win slots</th>
                                    <th class="text-right">OL Win</th>
                                    <th class="text-right">BS Win</th>
                                    <th class="text-right">Gaming</th>
                                    <th class="text-right">F&amp;B</th>
                                    <th class="text-right">Hotel</th>
                                    <th class="text-right">Combined</th>
                                    <th class="text-center">Val.</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($filas as $fila)
                                    @php
                                        $m = \App\Support\Caja\Flash\FlashCrownCalculoSupport::metricasDerivadas($fila->toArray());
                                    @endphp
                                    <tr>
                                        <td>{{ $fila->sala->codigo ?? '' }} {{ $fila->sala->nombre ?? '' }}</td>
                                        <td class="text-right">{{ number_format((int) $fila->att, 0, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $fila->cashdrop, 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $m['win_mesas'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $fila->coin_in, 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $fila->slot_r, 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($fila->ol_win ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($fila->bs_win ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $m['gaming'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $fila->ayb, 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) $fila->hotel, 2, ',', '.') }}</td>
                                        <td class="text-right"><strong>{{ number_format((float) $m['combined'], 2, ',', '.') }}</strong></td>
                                        <td class="text-center">
                                            @if ($fila->estaValidado())
                                                <i class="fa fa-check text-success" title="Validado"></i>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-nowrap">
                                            @if ($puedeEditar ?? false)
                                                <a href="{{ route('editar_flash_crown', $fila->id) }}" class="btn-accion-tabla tooltipsC" title="Editar / overrides">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                            @endif
                                            @if (($puedeValidar ?? false) && ! $fila->estaValidado())
                                                <form method="post" action="{{ route('validar_flash_crown', $fila->id) }}" class="d-inline" onsubmit="return confirm('¿Validar este flash?');">
                                                    @csrf
                                                    <button type="submit" class="btn-accion-tabla tooltipsC" title="Validar">
                                                        <i class="fa fa-check text-success"></i>
                                                    </button>
                                                </form>
                                            @elseif (($puedeValidar ?? false) && $fila->estaValidado())
                                                <form method="post" action="{{ route('quitar_validacion_flash_crown', $fila->id) }}" class="d-inline" onsubmit="return confirm('¿Quitar validación?');">
                                                    @csrf
                                                    <button type="submit" class="btn-accion-tabla tooltipsC" title="Quitar validación">
                                                        <i class="fa fa-undo text-warning"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                @if ($consolidado)
                                    <tr style="background:#D6EAF8;font-weight:600;">
                                        <td>CONSOLIDADO</td>
                                        <td class="text-right">{{ number_format((int) ($consolidado['att'] ?? 0), 0, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($consolidado['cashdrop'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($cm['win_mesas'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($consolidado['coin_in'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($consolidado['slot_r'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($consolidado['ol_win'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($consolidado['bs_win'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($cm['gaming'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($consolidado['ayb'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($consolidado['hotel'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float) ($cm['combined'] ?? 0), 2, ',', '.') }}</td>
                                        <td colspan="2"></td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Online desde Tesorería Crown + Resultado online</p>
                @elseif ($filtros['consultar'] ?? false)
                    <div class="alert alert-info mb-0">Sin datos para la fecha.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
