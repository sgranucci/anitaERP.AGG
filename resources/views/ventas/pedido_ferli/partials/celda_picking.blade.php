@php
    $pickingMarcado = ($pickingMarcado ?? false) === true || ($pickingMarcado ?? 'N') === 'S';
    $pickingFacturado = ($pickingFacturado ?? false) === true || ($pickingFacturado ?? 'N') === 'S';
    $pickingLote = (string) ($pickingLote ?? '');
    $pickingDep = (int) ($pickingDep ?? 0);
    $depositosPicking = $depositosPicking ?? ($depositos_picking_query ?? collect());
    if ($pickingFacturado) {
        $estadoPicking = 'facturado';
    } elseif ($pickingMarcado) {
        $estadoPicking = 'preparado';
    } else {
        $estadoPicking = 'pendiente';
    }
@endphp
<div class="picking-box"
     data-estado="{{ $estadoPicking }}"
     data-picking-marcado="{{ $pickingMarcado ? 'S' : 'N' }}"
     data-picking-facturado="{{ $pickingFacturado ? 'S' : 'N' }}">
    <div class="picking-estado mb-1">
        @if ($estadoPicking === 'facturado')
            <span class="badge badge-success picking-estado-badge">Facturado</span>
        @elseif ($estadoPicking === 'preparado')
            <span class="badge badge-warning picking-estado-badge">Preparado</span>
        @else
            <span class="badge badge-secondary picking-estado-badge">Pendiente</span>
        @endif
    </div>
    <div class="input-group input-group-sm picking-lote-grupo mb-1">
        <input type="text"
               class="form-control form-control-sm picking-lote"
               placeholder="Lote / OT stock"
               title="C&oacute;digo de OT stock o lote a preparar (F1 consulta stock)"
               value="{{ $pickingLote }}"
               @if ($pickingFacturado || $pickingMarcado) readonly @endif>
        <div class="input-group-append">
            <button type="button"
                    class="btn btn-outline-secondary consulta-lotes-stock-picking tooltipsC"
                    title="Consultar m&oacute;dulos/lotes de stock pendientes (F1)"
                    @if ($pickingFacturado || $pickingMarcado) disabled @endif>
                <i class="fa fa-search"></i>
            </button>
        </div>
    </div>
    <select class="form-control form-control-sm picking-deposito mb-1"
            title="Dep&oacute;sito con saldo del lote (usar F1 / Elegir)"
            @if ($pickingFacturado || $pickingMarcado) disabled @endif>
        <option value="0">Dep&oacute;sito…</option>
        @foreach ($depositosPicking as $dep)
            <option value="{{ $dep->id }}" @if ($pickingDep === (int) $dep->id) selected @endif>
                {{ trim(($dep->codigo ?? '').' — '.($dep->nombre ?? ''), ' —') }}
            </option>
        @endforeach
    </select>
    @if ($pickingFacturado)
        <small class="text-muted d-block picking-facturado-msg">Ya facturado</small>
    @elseif ($pickingMarcado)
        <button type="button"
                title="Quitar picking y devolver stock al lote/OT"
                class="btn btn-sm btn-outline-secondary btn-block guarda-picking tooltipsC">
            <i class="fa fa-undo"></i> Quitar
        </button>
        <small class="text-muted d-block picking-ayuda" title="Stock ya descontado del lote/OT">
            Stock descontado (atrapado)
        </small>
    @else
        <button type="button"
                title="Preparar y descontar stock del lote/OT"
                class="btn btn-sm btn-outline-primary btn-block guarda-picking tooltipsC">
            <i class="fa fa-check"></i> Preparar
        </button>
        <small class="text-muted d-block picking-ayuda">
            Al preparar descuenta stock; us&aacute; F1
        </small>
    @endif
</div>
