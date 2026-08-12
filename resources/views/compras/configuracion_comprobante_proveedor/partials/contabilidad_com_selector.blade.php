@php
    $comContabilidad = (bool) old(
        'com_genera_contabilidad',
        $comGeneraContabilidad ?? true
    );
@endphp

<div class="cp-flujo-proceso mb-4" id="cp-com-contabilidad" data-contab-inicial="{{ $comContabilidad ? 'provision' : 'factura' }}">
    <div class="d-flex flex-wrap align-items-end justify-content-between mb-2">
        <div>
            <h5 class="mb-1"><i class="fa fa-balance-scale"></i> Contabilidad de la recepción (COM)</h5>
            <p class="text-muted small mb-0">
                En Argentina y en SAP MM esto es el GR valuado vs no valuado: si la COM provisiona
                <em>Facturas a recibir</em> o si la factura imputa el gasto/bien de una vez.
            </p>
        </div>
        <span class="badge badge-light border text-dark mt-2" id="cp-contab-etiqueta-activa">
            Contabilidad activa: —
        </span>
    </div>

    <input type="hidden" name="com_genera_contabilidad" id="com_genera_contabilidad" value="{{ $comContabilidad ? '1' : '0' }}">

    <div class="row">
        {{-- Provisión automática (AGG) --}}
        <div class="col-lg-6 mb-3">
            <button type="button"
                    class="cp-flujo-card {{ $comContabilidad ? 'is-selected' : '' }}"
                    data-contab="provision"
                    data-activa="1"
                    tabindex="0"
                    aria-pressed="{{ $comContabilidad ? 'true' : 'false' }}">
                <div class="cp-flujo-card__header">
                    <span class="cp-flujo-card__radio" aria-hidden="true"></span>
                    <div>
                        <strong>Provisión automática (GR valuado)</strong>
                        <div class="cp-flujo-card__subtitle">COM genera asiento · Factura revierte FAR</div>
                    </div>
                    <span class="badge badge-success cp-flujo-card__tag">Obligatorio AGG</span>
                </div>

                <div class="cp-flujo-diagrama" aria-hidden="true">
                    <div class="cp-flujo-track">
                        <div class="cp-flujo-nodo cp-flujo-nodo--com">
                            <span class="cp-flujo-nodo__code">GR+FI</span>
                            <span class="cp-flujo-nodo__label">COM + asiento</span>
                            <span class="cp-flujo-nodo__hint">D Bienes / gasto</span>
                        </div>
                        <div class="cp-flujo-flecha"><span></span></div>
                        <div class="cp-flujo-nodo cp-flujo-nodo--far">
                            <span class="cp-flujo-nodo__code">FAR</span>
                            <span class="cp-flujo-nodo__label">Facturas a recibir</span>
                            <span class="cp-flujo-nodo__hint">Haber provisión</span>
                        </div>
                        <div class="cp-flujo-flecha"><span></span></div>
                        <div class="cp-flujo-nodo cp-flujo-nodo--fac">
                            <span class="cp-flujo-nodo__code">IR</span>
                            <span class="cp-flujo-nodo__label">Factura</span>
                            <span class="cp-flujo-nodo__hint">Revierte FAR + IVA + Prov.</span>
                        </div>
                    </div>
                    <div class="cp-flujo-asiento-mini">
                        <div><strong>COM:</strong> Debe stock/gasto · Haber provisión FAR (sin IVA)</div>
                        <div><strong>Factura:</strong> Debe FAR + IVA · Haber proveedores</div>
                    </div>
                </div>

                <ul class="cp-flujo-card__bullets text-left mb-0">
                    <li>Mejor práctica AR / SAP MM: bienes recibidos no facturados (GRNI) al día</li>
                    <li>El IVA entra solo con la factura (AFIP / libro IVA)</li>
                    <li>Diferencia de precio COM↔factura se prorratea a artículos</li>
                </ul>
            </button>
        </div>

        {{-- Sin provisión --}}
        <div class="col-lg-6 mb-3">
            <button type="button"
                    class="cp-flujo-card {{ ! $comContabilidad ? 'is-selected' : '' }}"
                    data-contab="factura"
                    data-activa="0"
                    tabindex="0"
                    aria-pressed="{{ ! $comContabilidad ? 'true' : 'false' }}">
                <div class="cp-flujo-card__header">
                    <span class="cp-flujo-card__radio" aria-hidden="true"></span>
                    <div>
                        <strong>Sin provisión (GR no valuado)</strong>
                        <div class="cp-flujo-card__subtitle">COM solo logística · Factura imputa todo</div>
                    </div>
                    <span class="badge badge-secondary cp-flujo-card__tag">Otras empresas</span>
                </div>

                <div class="cp-flujo-diagrama" aria-hidden="true">
                    <div class="cp-flujo-track">
                        <div class="cp-flujo-nodo cp-flujo-nodo--com cp-flujo-nodo--suave">
                            <span class="cp-flujo-nodo__code">GR</span>
                            <span class="cp-flujo-nodo__label">COM stock</span>
                            <span class="cp-flujo-nodo__hint">Sin asiento FI</span>
                        </div>
                        <div class="cp-flujo-flecha cp-flujo-flecha--dashed"><span></span></div>
                        <div class="cp-flujo-nodo cp-flujo-nodo--fac">
                            <span class="cp-flujo-nodo__code">IR+FI</span>
                            <span class="cp-flujo-nodo__label">Factura contable</span>
                            <span class="cp-flujo-nodo__hint">Neto + IVA + Prov.</span>
                        </div>
                    </div>
                    <div class="cp-flujo-asiento-mini">
                        <div><strong>COM:</strong> movimiento de stock / cierre OC (sin mayor)</div>
                        <div><strong>Factura:</strong> Debe conceptos (neto/IVA) · Haber proveedores</div>
                    </div>
                </div>

                <ul class="cp-flujo-card__bullets text-left mb-0">
                    <li>Útil si la empresa no provisiona compras o opera servicios sin stock</li>
                    <li>La COM sigue sirviendo para 3-way match de cantidades/importes</li>
                    <li>No genera saldo de facturas a recibir al confirmar recepción</li>
                </ul>
            </button>
        </div>
    </div>

    <div class="cp-flujo-leyenda small text-muted">
        <span><i class="cp-flujo-dot cp-flujo-dot--com"></i> GR = Goods Receipt</span>
        <span><i class="cp-flujo-dot cp-flujo-dot--far"></i> FAR = Facturas a recibir (provisión)</span>
        <span><i class="cp-flujo-dot cp-flujo-dot--fac"></i> IR = Invoice Receipt</span>
        <span class="ml-lg-2">
            Cuentas FAR / anticipos:
            <a href="{{ route('configuracion_recepcion_proveedor', ['empresa_id' => $empresa_id ?? null]) }}" target="_blank" rel="noopener">
                Configuración recepción proveedores
            </a>
        </span>
    </div>
</div>
