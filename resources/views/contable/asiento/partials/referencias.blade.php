@php
    use App\Support\Contable\AsientoReferenciaAnitaSupport;

    $refs = $asiento_referencias ?? AsientoReferenciaAnitaSupport::etiquetasDesdeAsiento($data ?? null);
    $tipoActual = old('referencia_tipo', $refs['referencia_tipo'] ?? AsientoReferenciaAnitaSupport::TIPO_NINGUNA);
    $ocId = (int) old('ordencompra_id', $refs['ordencompra_id'] ?? 0);
    $cpId = (int) old('comprobante_proveedor_id', $refs['comprobante_proveedor_id'] ?? 0);
    $ventaId = (int) old('venta_id', $refs['venta_id'] ?? 0);
    $ocCodigo = old('ordencompra_codigo', $refs['ordencompra_codigo'] ?? '');
    $ocDesc = old('ordencompra_descripcion', $refs['ordencompra_descripcion'] ?? '');
    $cpCodigo = old('comprobante_proveedor_codigo', $refs['comprobante_proveedor_codigo'] ?? '');
    $cpDesc = old('comprobante_proveedor_descripcion', $refs['comprobante_proveedor_descripcion'] ?? '');
    $ventaCodigo = old('venta_codigo', $refs['venta_codigo'] ?? '');
    $ventaDesc = old('venta_descripcion', $refs['venta_descripcion'] ?? '');

    $puedeAbmOc = can('listar-ordencompra', false) || can('editar-ordencompra', false);
    $puedeAbmCp = can('listar-comprobante-proveedor', false) || can('editar-comprobante-proveedor', false);
    $puedeAbmVenta = can('listar-factura', false) || can('editar-factura', false);

    $chips = [
        AsientoReferenciaAnitaSupport::TIPO_NINGUNA => ['label' => 'Sin referencia', 'icon' => 'fa-unlink'],
        AsientoReferenciaAnitaSupport::TIPO_ORDENCOMPRA => ['label' => 'Orden de compra', 'icon' => 'fa-shopping-cart'],
        AsientoReferenciaAnitaSupport::TIPO_COMPROBANTE_PROVEEDOR => ['label' => 'Factura proveedor', 'icon' => 'fa-file-invoice'],
        AsientoReferenciaAnitaSupport::TIPO_VENTA => ['label' => 'Factura venta', 'icon' => 'fa-cash-register'],
        AsientoReferenciaAnitaSupport::TIPO_OC_Y_COMPROBANTE => ['label' => 'OC + factura compra', 'icon' => 'fa-link'],
    ];

    $tieneAlgunaRef = $ocId > 0 || $cpId > 0 || $ventaId > 0
        || $tipoActual !== AsientoReferenciaAnitaSupport::TIPO_NINGUNA;
    $tipoLabel = $chips[$tipoActual]['label'] ?? 'Sin referencia';
    $tipoIcon = $chips[$tipoActual]['icon'] ?? 'fa-unlink';
@endphp

<div class="asiento-ref-card mb-3 is-collapsed" id="asiento-referencias" data-collapsed="1">
    <input type="hidden" name="referencia_tipo" id="referencia_tipo" value="{{ $tipoActual }}">

    {{-- Barra compacta (siempre visible) --}}
    <div class="asiento-ref-compact" id="asiento-ref-compact">
        <div class="asiento-ref-compact-main">
            <span class="asiento-ref-compact-icon" aria-hidden="true">
                <i class="fa fa-link"></i>
            </span>
            <div class="asiento-ref-compact-body">
                <div class="asiento-ref-compact-title-row">
                    <span class="asiento-ref-compact-title">Referencia</span>
                    <span class="badge badge-light asiento-ref-badge">Opcional</span>
                </div>
                <div class="asiento-ref-compact-summary" id="asiento-ref-compact-summary">
                    <span class="asiento-ref-tipo-pill {{ $tieneAlgunaRef && $tipoActual !== AsientoReferenciaAnitaSupport::TIPO_NINGUNA ? '' : 'is-muted' }}" id="asiento-ref-tipo-pill">
                        <i class="fa {{ $tipoIcon }} mr-1" id="asiento-ref-tipo-icon"></i>
                        <span id="asiento-ref-tipo-label">{{ $tipoLabel }}</span>
                    </span>
                    <span class="asiento-ref-pills" id="asiento-ref-pills">
                        <span class="asiento-ref-chip-resumen {{ $ocId > 0 ? '' : 'd-none' }}" id="asiento-ref-pill-oc" data-kind="oc">{{ $ocDesc }}</span>
                        <span class="asiento-ref-chip-resumen {{ $cpId > 0 ? '' : 'd-none' }}" id="asiento-ref-pill-cp" data-kind="cp">{{ $cpDesc }}</span>
                        <span class="asiento-ref-chip-resumen {{ $ventaId > 0 ? '' : 'd-none' }}" id="asiento-ref-pill-venta" data-kind="venta">{{ $ventaDesc }}</span>
                    </span>
                    <span class="asiento-ref-compact-hint text-muted {{ $tieneAlgunaRef && ($ocId > 0 || $cpId > 0 || $ventaId > 0) ? 'd-none' : '' }}" id="asiento-ref-compact-hint">
                        Sin enganche a OC ni factura — aparece como link en el mayor si la cargás
                    </span>
                </div>
            </div>
        </div>
        <button type="button"
                class="btn asiento-ref-toggle"
                id="asiento-ref-toggle"
                aria-expanded="false"
                aria-controls="asiento-ref-editor">
            <i class="fa fa-pen mr-1" id="asiento-ref-toggle-icon"></i>
            <span id="asiento-ref-toggle-label">{{ $tieneAlgunaRef ? 'Cambiar' : 'Agregar' }}</span>
        </button>
    </div>

    {{-- Editor colapsable --}}
    <div class="asiento-ref-editor" id="asiento-ref-editor" hidden>
        <div class="asiento-ref-editor-inner">
            <div class="asiento-ref-editor-hint text-muted mb-2">
                Elegí el tipo y buscá con F1 o Enter. Al terminar, podés cerrar el panel.
            </div>

            <div class="asiento-ref-chips d-flex flex-wrap" role="group" aria-label="Tipo de referencia">
                @foreach ($chips as $valor => $meta)
                    <button type="button"
                            class="asiento-ref-chip {{ $tipoActual === $valor ? 'is-active' : '' }}"
                            data-referencia-tipo="{{ $valor }}">
                        <i class="fa {{ $meta['icon'] }} mr-1"></i>{{ $meta['label'] }}
                    </button>
                @endforeach
            </div>

            <div class="asiento-ref-empty text-muted {{ $tipoActual === AsientoReferenciaAnitaSupport::TIPO_NINGUNA ? '' : 'd-none' }}" id="asiento-ref-empty">
                Este asiento no se engancha a OC ni factura.
            </div>

            <div class="asiento-ref-panels mt-3">
                <div class="asiento-ref-panel {{ in_array($tipoActual, [AsientoReferenciaAnitaSupport::TIPO_ORDENCOMPRA, AsientoReferenciaAnitaSupport::TIPO_OC_Y_COMPROBANTE], true) ? '' : 'd-none' }}"
                     data-panel="ordencompra" id="asiento-ref-panel-oc">
                    <label class="asiento-ref-campo-label">Orden de compra</label>
                    <div class="d-flex flex-nowrap align-items-center asiento-ref-campo" style="gap: 4px;" id="asiento-campo-oc">
                        <input type="hidden" name="ordencompra_id" id="ordencompra_id" class="ordencompra_id" value="{{ $ocId > 0 ? $ocId : '' }}">
                        <button type="button" title="Consulta ordenes de compra (F1)" class="btn-accion-tabla consulta-asiento-oc flex-shrink-0">
                            <i class="fa fa-search text-primary"></i>
                        </button>
                        @if ($puedeAbmOc)
                            <a href="{{ $ocId > 0 ? route('editar_ordencompra', ['id' => $ocId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) : '#' }}"
                               target="_blank" rel="noopener"
                               class="btn-accion-tabla btn-link-editar-asiento-oc tooltipsC flex-shrink-0 {{ $ocId > 0 ? '' : 'd-none' }}"
                               title="Consultar OC en ABM">
                                <i class="fa fa-edit"></i>
                            </a>
                        @endif
                        <input type="text" class="form-control codigo-asiento-oc" id="ordencompra_codigo"
                               value="{{ $ocCodigo }}" placeholder="F1 o Enter…" autocomplete="off"
                               style="width: 7rem; flex-shrink: 0;">
                        <input type="text" class="form-control descripcion-asiento-oc text-truncate" id="ordencompra_descripcion"
                               value="{{ $ocDesc }}" placeholder="Descripcion" readonly
                               style="min-width: 0; flex: 1 1 auto;">
                        <button type="button" class="asiento-ref-clear flex-shrink-0 {{ $ocId > 0 ? '' : 'd-none' }}" data-clear="ordencompra" title="Quitar" id="asiento-ref-clear-oc">&times;</button>
                    </div>
                </div>

                <div class="asiento-ref-panel {{ in_array($tipoActual, [AsientoReferenciaAnitaSupport::TIPO_COMPROBANTE_PROVEEDOR, AsientoReferenciaAnitaSupport::TIPO_OC_Y_COMPROBANTE], true) ? '' : 'd-none' }}"
                     data-panel="comprobante_proveedor" id="asiento-ref-panel-cp">
                    <label class="asiento-ref-campo-label">Factura de proveedor</label>
                    <div class="d-flex flex-nowrap align-items-center asiento-ref-campo" style="gap: 4px;" id="asiento-campo-cp">
                        <input type="hidden" name="comprobante_proveedor_id" id="comprobante_proveedor_id" class="comprobante_proveedor_id" value="{{ $cpId > 0 ? $cpId : '' }}">
                        <button type="button" title="Consulta facturas proveedor (F1)" class="btn-accion-tabla consulta-asiento-cp flex-shrink-0">
                            <i class="fa fa-search text-primary"></i>
                        </button>
                        @if ($puedeAbmCp)
                            <a href="{{ $cpId > 0 ? route('editar_comprobante_proveedor', ['id' => $cpId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) : '#' }}"
                               target="_blank" rel="noopener"
                               class="btn-accion-tabla btn-link-editar-asiento-cp tooltipsC flex-shrink-0 {{ $cpId > 0 ? '' : 'd-none' }}"
                               title="Consultar comprobante en ABM">
                                <i class="fa fa-edit"></i>
                            </a>
                        @endif
                        <input type="text" class="form-control codigo-asiento-cp" id="comprobante_proveedor_codigo"
                               value="{{ $cpCodigo }}" placeholder="F1 o Enter…" autocomplete="off"
                               style="width: 10rem; flex-shrink: 0;">
                        <input type="text" class="form-control descripcion-asiento-cp text-truncate" id="comprobante_proveedor_descripcion"
                               value="{{ $cpDesc }}" placeholder="Descripcion" readonly
                               style="min-width: 0; flex: 1 1 auto;">
                        <button type="button" class="asiento-ref-clear flex-shrink-0 {{ $cpId > 0 ? '' : 'd-none' }}" data-clear="comprobante_proveedor" title="Quitar" id="asiento-ref-clear-cp">&times;</button>
                    </div>
                </div>

                <div class="asiento-ref-panel {{ $tipoActual === AsientoReferenciaAnitaSupport::TIPO_VENTA ? '' : 'd-none' }}"
                     data-panel="venta" id="asiento-ref-panel-venta">
                    <label class="asiento-ref-campo-label">Factura de venta</label>
                    <div class="d-flex flex-nowrap align-items-center asiento-ref-campo" style="gap: 4px;" id="asiento-campo-venta">
                        <input type="hidden" name="venta_id" id="venta_id" class="venta_id" value="{{ $ventaId > 0 ? $ventaId : '' }}">
                        <button type="button" title="Consulta facturas de venta (F1)" class="btn-accion-tabla consulta-asiento-venta flex-shrink-0">
                            <i class="fa fa-search text-primary"></i>
                        </button>
                        @if ($puedeAbmVenta)
                            <a href="{{ $ventaId > 0 ? route('editar_factura', ['id' => $ventaId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) : '#' }}"
                               target="_blank" rel="noopener"
                               class="btn-accion-tabla btn-link-editar-asiento-venta tooltipsC flex-shrink-0 {{ $ventaId > 0 ? '' : 'd-none' }}"
                               title="Consultar factura en ABM">
                                <i class="fa fa-edit"></i>
                            </a>
                        @endif
                        <input type="text" class="form-control codigo-asiento-venta" id="venta_codigo"
                               value="{{ $ventaCodigo }}" placeholder="F1 o Enter…" autocomplete="off"
                               style="width: 10rem; flex-shrink: 0;">
                        <input type="text" class="form-control descripcion-asiento-venta text-truncate" id="venta_descripcion"
                               value="{{ $ventaDesc }}" placeholder="Descripcion" readonly
                               style="min-width: 0; flex: 1 1 auto;">
                        <button type="button" class="asiento-ref-clear flex-shrink-0 {{ $ventaId > 0 ? '' : 'd-none' }}" data-clear="venta" title="Quitar" id="asiento-ref-clear-venta">&times;</button>
                    </div>
                </div>
            </div>

            <div class="asiento-ref-editor-footer mt-3">
                <button type="button" class="btn btn-sm asiento-ref-done" id="asiento-ref-done">
                    <i class="fa fa-check mr-1"></i> Listo
                </button>
            </div>
        </div>
    </div>
</div>
