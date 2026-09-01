@extends("theme.$theme.layout")
@section('titulo')
    F2015 · Canon entidades
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">F2015 · Canon entidades de bien público</h3>
                <div class="card-tools">
                    <a href="{{ route('canon_entidades') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('canon_entidades') }}" id="form-canon-entidades" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Cálculo automático del canon (máquinas 1% + bingo según empresa) desde el Flash,
                        conciliado contra el pasivo <strong>215010-003</strong>
                        (Σ Haber de tipos MAQ + BIN del período). Tolerancia ≤ $&nbsp;1.
                    </p>

                    @php
                        $colLabel = 'col-lg-2 control-label text-right pr-2';
                        $colInput = 'col-lg-4';
                        $empresasDisponibles = collect($empresa_query ?? []);
                        $periodoYm = (string) ($filtros['periodo'] ?? date('Ym'));
                        if (strlen($periodoYm) !== 6) {
                            $periodoYm = date('Ym');
                        }
                        $periodoAnio = (int) substr($periodoYm, 0, 4);
                        $periodoMes = substr($periodoYm, 4, 2);
                        $mesesPeriodo = [
                            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
                            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
                            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
                        ];
                        $anioMinPeriodo = 2015;
                        $anioMaxPeriodo = (int) date('Y') + 1;
                    @endphp

                    <div class="form-group row">
                        <label for="empresa_id" class="{{ $colLabel }} requerido">Empresa</label>
                        <div class="{{ $colInput }}">
                            @if ($empresasDisponibles->count() > 1)
                                <select name="empresa_id" id="empresa_id" class="form-control" required>
                                    <option value="">Seleccione…</option>
                                    @foreach ($empresasDisponibles as $emp)
                                        <option value="{{ $emp->id }}" @selected((int) ($filtros['empresa_id'] ?? 0) === (int) $emp->id)>
                                            {{ $emp->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                            @elseif ($empresasDisponibles->count() === 1)
                                <input type="hidden" name="empresa_id" id="empresa_id" value="{{ (int) $empresasDisponibles->first()->id }}">
                                <span class="form-control-plaintext">{{ $empresasDisponibles->first()->nombre }}</span>
                            @else
                                <p class="text-danger small mb-0">Sin empresas asignadas.</p>
                            @endif
                        </div>
                        <label class="{{ $colLabel }} requerido">Período</label>
                        <div class="{{ $colInput }}">
                            <input type="hidden" name="periodo" id="periodo" value="{{ $periodoYm }}">
                            <div class="form-row">
                                <div class="col-7">
                                    <select name="periodo_mes_num" id="periodo_mes_num" class="form-control" required
                                        title="Mes del período" aria-label="Mes del período">
                                        @foreach ($mesesPeriodo as $num => $nombre)
                                            <option value="{{ $num }}" @selected($periodoMes === $num)>{{ $nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-5">
                                    <select name="periodo_anio" id="periodo_anio" class="form-control" required
                                        title="Año del período" aria-label="Año del período">
                                        @for ($y = $anioMaxPeriodo; $y >= $anioMinPeriodo; $y--)
                                            <option value="{{ $y }}" @selected($periodoAnio === $y)>{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row mb-0">
                        <label class="{{ $colLabel }}">Desde / Hasta</label>
                        <div class="{{ $colInput }}">
                            <div class="form-row">
                                <div class="col-6">
                                    <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" readonly
                                        value="{{ $filtros['fecha_desde'] ?? '' }}">
                                </div>
                                <div class="col-6">
                                    <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" readonly
                                        value="{{ $filtros['fecha_hasta'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <input type="hidden" name="consultar" value="1">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                    @if ($consultado && can('exportar-canon-entidades', false) && (($resultado['totales']['dias_rango'] ?? 0) > 0))
                        <a href="{{ route('exportar_canon_entidades_formulario', $filtrosQuery) }}"
                           class="btn btn-success"
                           target="canon_entidades_dl"
                           rel="noopener">
                            <i class="fa fa-file-pdf"></i> PDF para SP
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <iframe name="canon_entidades_dl" id="canon-entidades-dl-frame" title="Descarga PDF formulario"
            style="position:absolute;width:0;height:0;border:0;visibility:hidden;"></iframe>

        @include('includes.proceso_overlay_aviso', [
            'overlayId' => 'canon-entidades-procesando-overlay',
            'tituloId' => 'canon-entidades-procesando-titulo',
            'subtituloId' => 'canon-entidades-procesando-subtitulo',
            'titulo' => 'Calculando canon entidades…',
            'subtitulo' => 'Lee Flash y mayor 215010-003. Puede demorar. No cierre la página.',
        ])

        @if ($consultado && $resultado)
            @if (! empty($resultado['mensaje']))
                <div class="alert alert-warning">{{ $resultado['mensaje'] }}</div>
            @endif

            @if (can('exportar-canon-entidades', false) && (($resultado['totales']['dias_rango'] ?? 0) > 0))
                <div class="mb-2">
                    @include('includes.exportar-tabla-queryparams', [
                        'ruta' => 'listar_canon_entidades',
                        'queryparams' => $filtrosQuery,
                    ])
                </div>
            @endif

            @include('contable.canon_entidades.partials.conciliacion', [
                'resultado' => $resultado,
                'periodo_texto' => $periodo_texto ?? '',
            ])
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/contable/canon_entidades/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/canon_entidades/form.js')) ?: time() }}" type="text/javascript"></script>
@endsection
