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
.rd-exec-row-subtitulo td,
.rd-exec-row-subtitulo .col-fija-1,
.rd-exec-row-subtitulo .col-fija-2 { font-weight: 700; font-size: 14px; color: #1B4F72; background: #EAF2F8; border-top: 2px solid #85C1E9; }
.rd-exec-row-subtotal td,
.rd-exec-row-subtotal .col-fija-1,
.rd-exec-row-subtotal .col-fija-2 { font-weight: 700; background: #F4F6F7; border-top: 2px solid #85929E; }
.rd-exec-row-beneficio td,
.rd-exec-row-beneficio .col-fija-1,
.rd-exec-row-beneficio .col-fija-2 { font-weight: 700; background: #D5F5E3; border-top: 3px double #196F3D; color: #145A32; }
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
<script src="{{ asset('assets/pages/scripts/contable/reporte_definible/grupo_ccosto.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/reporte_definible/grupo_ccosto.js')) }}"></script>
<script>
(function () {
    function toggleModo() {
        var rango = document.getElementById('modo_rango') && document.getElementById('modo_rango').checked;
        var meses = document.getElementById('modo_meses') && document.getElementById('modo_meses').checked;
        var pPer = document.getElementById('panel-periodos-rd');
        var pRan = document.getElementById('panel-rango-rd');
        var pMes = document.getElementById('panel-meses-rd');
        if (pPer) pPer.style.display = (rango || meses) ? 'none' : '';
        if (pRan) pRan.style.display = rango ? '' : 'none';
        if (pMes) pMes.style.display = meses ? '' : 'none';
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
        if (layout === 'comparativo_meses' && layoutId && layoutId.value) {
            layoutId.value = '';
            hasDesigned = false;
        }
        var pPlan = document.getElementById('panel-plan-rd');
        var pCc = document.getElementById('panel-ccosto-cols-rd');
        var showPlan = false;
        if (hasDesigned) {
            var opt = layoutId.options[layoutId.selectedIndex];
            showPlan = opt && opt.getAttribute('data-usa-plan') === '1';
        } else {
            showPlan = layout === 'comparativo' || layout === 'comparativo_meses';
        }
        if (pPlan) pPlan.style.display = showPlan ? '' : 'none';
        if (pCc) pCc.style.display = (!hasDesigned && layout === 'ccosto') ? '' : 'none';
        var pMesesLay = document.getElementById('ayuda-comparativo-meses-rd');
        if (pMesesLay) pMesesLay.style.display = (!hasDesigned && layout === 'comparativo_meses') ? '' : 'none';
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

    var listaMeses = document.getElementById('rd-meses-lista');
    var btnAgregarMes = document.getElementById('rd-agregar-mes');
    function filaMes(valor) {
        var row = document.createElement('div');
        row.className = 'input-group mb-1 rd-mes-item';
        row.innerHTML = '<input type="month" name="periodos[]" class="form-control" min="2000-01" max="2100-12" value="' + (valor || '') + '">'
            + '<div class="input-group-append"><button type="button" class="btn btn-outline-secondary rd-quitar-mes" title="Quitar mes">&times;</button></div>';
        return row;
    }
    if (btnAgregarMes && listaMeses) {
        btnAgregarMes.addEventListener('click', function () {
            if (listaMeses.querySelectorAll('.rd-mes-item').length >= 12) {
                return;
            }
            listaMeses.appendChild(filaMes(''));
        });
        listaMeses.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.rd-quitar-mes');
            if (!btn) return;
            var items = listaMeses.querySelectorAll('.rd-mes-item');
            if (items.length <= 1) {
                var input = items[0].querySelector('input');
                if (input) input.value = '';
                return;
            }
            btn.closest('.rd-mes-item').remove();
        });
    }

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
                if (k === 'modo_periodo') {
                    formEl.querySelectorAll('input[name="modo_periodo"]').forEach(function (r) {
                        r.checked = String(r.value) === String(filtros[k]);
                    });
                    return;
                }
                if (k === 'periodos' && Array.isArray(filtros[k]) && listaMeses) {
                    listaMeses.innerHTML = '';
                    var lista = filtros[k].length ? filtros[k] : [''];
                    lista.forEach(function (p) {
                        var s = String(p).replace(/\D/g, '');
                        var valor = s.length >= 6 ? s.slice(0, 4) + '-' + s.slice(4, 6) : '';
                        listaMeses.appendChild(filaMes(valor));
                    });
                    return;
                }
                if (k === 'grupo_ccosto' && Array.isArray(filtros[k]) && typeof window.rdCargarGruposCcosto === 'function') {
                    window.rdCargarGruposCcosto(filtros[k]);
                    return;
                }
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
                    compara contra el presupuesto: por defecto la empresa de código + 10 (Anita). El ícono Mayor abre el mayor plano.
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
                                           @if (!in_array($filtros['modo_periodo'] ?? 'periodos', ['rango', 'meses'], true)) checked @endif>
                                    <label class="custom-control-label" for="modo_periodos">Rango de meses</label>
                                </div>
                                <div class="custom-control custom-radio custom-control-inline">
                                    <input type="radio" id="modo_meses" name="modo_periodo" value="meses" class="custom-control-input"
                                           @if (($filtros['modo_periodo'] ?? '') === 'meses') checked @endif>
                                    <label class="custom-control-label" for="modo_meses">Meses en particular</label>
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
                            <p class="text-muted small mb-0 mt-1" id="ayuda-comparativo-meses-rd" style="display:none">
                                Cada mes sale en su bloque: año anterior, mes anterior, el mes, presupuesto y las diferencias contra presupuesto, contra el año anterior y contra el mes anterior. Si pedís septiembre, el mes anterior es agosto.
                                Con rango de meses abre todos los del desde/hasta. Con meses en particular, solo los que cargues (aunque no sean seguidos).
                                Dejá «Layout de columnas» en Legacy para usar esta opción.
                            </p>
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

                    <div class="form-row" id="panel-plan-rd" style="@if (!in_array($filtros['columnas_layout'] ?? '', ['comparativo', 'comparativo_meses'], true)) display:none @endif">
                        <div class="form-group col-md-4">
                            <label>Fuente del Plan</label>
                            <select name="fuente_plan" id="rd_fuente_plan" class="form-control">
                                @foreach (\App\Support\Contable\ReporteDefinible\ReporteDefinibleDimensionSupport::fuentesPlan() as $fk => $fl)
                                    <option value="{{ $fk }}" @if (($filtros['fuente_plan'] ?? 'empresa_presupuesto') === $fk) selected @endif>{{ $fl }}</option>
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
                                Empresa presupuesto lee los asientos de la empresa código + 10, con el mismo filtro de c.costo que Actual.
                                Partidas de gasto usa el módulo de presupuesto. Origen P solo suma las cuentas marcadas así en el informe.
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

                    <div class="form-row" id="panel-meses-rd" style="display:none">
                        <div class="form-group col-md-4">
                            <label>Meses</label>
                            <div id="rd-meses-lista">
                                @php
                                    $periodosUi = $filtros['periodos'] ?? [];
                                    if ($periodosUi === []) {
                                        $periodosUi = [sprintf('%04d-%02d', (int) $anio_actual, (int) $mes_actual)];
                                    }
                                @endphp
                                @foreach ($periodosUi as $periodoUi)
                                    @php
                                        $digitosPeriodo = preg_replace('/\D/', '', (string) $periodoUi) ?? '';
                                        $valorMes = strlen($digitosPeriodo) >= 6
                                            ? substr($digitosPeriodo, 0, 4).'-'.substr($digitosPeriodo, 4, 2)
                                            : '';
                                    @endphp
                                    <div class="input-group mb-1 rd-mes-item">
                                        <input type="month" name="periodos[]" class="form-control" min="2000-01" max="2100-12" value="{{ $valorMes }}">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary rd-quitar-mes" title="Quitar mes">&times;</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="btn btn-link btn-sm px-0" id="rd-agregar-mes">Agregar mes</button>
                            <p class="text-muted small mb-0">Hasta 12. Podés mezclar meses que no sean consecutivos, por ejemplo agosto y septiembre.</p>
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
                        <div class="form-group col-md-4" id="rd-grupo-ccosto">
                            <label>Grupo de centros de costo</label>
                            <div class="input-group input-group-sm mb-1">
                                <input type="text" id="rd_grupo_ccosto_codigo" class="form-control" placeholder="C&oacute;digo" autocomplete="off" style="max-width: 6rem;">
                                <input type="text" id="rd_grupo_ccosto_nombre" class="form-control" placeholder="Descripci&oacute;n" readonly>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary" id="rd-grupo-ccosto-lupa" title="Consultar grupos (F1)">
                                        <i class="fa fa-search"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" id="rd-grupo-ccosto-agregar">Agregar</button>
                                </div>
                            </div>
                            <table class="table table-sm table-bordered mb-0">
                                <tbody id="rd-grupo-ccosto-lista">
                                    @foreach ($grupos_ccosto ?? [] as $grupoCc)
                                        <tr data-codigo="{{ $grupoCc['codigo'] }}">
                                            <td style="width:5rem">{{ $grupoCc['codigo'] }}</td>
                                            <td>{{ $grupoCc['nombre'] }}<input type="hidden" name="grupo_ccosto[]" value="{{ $grupoCc['codigo'] }}"></td>
                                            <td class="text-center" style="width:3rem">
                                                <button type="button" class="btn btn-outline-danger btn-xs rd-grupo-ccosto-quitar" title="Quitar">&times;</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <small class="text-muted">Vac&iacute;o = todos los centros. Pod&eacute;s sumar m&aacute;s de un grupo, como en Anita.</small>
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
                                @php
                                    $logosVista = \App\Support\Configuracion\EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
                                        \App\Services\Contable\ReporteDefinibleParidadService::coleccionEmpresasParaLogos((array) ($filtros['empresa_ids'] ?? []))
                                    );
                                @endphp
                                @if (count($logosVista) > 0)
                                    <div class="d-flex flex-wrap align-items-center mb-2">
                                        @foreach ($logosVista as $logo)
                                            <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" class="mr-2" style="max-height: 48px; max-width: 140px;">
                                        @endforeach
                                    </div>
                                @endif
                                <h4 class="mb-0">{{ $reporte->titulo1 ?: $reporte->nombre }}</h4>
                                @if ($reporte->titulo2)
                                    <div class="text-muted">{{ $reporte->titulo2 }}</div>
                                @endif
                                <div class="small text-muted">Período {{ $periodo_texto }} · Fuente {{ $resultado['fuente'] ?? '' }}</div>
                                @if (!empty($resultado['criterios']))
                                    <div class="small mt-1" style="white-space: pre-line;">{{ $resultado['criterios'] }}</div>
                                @endif
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
@include('contable.reporte_definible.partials.modal_grupo_ccosto')
@endsection
