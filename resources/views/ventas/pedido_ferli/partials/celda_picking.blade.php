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
    <input type="text"
           class="form-control form-control-sm picking-lote mb-1"
           placeholder="Lote / OT stock"
           title="C&oacute;digo de OT stock o lote a preparar"
           value="{{ $pickingLote }}"
           @if ($pickingFacturado || $pickingMarcado) readonly @endif>
    <select class="form-control form-control-sm picking-deposito mb-1"
            title="Dep&oacute;sito de salida"
            @if ($pickingFacturado || $pickingMarcado) disabled @endif>
        <option value="0">Dep&oacute;sito…</option>
        @foreach ($depositosPicking as $dep)
            <option value="{{ $dep->id }}" @if ($pickingDep === (int) $dep->id) selected @endif>
                {{ trim(($dep->codigo ?? '').' — '.($dep->nombre ?? ''), ' —') }}
            </option>
        @endforeach
    </select>
    @if ($pickingFacturado)
        <small class="text-muted d-block">Ya facturado</small>
    @elseif ($pickingMarcado)
        <button type="button"
                title="Quitar picking"
                class="btn btn-sm btn-outline-secondary btn-block guarda-picking tooltipsC">
            <i class="fa fa-undo"></i> Quitar
        </button>
    @else
        <button type="button"
                title="Marcar como preparado"
                class="btn btn-sm btn-outline-primary btn-block guarda-picking tooltipsC">
            <i class="fa fa-check"></i> Preparar
        </button>
    @endif
</div>
