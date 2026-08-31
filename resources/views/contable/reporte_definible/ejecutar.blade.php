@extends("theme.$theme.layout")
@section('titulo')
    Ejecutar reporte definible
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}">
<style>
.rd-exec-row-rubro td { border-top: 1px solid #d5d8dc; }
.rd-exec-row-rubro.negrita td { font-weight: 700; color: #1B4F72; }
.rd-exec-row-cuenta td { color: #566573; font-size: 12.5px; }
.rd-exec-indent { display: inline-block; }
.rd-exec-importe, .rd-exec-col-importe { font-variant-numeric: tabular-nums; white-space: nowrap; min-width: 7.5rem; }
.rd-exec-row-rubro .col-fija-1,
.rd-exec-row-rubro .col-fija-2,
.rd-exec-row-rubro .col-fija-der-1,
.rd-exec-row-rubro .col-fija-der-2 { background: #fff; }
.rd-exec-row-cuenta .col-fija-1,
.rd-exec-row-cuenta .col-fija-2,
.rd-exec-row-cuenta .col-fija-der-1,
.rd-exec-row-cuenta .col-fija-der-2 { background: #fff; }
</style>
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}"></script>
<script>
(function () {
    function toggleModo() {
        var rango = document.getElementById('modo_rango') && document.getElementById('modo_rango').checked;
        var pPer = document.getElementById('panel-periodos-rd');
        var pRan = document.getElementById('panel-rango-rd');
        if (pPer) pPer.style.display = rango ? 'none' : '';
        if (pRan) pRan.style.display = rango ? '' : 'none';
    }
    document.querySelectorAll('input[name="modo_periodo"]').forEach(function (el) {
        el.addEventListener('change', toggleModo);
    });
    toggleModo();

    function toggleLayout() {
        var layoutId = document.getElementById('rd_layout_id');
        var hasDesigned = layoutId && layoutId.value;
        var sel = document.getElementById('rd_columnas_layout');
        var layout = sel ? sel.value : 'periodos';
        var pPlan = document.getElementById('panel-plan-rd');
        var pCc = document.getElementById('panel-ccosto-cols-rd');
        var showPlan = false;
        if (hasDesigned) {
            var opt = layoutId.options[layoutId.selectedIndex];
            showPlan = opt && opt.getAttribute('data-usa-plan') === '1';
        } else {
            showPlan = layout === 'comparativo';
        }
        if (pPlan) pPlan.style.display = showPlan ? '' : 'none';
        if (pCc) pCc.style.display = (!hasDesigned && layout === 'ccosto') ? '' : 'none';
        toggleFuentePlan();
    }
    function toggleFuentePlan() {
        var fuente = document.getElementById('rd_fuente_plan');
        var panelEsc = document.getElementById('panel-escenario-rd');
        if (!panelEsc) return;
        var panelPlan = document.getElementById('panel-plan-rd');
        var planVisible = panelPlan && panelPlan.style.display !== 'none';
        var esPartida = !fuente || fuente.value === 'partidagasto';
        panelEsc.style.display = (planVisible && esPartida) ? '' : 'none';
    }
    var layoutSel = document.getElementById('rd_columnas_layout');
    if (layoutSel) {
        layoutSel.addEventListener('change', toggleLayout);
    }
    var layoutIdSel = document.getElementById('rd_layout_id');
    if (layoutIdSel) {
        layoutIdSel.addEventListener('change', toggleLayout);
    }
    var fuenteSel = document.getElementById('rd_fuente_plan');
    if (fuenteSel) {
        fuenteSel.addEventListener('change', toggleFuentePlan);
    }
    toggleLayout();

    var btnVar = document.getElementById('rd_btn_guardar_variante');
    if (btnVar) {
        btnVar.addEventListener('click', function () {
            var nombre = (document.getElementById('rd_variante_nombre') || {}).value || '';
            var formEl = document.getElementById('form-reporte-definible-ejecutar');
            if (!formEl || !nombre) { alert('Indique nombre de variante'); return; }
            var fd = new FormData(formEl);
            var filtros = {};
            fd.forEach(function (v, k) {
                if (k === 'consultar') return;
                if (k.slice(-2) === '[]') {
                    var kk = k.slice(0, -2);
                    if (!filtros[kk]) filtros[kk] = [];
                    filtros[kk].push(v);
                } else {
                    filtros[k] = v;
                }
            });
            fetch(btnVar.getAttribute('data-url'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': btnVar.getAttribute('data-csrf'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ nombre: nombre, filtros: filtros })
            }).then(function (r) { return r.json(); }).then(function (j) {
                if (j.ok) { alert('Variante guardada'); location.reload(); }
                else alert(j.message || 'Error');
            }).catch(function () { alert('Error al guardar variante'); });
        });
    }

    var selVar = document.getElementById('rd_variante_sel');
    if (selVar) {
        selVar.addEventListener('change', function () {
            var opt = selVar.options[selVar.selectedIndex];
            if (!opt || !opt.value) return;
            var raw = opt.getAttribute('data-filtros') || '{}';
            var filtros;
            try { filtros = JSON.parse(raw); } catch (e) { return; }
            var formEl = document.getElementById('form-reporte-definible-ejecutar');
            if (!formEl) return;
            Object.keys(filtros).forEach(function (k) {
                if (k === 'empresa_ids' && Array.isArray(filtros[k])) {
                    formEl.querySelectorAll('input[name="empresa_ids[]"]').forEach(function (cb) {
                        cb.checked = filtros[k].map(String).indexOf(String(cb.value)) >= 0;
                    });
                    return;
                }
                var el = formEl.querySelector('[name="' + k + '"]');
                if (!el) return;
                if (el.type === 'checkbox' || el.type === 'radio') {
                    if (el.type === 'checkbox') el.checked = !!filtros[k] && filtros[k] !== '0';
                    else if (String(el.value) === String(filtros[k])) el.checked = true;
                } else {
                    el.value = filtros[k];
                }
            });
            if (typeof toggleModo === 'function') toggleModo();
            if (typeof toggleLayout === 'function') toggleLayout();
        });
    }

    var form = document.getElementById('form-reporte-definible-ejecutar');
    function ocultarRdOverlay() {
        var overlay = document.getElementById('rd-exec-overlay');
        if (overlay) {
            overlay.classList.add('d-none');
            overlay.style.display = '';
            overlay.setAttribute('aria-hidden', 'true');
        }
    }
    function mostrarRdOverlay(titulo, subtitulo) {
        var overlay = document.getElementById('rd-exec-overlay');
        if (!overlay) {
            return;
        }
        var t = document.getElementById('rd-exec-titulo');
        var s = document.getElementById('rd-exec-subtitulo');
        if (titulo && t) {
            t.textContent = titulo;
        }
        if (subtitulo && s) {
            s.textContent = subtitulo;
        }
        overlay.classList.remove('d-none');
        overlay.style.display = 'flex';
        overlay.setAttribute('aria-hidden', 'false');
    }
    if (form) {
        form.addEventListener('submit', function () {
            if (!form.checkValidity()) return;
            mostrarRdOverlay(
                'Calculando informe…',
                'Puede demorar según el período y la cantidad de cuentas. No cierre la página.'
            );
        });
    }
    document.querySelectorAll('a[href*="listar-reporte-definible"]').forEach(function (a) {
        a.addEventListener('click', function () {
            mostrarRdOverlay(
                'Exportando…',
                'El archivo se descarga al terminar. Pulse Esc para cerrar este aviso.'
            );
            window.addEventListener('focus', ocultarRdOverlay, { once: true });
        });
    });
    window.addEventListener('pageshow', ocultarRdOverlay);
    window.addEventListener('pagehide', ocultarRdOverlay);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' || event.keyCode === 27) {
            ocultarRdOverlay();
        }
    });
})();
</script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'rd-exec-overlay',
    'tituloId' => 'rd-exec-titulo',
    'subtituloId' => 'rd-exec-subtitulo',
    'titulo' => 'Calculando informe…',
    'subtitulo' => 'Puede demorar según el período y la cantidad de cuentas. No cierre la página.',
])

<div class="row">
    <div class="col-lg-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-play"></i> Ejecutar reporte definible
                </h3>
                <div class="card-tools">
                    <a href="{{ route('reporte_definible') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Catálogo
                    </a>
                    @if ($puede_editar && $reporte)
                        <a href="{{ route('editar_reporte_definible', ['id' => $reporte->id]) }}" class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-edit"></i> Diseñar
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @include('includes.mensaje')

                <div class="alert alert-light border">
                    Períodos usan saldos mensuales (rápido). <strong>Entre fechas</strong> y filtros de
                    <strong>c.costo</strong> leen asientos. Layout <strong>Actual / Plan / Var / %</strong>
                    separa cuentas reales vs presupuesto del diseño. El ícono Mayor abre el mayor plano.
                </div>

                <form method="get" action="{{ route('ejecutar_reporte_definible', ['id' => $reporte->id ?? null]) }}"
                      id="form-reporte-definible-ejecutar" class="mb-4">
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Informe</label>
                            <select name="reporte_contable_id" id="reporte_contable_id" class="form-control" required
                                    onchange="var id=this.value; if(id){ window.location='{{ url(config('app.app_carpeta').'/contable/reporte-definible/ejecutar') }}/'+id; }">
                                <option value="">Seleccione…</option>
                                @foreach ($reportes as $r)
                                    <option value="{{ $r->id }}" @if ($reporte && (int)$reporte->id === (int)$r->id) selected @endif>
                                        {{ $r->codigo }} — {{ $r->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @if ($reporte)
                        <div class="form-group col-md-3">
                            <label>Variante</label>
                            <select id="rd_variante_sel" class="form-control">
                                <option value="">— Cargar —</option>
                                @foreach ($variantes ?? [] as $v)
                                    <option value="{{ $v['id'] }}" data-filtros='@json($v['filtros'] ?? [])'>{{ $v['nombre'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label>Guardar como</label>
                            <div class="input-group">
                                <input type="text" id="rd_variante_nombre" class="form-control" maxlength="80" placeholder="Nombre">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary" id="rd_btn_guardar_variante"
                                            data-url="{{ route('guardar_variante_reporte_definible', $reporte->id) }}"
                                            data-csrf="{{ csrf_token() }}">OK</button>
                                </div>
                            </div>
                        </div>
                        @endif
                        <div class="form-group col-md-4">
                            <label>Apertura</label>
                            <div class="mt-1">
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="modo_periodos" name="modo_periodo" value="periodos" class="custom-control-input"
                                           @if (($filtros['modo_periodo'] ?? 'periodos') !== 'rango') checked @endif>
                                    <label class="custom-control-label" for="modo_periodos">Por períodos</label>
                                </div>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="modo_rango" name="modo_periodo" value="rango" class="custom-control-input"
                                           @if (($filtros['modo_periodo'] ?? '') === 'rango') checked @endif>
                                    <label class="custom-control-label" for="modo_rango">Entre fechas (asientos)</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Layout de columnas</label>
                            <select name="layout_id" id="rd_layout_id" class="form-control">
                                <option value="">— Legacy (abajo) —</option>
                                @foreach ($layouts_disponibles ?? [] as $lay)
                                    @php
                                        $usaPlanLay = app(\App\Support\Contable\ReporteDefinible\ReporteDefinibleLayoutResolver::class)->layoutUsaPlan($lay);
                                    @endphp
                                    <option value="{{ $lay->id }}"
                                        data-usa-plan="{{ $usaPlanLay ? '1' : '0' }}"
                                        @if ((int)($filtros['layout_id'] ?? 0) === (int)$lay->id) selected @endif>
                                        {{ $lay->reporte_contable_id ? 'Informe · ' : 'Sistema · ' }}{{ $lay->codigo }} — {{ $lay->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Columnas (legacy si no hay layout)</label>
                            <select name="columnas_layout" id="rd_columnas_layout" class="form-control">
                                @foreach (\App\Support\Contable\ReporteDefinible\ReporteDefinibleSupport::layoutsColumnas() as $lk => $ll)
                                    <option value="{{ $lk }}" @if (($filtros['columnas_layout'] ?? 'periodos') === $lk) selected @endif>{{ $ll }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>TC consolidación (FX)</label>
                            <select name="tipo_cambio_consolidacion" class="form-control">
                                <option value="asiento" @if (($filtros['tipo_cambio_consolidacion'] ?? 'asiento') === 'asiento') selected @endif>
                                    Cotización del asiento
                                </option>
                                <option value="cierre" @if (($filtros['tipo_cambio_consolidacion'] ?? '') === 'cierre') selected @endif>
                                    Cotización de cierre (fecha hasta)
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row" id="panel-plan-rd" style="@if (($filtros['columnas_layout'] ?? '') !== 'comparativo') display:none @endif">
                        <div class="form-group col-md-4">
                            <label>Fuente del Plan</label>
                            <select name="fuente_plan" id="rd_fuente_plan" class="form-control">
                                @foreach (\App\Support\Contable\ReporteDefinible\ReporteDefinibleDimensionSupport::fuentesPlan() as $fk => $fl)
                                    <option value="{{ $fk }}" @if (($filtros['fuente_plan'] ?? 'partidagasto') === $fk) selected @endif>{{ $fl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4" id="panel-escenario-rd">
                            <label>Escenario presupuesto</label>
                            <select name="presupuesto_escenario_id" class="form-control">
                                <option value="">Automático (año del período)</option>
                                @foreach ($escenarios_presupuesto ?? [] as $esc)
                                    <option value="{{ $esc['id'] }}"
                                        @if ((int)($filtros['presupuesto_escenario_id'] ?? 0) === (int)$esc['id']) selected @endif>
                                        {{ $esc['anio'] }} — {{ $esc['nombre'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label class="d-block text-muted small mt-4">
                                Plan = suma de <strong>partidagasto_monto</strong> por cuenta y c.costo (estado ACTIVA), mismo filtro de c.costo que Actual.
                            </label>
                        </div>
                    </div>

                    <div class="form-row" id="panel-ccosto-cols-rd" style="@if (($filtros['columnas_layout'] ?? '') !== 'ccosto') display:none @endif">
                        <div class="form-group col-md-3">
                            <label class="d-block">&nbsp;</label>
                            <input type="hidden" name="incluir_sin_ccosto" value="0">
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input" id="incluir_sin_ccosto" name="incluir_sin_ccosto" value="1"
                                       @if (!array_key_exists('incluir_sin_ccosto', $filtros) || !empty($filtros['incluir_sin_ccosto'])) checked @endif>
                                <label class="custom-control-label" for="incluir_sin_ccosto">Columna Sin c.costo</label>
                            </div>
                        </div>
                        <div class="form-group col-md-3">
                            <label class="d-block">&nbsp;</label>
                            <input type="hidden" name="incluir_total_ccosto" value="0">
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input" id="incluir_total_ccosto" name="incluir_total_ccosto" value="1"
                                       @if (!array_key_exists('incluir_total_ccosto', $filtros) || !empty($filtros['incluir_total_ccosto'])) checked @endif>
                                <label class="custom-control-label" for="incluir_total_ccosto">Columna Total</label>
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <p class="text-muted small mt-4 mb-0">
                                Un período/rango; una columna por c.costo con movimiento (máx. 30). Usá c.costo desde/hasta para acotar.
                            </p>
                        </div>
                    </div>

                    <div class="form-row" id="panel-periodos-rd">
                        <div class="form-group col-md-2">
                            <label>Mes desde</label>
                            <select name="mes_desde" class="form-control">
                                @for ($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" @if ((int)($filtros['mes_desde'] ?? $mes_actual) === $m) selected @endif>{{ sprintf('%02d', $m) }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label>Año desde</label>
                            <input type="number" name="anio_desde" class="form-control" min="2000" max="2100"
                                   value="{{ $filtros['anio_desde'] ?? $anio_actual }}">
                        </div>
                        <div class="form-group col-md-2">
                            <label>Mes hasta</label>
                            <select name="mes_hasta" class="form-control">
                                @for ($m = 1; $m <= 12; $m++)
                                    <option value="{{ $m }}" @if ((int)($filtros['mes_hasta'] ?? $mes_actual) === $m) selected @endif>{{ sprintf('%02d', $m) }}</option>
                                @endfor
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label>Año hasta</label>
                            <input type="number" name="anio_hasta" class="form-control" min="2000" max="2100"
                                   value="{{ $filtros['anio_hasta'] ?? $anio_actual }}">
                        </div>
                    </div>

                    <div class="form-row" id="panel-rango-rd" style="display:none">
                        <div class="form-group col-md-3">
                            <label>Fecha desde</label>
                            <input type="date" name="fecha_desde" class="form-control"
                                   value="{{ $filtros['fecha_desde'] ?? '' }}">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Fecha hasta</label>
                            <input type="date" name="fecha_hasta" class="form-control"
                                   value="{{ $filtros['fecha_hasta'] ?? '' }}">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-2">
                            <label>Base del saldo</label>
                            <select name="base_saldo" class="form-control">
                                <option value="periodo" @if (($filtros['base_saldo'] ?? '') === 'periodo') selected @endif>Movimiento del período</option>
                                <option value="ejercicio" @if (($filtros['base_saldo'] ?? '') === 'ejercicio') selected @endif>Saldo del ejercicio</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label>Asientos</label>
                            <select name="modo_inclusion_asientos" class="form-control">
                                <option value="sin_cierre_ni_inflacion" @if (($filtros['modo_inclusion_asientos'] ?? '') === 'sin_cierre_ni_inflacion') selected @endif>Sin cierre ni inflación</option>
                                <option value="sin_cierre" @if (($filtros['modo_inclusion_asientos'] ?? '') === 'sin_cierre') selected @endif>Sin cierre</option>
                                <option value="sin_inflacion" @if (($filtros['modo_inclusion_asientos'] ?? '') === 'sin_inflacion') selected @endif>Sin inflación</option>
                                <option value="todos" @if (($filtros['modo_inclusion_asientos'] ?? '') === 'todos') selected @endif>Todos</option>
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label>C.costo desde</label>
                            <input type="number" name="ccosto_desde" class="form-control" min="0"
                                   value="{{ ($filtros['ccosto_desde'] ?? 0) ?: '' }}" placeholder="Opcional">
                        </div>
                        <div class="form-group col-md-2">
                            <label>C.costo hasta</label>
                            <input type="number" name="ccosto_hasta" class="form-control" min="0"
                                   value="{{ ($filtros['ccosto_hasta'] ?? 0) ?: '' }}" placeholder="Opcional">
                        </div>
                        <div class="form-group col-md-1">
                            <label>Nivel</label>
                            <input type="number" name="nivel_max" class="form-control" min="0" max="20"
                                   value="{{ $filtros['nivel_max'] ?? 0 }}" title="0 = todos">
                        </div>
                        <div class="form-group col-md-1">
                            <label class="d-block">&nbsp;</label>
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input" id="mostrar_cuentas" name="mostrar_cuentas" value="1"
                                       @if (!empty($filtros['mostrar_cuentas'])) checked @endif>
                                <label class="custom-control-label" for="mostrar_cuentas">Ctas</label>
                            </div>
                        </div>
                        <div class="form-group col-md-1">
                            <label class="d-block">&nbsp;</label>
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input" id="ocultar_ceros" name="ocultar_ceros" value="1"
                                       @if (!empty($filtros['ocultar_ceros'])) checked @endif>
                                <label class="custom-control-label" for="ocultar_ceros">Sin ceros</label>
                            </div>
                        </div>
                        <div class="form-group col-md-2">
                            <label class="d-block">&nbsp;</label>
                            <button type="submit" class="btn btn-info btn-block" id="btn-consultar" @if (!$reporte) disabled @endif>
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids' => $filtros['empresa_ids'] ?? [],
                        'consolidar_empresas' => $filtros['consolidar_empresas'] ?? true,
                        'id_prefix' => 'rd_exec',
                    ])
                </form>

                @if ($consultado && $resultado)
                    @if (!empty($resultado['advertencias']))
                        <div class="alert alert-warning">
                            <ul class="mb-0">
                                @foreach ($resultado['advertencias'] as $adv)
                                    <li>{{ $adv }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @php
                        $rdExportQs = http_build_query(array_filter($filtrosQuery ?? [], fn ($v) => $v !== null && $v !== ''));
                        $rdExportSuffix = $rdExportQs !== '' ? '?'.$rdExportQs : '';
                    @endphp

                    @if ($reporte)
                        @php
                            $rdUrlParidad = \App\Support\Navegacion\ModoConsultaUrlSupport::appendQueryToUrl(
                                route('paridad_anita_reporte_definible', ['id' => $reporte->id]).$rdExportSuffix
                            );
                            $rdUrlPublicaciones = \App\Support\Navegacion\ModoConsultaUrlSupport::appendQueryToUrl(
                                route('publicaciones_reporte_definible', ['id' => $reporte->id])
                            );
                        @endphp
                        <div class="d-flex flex-wrap align-items-start justify-content-between mb-2">
                            <div class="mb-2 mr-3">
                                <h4 class="mb-0">{{ $reporte->titulo1 ?: $reporte->nombre }}</h4>
                                @if ($reporte->titulo2)
                                    <div class="text-muted">{{ $reporte->titulo2 }}</div>
                                @endif
                                <div class="small text-muted">Período {{ $periodo_texto }} · Fuente {{ $resultado['fuente'] ?? '' }}</div>
                            </div>
                            <div class="d-flex flex-wrap align-items-start">
                                <a href="{{ route('listar_reporte_definible', ['id' => $reporte->id, 'formato' => 'PDF']).$rdExportSuffix }}" class="btn btn-app bg-danger">
                                    <i class="fas fa-file-pdf"></i> Pdf
                                </a>
                                <a href="{{ route('listar_reporte_definible', ['id' => $reporte->id, 'formato' => 'EXCEL']).$rdExportSuffix }}" class="btn btn-app bg-success">
                                    <i class="fas fa-file-excel"></i> Excel
                                </a>
                                <a href="{{ route('listar_reporte_definible', ['id' => $reporte->id, 'formato' => 'CSV']).$rdExportSuffix }}" class="btn btn-app bg-warning">
                                    <i class="fas fa-file-csv"></i> Csv
                                </a>
                                <a href="{{ $rdUrlParidad }}"
                                   class="btn btn-app bg-info" target="_blank" rel="noopener"
                                   title="Comparar este informe contra ctamov + subdiario de Anita">
                                    <i class="fas fa-balance-scale"></i> Paridad Anita
                                </a>
                                @if (can('ejecutar-reporte-definible', false))
                                    <a href="#" class="btn btn-app bg-primary" data-toggle="modal" data-target="#rd-modal-publicar"
                                       title="Congelar estos números para reimprimirlos idénticos">
                                        <i class="fas fa-stamp"></i> Publicar
                                    </a>
                                @endif
                                <a href="{{ $rdUrlPublicaciones }}"
                                   class="btn btn-app bg-secondary" title="Resultados ya publicados de este informe">
                                    <i class="fas fa-archive"></i> Publicados
                                </a>
                            </div>
                        </div>
                    @endif

                    @if (!empty($publicado_aviso))
                        <div class="alert {{ $publicado_aviso['coincide'] ? 'alert-success' : 'alert-danger' }} py-2">
                            <i class="fa {{ $publicado_aviso['coincide'] ? 'fa-check-circle' : 'fa-exclamation-triangle' }}"></i>
                            {{ $publicado_aviso['mensaje'] }}
                            <a href="{{ \App\Support\Navegacion\ModoConsultaUrlSupport::route('ver_publicacion_reporte_definible', ['id' => $reporte->id, 'publicacionId' => $publicado_aviso['publicacion']->id]) }}"
                               class="text-primary" target="_blank" rel="noopener">Ver lo publicado</a>
                        </div>
                    @endif

                    @include('contable.reporte_definible.partials.tabla_resultado', [
                        'resultado' => $resultado,
                        'puede_drill' => true,
                        'drill_url' => route('drill_reporte_definible', ['id' => $reporte->id]).$rdExportSuffix,
                    ])

                    @include('contable.reporte_definible.partials.notas_pie', [
                        'notas' => $resultado['notas'] ?? [],
                        'notas_url_admin' => ($reporte && can('editar-reporte-definible', false))
                            ? \App\Support\Navegacion\ModoConsultaUrlSupport::appendQueryToUrl(
                                route('editar_reporte_definible', ['id' => $reporte->id]).'#tab-notas'
                            )
                            : null,
                    ])
                @endif
            </div>
        </div>
    </div>
</div>

@if (($reporte ?? null) && ($resultado ?? null))
    @include('contable.reporte_definible.partials.modal_drill', [
        'drill_url' => route('drill_reporte_definible', ['id' => $reporte->id]).$rdExportSuffix,
    ])

    @if (can('ejecutar-reporte-definible', false))
        <div class="modal fade" id="rd-modal-publicar" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <form method="POST" action="{{ route('publicar_resultado_reporte_definible', ['id' => $reporte->id]) }}">
                    @csrf
                    @foreach (array_filter($filtrosQuery ?? [], fn ($v) => $v !== null && $v !== '') as $clave => $valor)
                        @if (is_array($valor))
                            @foreach ($valor as $v)
                                <input type="hidden" name="{{ $clave }}[]" value="{{ $v }}">
                            @endforeach
                        @else
                            <input type="hidden" name="{{ $clave }}" value="{{ $valor }}">
                        @endif
                    @endforeach
                    <div class="modal-content">
                        <div class="modal-header bg-primary">
                            <h5 class="modal-title">Publicar este resultado</h5>
                            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">
                                Se guardan los números tal como están en pantalla, con los filtros usados y una huella
                                del contenido. Después se reimprimen sin recalcular, incluso si cambia la definición.
                            </p>
                            <div class="form-group">
                                <label class="small mb-0">Nombre</label>
                                <input type="text" name="nombre" class="form-control form-control-sm"
                                       value="{{ trim(($reporte->titulo1 ?: $reporte->nombre).' '.$periodo_texto) }}" maxlength="160">
                            </div>
                            <div class="form-group mb-0">
                                <label class="small mb-0">Observación (opcional)</label>
                                <textarea name="observacion" class="form-control form-control-sm" rows="2"
                                          placeholder="Ej. presentado a directorio / cierre noviembre"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-stamp"></i> Publicar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endif
@endsection
