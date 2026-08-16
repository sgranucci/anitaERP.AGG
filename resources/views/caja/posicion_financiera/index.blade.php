@extends("theme.$theme.layout")
@section('titulo')
    Posición financiera
@endsection

@section('styles')
<style>
    .posfin-wrap { overflow-x: auto; }
    .posfin-tabla { font-size: 12px; white-space: nowrap; }
    .posfin-tabla th.posfin-concepto,
    .posfin-tabla td.posfin-concepto {
        position: sticky;
        left: 0;
        z-index: 2;
        min-width: 200px;
        background: #fff;
    }
    .posfin-tabla thead th.posfin-concepto {
        z-index: 3;
        background: #85C1E9;
    }
    .posfin-tabla th.posfin-dia,
    .posfin-tabla td.posfin-dia,
    .posfin-tabla th.posfin-total-col,
    .posfin-tabla td.posfin-total-col {
        min-width: 72px;
    }
    .posfin-tabla tr.posfin-titulo td {
        background: #D6EAF8;
        color: #1B4F72;
        font-weight: 600;
    }
    .posfin-tabla tr.posfin-total td.posfin-concepto {
        background: #f5f5f5;
    }
</style>
@endsection

@section('scripts')
<script>
(function () {
    var OVERLAY_ID = 'posfin-procesando-overlay';
    var TITULO_ID = 'posfin-procesando-titulo';
    var SUBTITULO_ID = 'posfin-procesando-subtitulo';

    function mostrarOverlay(titulo, subtitulo) {
        var overlay = document.getElementById(OVERLAY_ID);
        if (! overlay) {
            return;
        }
        var tituloEl = document.getElementById(TITULO_ID);
        var subtituloEl = document.getElementById(SUBTITULO_ID);
        if (tituloEl && titulo) {
            tituloEl.textContent = titulo;
        }
        if (subtituloEl && subtitulo) {
            subtituloEl.textContent = subtitulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }

    function ocultarOverlay() {
        var overlay = document.getElementById(OVERLAY_ID);
        if (! overlay) {
            return;
        }
        overlay.classList.add('d-none');
        overlay.style.display = '';
        overlay.setAttribute('aria-hidden', 'true');
    }

    var form = document.getElementById('form-posicion-financiera');
    if (form) {
        form.addEventListener('submit', function () {
            if (! form.checkValidity()) {
                return;
            }
            var btn = document.getElementById('btn-consultar');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando…';
            }
            mostrarOverlay('Calculando posición financiera…', 'Puede demorar según el período. No cierre la página.');
        });
    }

    document.querySelectorAll('a[href*="listar-posicion-financiera"]').forEach(function (link) {
        link.addEventListener('click', function () {
            mostrarOverlay('Generando exportación…', 'El PDF o Excel puede tardar según el volumen.');
        });
    });

    window.addEventListener('pageshow', ocultarOverlay);
})();
</script>
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'posfin-procesando-overlay',
    'tituloId' => 'posfin-procesando-titulo',
    'subtituloId' => 'posfin-procesando-subtitulo',
    'titulo' => 'Calculando…',
    'subtitulo' => 'Puede demorar según el período. No cierre la página.',
])
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Posición financiera</h3>
                <div class="card-tools">
                    <a href="{{ route('posicion_financiera') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('posicion_financiera') }}" id="form-posicion-financiera" class="mb-0">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Posición financiera del mes con una columna por día, cortada por unidad de negocio
                        (bingo, gastronomía, estacionamiento, vending y máquinas), más apertura de medios y egresos.
                    </p>

                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => $filtros['empresa_id'] ?? null,
                        'required' => true,
                        'col_label' => 'col-lg-2',
                        'col_input' => 'col-lg-4',
                    ])

                    <div class="form-group row mb-0">
                        <label class="col-lg-2 control-label text-right pr-2 requerido">Mes / Año</label>
                        <div class="col-lg-9">
                            <div class="row">
                                <div class="col-md-3">
                                    <select name="mes" class="form-control">
                                        @for ($m = 1; $m <= 12; $m++)
                                            <option value="{{ $m }}" @selected((int) ($filtros['mes'] ?? $mes_actual) === $m)>
                                                {{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="anio" class="form-control" min="2000" max="2100"
                                        value="{{ $filtros['anio'] ?? $anio_actual }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <input type="hidden" name="consultar" value="1">
                    <button type="submit" class="btn btn-primary" id="btn-consultar">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                </div>
            </form>
        </div>

        @if ($consultado)
            @if (! empty($errores_bridge))
                <div class="alert alert-warning">
                    <strong>Avisos del bridge Anita:</strong>
                    <ul class="mb-0">
                        @foreach ($errores_bridge as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title">
                        Resultado
                        @if (($periodo_texto ?? '') !== '')
                            <small class="text-muted">({{ $periodo_texto }}{{ $empresa ? ' — '.$empresa->nombre : '' }})</small>
                        @endif
                    </h3>
                    <div class="card-tools">
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_posicion_financiera',
                            'queryparams' => $filtrosQuery ?? [],
                        ])
                    </div>
                </div>
                <div class="card-body table-responsive p-0 posfin-wrap">
                    @include('caja.posicion_financiera.partials.tabla_datos', [
                        'filas' => $filas,
                        'dias' => $dias ?? [],
                        'modo' => 'pantalla',
                    ])
                </div>
                @if (count($filas) > 0)
                    <div class="card-footer clearfix small text-muted">
                        {{ count($filas) }} conceptos
                        @if ($saldo_inicial !== null)
                            · Saldo inicial {{ number_format((float) $saldo_inicial, 2, ',', '.') }}
                        @endif
                        @if ($saldo_final !== null)
                            · Saldo final {{ number_format((float) $saldo_final, 2, ',', '.') }}
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
