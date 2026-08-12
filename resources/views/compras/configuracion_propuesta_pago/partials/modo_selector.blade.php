@include('compras.configuracion_comprobante_proveedor.partials.flujo_proceso_estilos')

@php
    $modoPremium = (string) old('modo', $config->modo ?? 'premium') === 'premium';
@endphp

<div class="cp-flujo-proceso mb-4" id="pp-modo-proceso" data-flujo-inicial="{{ $modoPremium ? 'premium' : 'light' }}">
    <div class="d-flex flex-wrap align-items-end justify-content-between mb-2">
        <div>
            <h5 class="mb-1"><i class="fa fa-cogs"></i> Modo de tesorería / propuesta de pagos</h5>
            <p class="text-muted small mb-0">
                Premium: proyección + árbol de aprobación del lote + ejecución a OP (alineado SAP F110 / AGG).
                Light: menos burocracia — la propuesta se autoriza sin árbol (o se paga OP directa).
            </p>
        </div>
        <span class="badge badge-light border text-dark mt-2" id="pp-modo-etiqueta-activa">
            Escenario activo: {{ $modoPremium ? 'Premium' : 'Light' }}
        </span>
    </div>

    <input type="hidden" name="modo" id="pp_modo" value="{{ $modoPremium ? 'premium' : 'light' }}">
    <input type="hidden" name="exige_arbol_aprobacion" id="exige_arbol_aprobacion" value="{{ $modoPremium ? '1' : '0' }}">

    <div class="row">
        <div class="col-lg-6 mb-3">
            <button type="button"
                    class="cp-flujo-card {{ $modoPremium ? 'is-selected' : '' }} pp-modo-card"
                    data-modo="premium"
                    data-exige="1"
                    tabindex="0">
                <div class="cp-flujo-card__header">
                    <span class="cp-flujo-card__radio" aria-hidden="true"></span>
                    <div>
                        <strong>Premium (recomendado AGG)</strong>
                        <div class="cp-flujo-card__subtitle">Proyección → Árbol lote → Ejecutar OP</div>
                    </div>
                    <span class="badge badge-primary cp-flujo-card__tag">SAP-like</span>
                </div>
                <div class="cp-flujo-diagrama" aria-hidden="true">
                    <div class="cp-flujo-track">
                        <div class="cp-flujo-nodo"><span class="cp-flujo-nodo__code">PR</span><span class="cp-flujo-nodo__label">Propuesta</span></div>
                        <div class="cp-flujo-flecha"><span></span></div>
                        <div class="cp-flujo-nodo cp-flujo-nodo--obligatorio"><span class="cp-flujo-nodo__code">AP</span><span class="cp-flujo-nodo__label">Árbol lote</span></div>
                        <div class="cp-flujo-flecha"><span></span></div>
                        <div class="cp-flujo-nodo"><span class="cp-flujo-nodo__code">OP</span><span class="cp-flujo-nodo__label">Órdenes pago</span></div>
                    </div>
                </div>
                <ul class="cp-flujo-card__bullets text-left mb-0">
                    <li>Aprobación sobre el <strong>lote</strong> (no cada factura)</li>
                    <li>Grilla tipo Anita l-proy (vencidos / a vencer)</li>
                    <li>Ejecución batch → un OP por proveedor</li>
                </ul>
            </button>
        </div>
        <div class="col-lg-6 mb-3">
            <button type="button"
                    class="cp-flujo-card {{ ! $modoPremium ? 'is-selected' : '' }} pp-modo-card"
                    data-modo="light"
                    data-exige="0"
                    tabindex="0">
                <div class="cp-flujo-card__header">
                    <span class="cp-flujo-card__radio" aria-hidden="true"></span>
                    <div>
                        <strong>Light</strong>
                        <div class="cp-flujo-card__subtitle">Menos pasos · sin árbol de lote</div>
                    </div>
                    <span class="badge badge-secondary cp-flujo-card__tag">Simplificado</span>
                </div>
                <div class="cp-flujo-diagrama" aria-hidden="true">
                    <div class="cp-flujo-track">
                        <div class="cp-flujo-nodo"><span class="cp-flujo-nodo__code">PR</span><span class="cp-flujo-nodo__label">Propuesta</span></div>
                        <div class="cp-flujo-flecha"><span></span></div>
                        <div class="cp-flujo-nodo"><span class="cp-flujo-nodo__code">OK</span><span class="cp-flujo-nodo__label">Auto-autoriza</span></div>
                        <div class="cp-flujo-flecha"><span></span></div>
                        <div class="cp-flujo-nodo"><span class="cp-flujo-nodo__code">OP</span><span class="cp-flujo-nodo__label">Órdenes pago</span></div>
                    </div>
                </div>
                <ul class="cp-flujo-card__bullets text-left mb-0">
                    <li>Sin árbol sobre la propuesta</li>
                    <li>Al «Enviar» pasa directo a AUTORIZADA</li>
                    <li>OP unitaria sigue disponible si se habilita</li>
                </ul>
            </button>
        </div>
    </div>

    <div class="form-row mt-2">
        <div class="form-group col-md-4">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" name="ejecutar_confirmada" id="ejecutar_confirmada" value="1"
                    {{ old('ejecutar_confirmada', $config->ejecutar_confirmada ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="ejecutar_confirmada">Al ejecutar, crear OP CONFIRMADA (no precarga)</label>
            </div>
        </div>
        <div class="form-group col-md-4">
            <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" name="permite_op_sin_propuesta" id="permite_op_sin_propuesta" value="1"
                    {{ old('permite_op_sin_propuesta', $config->permite_op_sin_propuesta ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label" for="permite_op_sin_propuesta">Permitir OP unitaria sin pasar por propuesta</label>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var root = document.getElementById('pp-modo-proceso');
    if (!root) return;
    var hiddenModo = document.getElementById('pp_modo');
    var hiddenExige = document.getElementById('exige_arbol_aprobacion');
    var etiqueta = document.getElementById('pp-modo-etiqueta-activa');
    function sel(modo, exige) {
        root.querySelectorAll('.pp-modo-card').forEach(function (btn) {
            var on = btn.getAttribute('data-modo') === modo;
            btn.classList.toggle('is-selected', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        if (hiddenModo) hiddenModo.value = modo;
        if (hiddenExige) hiddenExige.value = exige;
        if (etiqueta) etiqueta.textContent = 'Escenario activo: ' + (modo === 'premium' ? 'Premium' : 'Light');
    }
    root.querySelectorAll('.pp-modo-card').forEach(function (btn) {
        btn.addEventListener('click', function () {
            sel(btn.getAttribute('data-modo'), btn.getAttribute('data-exige'));
        });
    });
})();
</script>
