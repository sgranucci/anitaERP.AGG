@extends("theme.$theme.layout")
@section('titulo')
    Canon municipal bingo
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Canon municipal bingo (4%)</h3>
                <div class="card-tools">
                    @if (can('listar-canon-municipal-config', false))
                        <a href="{{ route('canon_municipal_config') }}" class="btn btn-outline-secondary btn-sm" title="Configuración">
                            <i class="fa fa-cogs"></i> Configuración
                        </a>
                    @endif
                    <a href="{{ route('canon_municipal') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('canon_municipal') }}" id="form-canon-municipal" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Cruce diario Flash contable (Ventas Bingo) × Posición financiera (VENTA BINGO).
                        Canon = 4% sobre ventas del rango. La nota municipal solo se habilita si el cruce cuadra
                        (tolerancia {{ number_format(\App\Support\Contable\CanonMunicipal\CanonMunicipalCalendarioSupport::TOLERANCIA, 2, ',', '.') }}).
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
                                <p class="text-danger small mb-0">Sin empresas con configuración de canon municipal.</p>
                            @endif
                        </div>
                        <label class="{{ $colLabel }} requerido">Período</label>
                        <div class="{{ $colInput }}">
                            <input type="hidden" name="periodo" id="periodo" value="{{ $periodoYm }}">
                            <input type="hidden" name="periodicidad" id="periodicidad" value="{{ $filtros['periodicidad'] ?? 'semanal' }}">
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
                        <label for="liquidacion" class="{{ $colLabel }} requerido">Liquidación</label>
                        <div class="{{ $colInput }}">
                            <select name="liquidacion" id="liquidacion" class="form-control" required>
                                @foreach ($liquidaciones_enum as $value => $label)
                                    <option value="{{ $value }}" @selected((int) ($filtros['liquidacion'] ?? 1) === (int) $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <label for="fecha_desde" class="{{ $colLabel }}">Desde / Hasta</label>
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
                    <button type="submit" class="btn btn-primary" @disabled($empresasDisponibles->isEmpty())>
                        <i class="fa fa-search"></i> Consultar
                    </button>
                    @if ($consultado && ! empty($resultado['puede_emitir_nota']) && can('exportar-canon-municipal', false))
                        <a href="{{ route('exportar_canon_municipal_nota', $filtrosQuery) }}"
                           class="btn btn-success js-canon-municipal-dl"
                           id="btn-nota-canon"
                           target="canon_municipal_dl"
                           rel="noopener">
                            <i class="fa fa-file-pdf"></i> Descargar nota municipal
                        </a>
                    @elseif ($consultado && empty($resultado['puede_emitir_nota']) && empty($resultado['mensaje_config']))
                        <button type="button" class="btn btn-secondary" disabled title="El cruce no cuadra">
                            <i class="fa fa-ban"></i> Nota bloqueada
                        </button>
                    @endif
                </div>
            </form>
        </div>

        @include('includes.proceso_overlay_aviso', [
            'overlayId' => 'canon-municipal-procesando-overlay',
            'tituloId' => 'canon-municipal-procesando-titulo',
            'subtituloId' => 'canon-municipal-procesando-subtitulo',
            'titulo' => 'Consultando canon municipal…',
            'subtitulo' => 'Cruza Flash y Posición financiera. Puede demorar. No cierre la página.',
        ])

        {{-- Iframe oculto: la descarga PDF no navega ni deja el overlay pegado. --}}
        <iframe name="canon_municipal_dl" id="canon-municipal-dl-frame" title="Descarga PDF canon municipal"
            style="position:absolute;width:0;height:0;border:0;visibility:hidden;"></iframe>

        @if ($consultado && $resultado)
            @if (! empty($resultado['mensaje_config']))
                <div class="alert alert-warning">
                    {{ $resultado['mensaje_config'] }}
                </div>
            @else
                @if (can('exportar-canon-municipal', false))
                    <div class="mb-2">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_canon_municipal',
                            'queryparams' => $filtrosQuery,
                        ])
                    </div>
                @endif

                @include('contable.canon_municipal.partials.conciliacion', [
                    'resultado' => $resultado,
                    'periodo_texto' => $periodo_texto ?? '',
                ])
            @endif
        @endif
    </div>
</div>
@endsection

@section('scripts')
@php
    $mapaConfigJs = $mapa_config_empresas ?? [];
@endphp
<script>
window.CANON_MUNICIPAL = {
    mapaConfig: @json($mapaConfigJs),
    urlLiquidaciones: @json(route('canon_municipal_liquidaciones')),
    liquidacionActual: {{ (int) ($filtros['liquidacion'] ?? 1) }}
};
</script>
<script src="{{ asset('assets/pages/scripts/contable/canon_municipal/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/canon_municipal/form.js')) ?: time() }}" type="text/javascript"></script>
@endsection
