@php
    $flujoEstricto = (bool) old('exige_flujo_oc_com_fac', $config->exige_flujo_oc_com_fac ?? false);
@endphp
@include('compras.configuracion_comprobante_proveedor.partials.flujo_proceso_estilos')

<div class="cp-flujo-proceso mb-4" id="cp-flujo-proceso" data-flujo-inicial="{{ $flujoEstricto ? 'estricto' : 'flexible' }}">
    <div class="d-flex flex-wrap align-items-end justify-content-between mb-2">
        <div>
            <h5 class="mb-1"><i class="fa fa-sitemap"></i> Proceso de compras (Procure-to-Pay)</h5>
            <p class="text-muted small mb-0">
                Elija el escenario operativo de la empresa, al estilo de variantes SAP Best Practices.
                El diagrama muestra el camino obligatorio y las excepciones admitidas.
            </p>
        </div>
        <span class="badge badge-light border text-dark mt-2" id="cp-flujo-etiqueta-activa">
            Escenario activo: —
        </span>
    </div>

    <input type="hidden" name="exige_flujo_oc_com_fac" id="exige_flujo_oc_com_fac" value="{{ $flujoEstricto ? '1' : '0' }}">

    <div class="row">
        {{-- Escenario A: estricto OC/COM/FAC --}}
        <div class="col-lg-6 mb-3">
            <button type="button"
                    class="cp-flujo-card {{ $flujoEstricto ? 'is-selected' : '' }}"
                    data-flujo="estricto"
                    data-exige="1"
                    tabindex="0"
                    aria-pressed="{{ $flujoEstricto ? 'true' : 'false' }}">
                <div class="cp-flujo-card__header">
                    <span class="cp-flujo-card__radio" aria-hidden="true"></span>
                    <div>
                        <strong>Estándar con recepción (MM)</strong>
                        <div class="cp-flujo-card__subtitle">OC → COM → Factura · excepción anticipada</div>
                    </div>
                    <span class="badge badge-primary cp-flujo-card__tag">Recomendado AGG</span>
                </div>

                <div class="cp-flujo-diagrama" aria-hidden="true">
                    <div class="cp-flujo-track">
                        <div class="cp-flujo-nodo cp-flujo-nodo--oc">
                            <span class="cp-flujo-nodo__code">PO</span>
                            <span class="cp-flujo-nodo__label">Orden de compra</span>
                        </div>
                        <div class="cp-flujo-flecha"><span></span></div>
                        <div class="cp-flujo-nodo cp-flujo-nodo--com cp-flujo-nodo--obligatorio" data-com-fi-nodo>
                            <span class="cp-flujo-nodo__code js-com-fi-code">GR</span>
                            <span class="cp-flujo-nodo__label js-com-fi-label">Recepción COM</span>
                            <span class="cp-flujo-nodo__hint js-com-fi-hint">Obligatoria</span>
                        </div>
                        <div class="cp-flujo-flecha"><span></span></div>
                        <div class="cp-flujo-nodo cp-flujo-nodo--fac">
                            <span class="cp-flujo-nodo__code">IR</span>
                            <span class="cp-flujo-nodo__label">Factura</span>
                        </div>
                    </div>
                    <div class="cp-flujo-rama">
                        <div class="cp-flujo-rama__label">Excepción</div>
                        <div class="cp-flujo-track cp-flujo-track--rama">
                            <div class="cp-flujo-nodo cp-flujo-nodo--oc cp-flujo-nodo--suave">
                                <span class="cp-flujo-nodo__code">PO*</span>
                                <span class="cp-flujo-nodo__label">OC anticipada</span>
                            </div>
                            <div class="cp-flujo-flecha cp-flujo-flecha--dashed"><span></span></div>
                            <div class="cp-flujo-nodo cp-flujo-nodo--fac cp-flujo-nodo--suave">
                                <span class="cp-flujo-nodo__code">IR*</span>
                                <span class="cp-flujo-nodo__label">Factura anticipada</span>
                                <span class="cp-flujo-nodo__hint">Sin COM aún · N facturas</span>
                            </div>
                            <div class="cp-flujo-flecha cp-flujo-flecha--dashed"><span></span></div>
                            <div class="cp-flujo-nodo cp-flujo-nodo--com cp-flujo-nodo--suave">
                                <span class="cp-flujo-nodo__code">GR</span>
                                <span class="cp-flujo-nodo__label">COM posterior</span>
                            </div>
                        </div>
                    </div>
                </div>

                <ul class="cp-flujo-card__bullets text-left mb-0">
                    <li>3-way match conceptual: OC · recepción · factura</li>
                    <li>Sin COM y OC no anticipada → no se carga factura</li>
                    <li>Varias facturas anticipadas permitidas hasta la primera COM</li>
                    <li>AGG: combinar con provisión automática (GR valuado) abajo</li>
                </ul>
            </button>
        </div>

        {{-- Escenario B: flexible --}}
        <div class="col-lg-6 mb-3">
            <button type="button"
                    class="cp-flujo-card {{ ! $flujoEstricto ? 'is-selected' : '' }}"
                    data-flujo="flexible"
                    data-exige="0"
                    tabindex="0"
                    aria-pressed="{{ ! $flujoEstricto ? 'true' : 'false' }}">
                <div class="cp-flujo-card__header">
                    <span class="cp-flujo-card__radio" aria-hidden="true"></span>
                    <div>
                        <strong>Flexible (sin circuito COM fijo)</strong>
                        <div class="cp-flujo-card__subtitle">OC / gasto → Factura · COM optativa</div>
                    </div>
                    <span class="badge badge-secondary cp-flujo-card__tag">Otras empresas</span>
                </div>

                <div class="cp-flujo-diagrama" aria-hidden="true">
                    <div class="cp-flujo-track">
                        <div class="cp-flujo-nodo cp-flujo-nodo--oc">
                            <span class="cp-flujo-nodo__code">PO</span>
                            <span class="cp-flujo-nodo__label">Orden / gasto</span>
                        </div>
                        <div class="cp-flujo-flecha"><span></span></div>
                        <div class="cp-flujo-nodo cp-flujo-nodo--fac">
                            <span class="cp-flujo-nodo__code">IR</span>
                            <span class="cp-flujo-nodo__label">Factura</span>
                        </div>
                    </div>
                    <div class="cp-flujo-rama">
                        <div class="cp-flujo-rama__label">Opcional</div>
                        <div class="cp-flujo-track cp-flujo-track--rama">
                            <div class="cp-flujo-nodo cp-flujo-nodo--com cp-flujo-nodo--suave">
                                <span class="cp-flujo-nodo__code">GR?</span>
                                <span class="cp-flujo-nodo__label">COM si existe</span>
                                <span class="cp-flujo-nodo__hint">Solo si hay recepción</span>
                            </div>
                            <div class="cp-flujo-flecha cp-flujo-flecha--dashed"><span></span></div>
                            <div class="cp-flujo-nodo cp-flujo-nodo--fac cp-flujo-nodo--suave">
                                <span class="cp-flujo-nodo__code">IR</span>
                                <span class="cp-flujo-nodo__label">Asocia COM</span>
                            </div>
                        </div>
                    </div>
                </div>

                <ul class="cp-flujo-card__bullets text-left mb-0">
                    <li>Ideal si la empresa no opera recepción de stock en el legajo</li>
                    <li>Si aparece una COM disponible, igual se pide asociarla</li>
                    <li>No bloquea facturas sin recepción</li>
                </ul>
            </button>
        </div>
    </div>

    <div class="cp-flujo-leyenda small text-muted">
        <span><i class="cp-flujo-dot cp-flujo-dot--oc"></i> PO = Purchase Order (OC)</span>
        <span><i class="cp-flujo-dot cp-flujo-dot--com"></i> GR = Goods Receipt (COM)</span>
        <span><i class="cp-flujo-dot cp-flujo-dot--fac"></i> IR = Invoice Receipt (Factura)</span>
    </div>
</div>
