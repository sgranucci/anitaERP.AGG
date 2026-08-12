@include('compras.configuracion_comprobante_proveedor.partials.flujo_proceso_estilos')

@php
    $modoIeInicial = old('ie_modo_uso', 'general');
@endphp

<div class="cp-flujo-proceso mb-3" id="ie-modo-uso" data-flujo-inicial="{{ $modoIeInicial }}">
    <div class="d-flex flex-wrap align-items-end justify-content-between mb-2">
        <div>
            <h5 class="mb-1"><i class="fa fa-random"></i> Tipo de movimiento</h5>
            <p class="text-muted small mb-0">
                IE cubre cualquier movimiento de caja. Elija el escenario para filtrar tipos y orientar solapas
                (no limita lo que puede guardar).
            </p>
        </div>
        <span class="badge badge-light border text-dark mt-2" id="ie-modo-etiqueta">Escenario: Operación general</span>
    </div>

    <input type="hidden" name="ie_modo_uso" id="ie_modo_uso" value="{{ $modoIeInicial }}">

    <div class="row">
        <div class="col-lg-4 mb-2">
            <button type="button" class="cp-flujo-card is-selected ie-modo-card" data-modo="general" tabindex="0">
                <div class="cp-flujo-card__header">
                    <span class="cp-flujo-card__radio" aria-hidden="true"></span>
                    <div>
                        <strong>Operación general</strong>
                        <div class="cp-flujo-card__subtitle">Ingresos, egresos, OPP, cobranza</div>
                    </div>
                </div>
                <ul class="cp-flujo-card__bullets text-left mb-0">
                    <li>Select de tipos I / E / P / C</li>
                    <li>Proveedor/gasto si es egreso</li>
                    <li>Cheques y comprobantes disponibles</li>
                </ul>
            </button>
        </div>
        <div class="col-lg-4 mb-2">
            <button type="button" class="cp-flujo-card ie-modo-card" data-modo="transferencia" tabindex="0">
                <div class="cp-flujo-card__header">
                    <span class="cp-flujo-card__radio" aria-hidden="true"></span>
                    <div>
                        <strong>Transferencia entre cuentas</strong>
                        <div class="cp-flujo-card__subtitle">TRA — dos piernas +/−</div>
                    </div>
                    <span class="badge badge-info cp-flujo-card__tag">TRA</span>
                </div>
                <ul class="cp-flujo-card__bullets text-left mb-0">
                    <li>Filtra tipo TRA</li>
                    <li>Aviso de montos firmados</li>
                    <li>≥ 2 renglones de cuenta caja</li>
                </ul>
            </button>
        </div>
        <div class="col-lg-4 mb-2">
            <button type="button" class="cp-flujo-card ie-modo-card" data-modo="canje_cheques" tabindex="0">
                <div class="cp-flujo-card__header">
                    <span class="cp-flujo-card__radio" aria-hidden="true"></span>
                    <div>
                        <strong>Canje / reemplazo de cheques</strong>
                        <div class="cp-flujo-card__subtitle">Orientado a solapa Cheques</div>
                    </div>
                </div>
                <ul class="cp-flujo-card__bullets text-left mb-0">
                    <li>Prefiere egreso / OPP</li>
                    <li>Abre solapa Cheques → Reemplazo</li>
                    <li>Emitidos / recibidos también</li>
                </ul>
            </button>
        </div>
    </div>
</div>
