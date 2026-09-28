@php
    $pickingMarcado = ($pickingMarcado ?? false) === true || ($pickingMarcado ?? 'N') === 'S';
    $pickingFacturado = ($pickingFacturado ?? false) === true || ($pickingFacturado ?? 'N') === 'S';
    $pickingLote = (string) ($pickingLote ?? '');
    $pickingDep = (int) ($pickingDep ?? 0);
    $pickingOtId = (int) ($pickingOtId ?? 0);
    $pickingCodigo = (int) ($pickingCodigo ?? 0);
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
     data-picking-facturado="{{ $pickingFacturado ? 'S' : 'N' }}"
     data-picking-codigo="{{ $pickingCodigo > 0 ? $pickingCodigo : '' }}">
    <div class="picking-estado mb-1">
        @if ($estadoPicking === 'facturado')
            <span class="badge badge-success picking-estado-badge">Facturado</span>
        @elseif ($estadoPicking === 'preparado')
            <span class="badge badge-warning picking-estado-badge">Preparado</span>
        @else
            <span class="badge badge-secondary picking-estado-badge">Pendiente</span>
        @endif
    </div>
    <div class="picking-nro small font-weight-bold text-primary mb-1{{ $pickingCodigo > 0 ? '' : ' d-none' }}">
        @if ($pickingCodigo > 0)
            Picking #{{ $pickingCodigo }}
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
    {{-- Bucket del modal: OT (lote=0) si >0; lote importado si vac&iacute;o. --}}
    <input type="hidden" class="picking-ordentrabajo-id" value="{{ $pickingOtId > 0 ? $pickingOtId : '' }}">
    @php
        $depositoAsignadoTxt = '';
        if ($pickingDep > 0) {
            foreach ($depositosPicking as $depAsignado) {
                if ((int) $depAsignado->id === $pickingDep) {
                    $depositoAsignadoTxt = trim(($depAsignado->codigo ?? '').' — '.($depAsignado->nombre ?? ''), ' —');
                    break;
                }
            }
            if ($depositoAsignadoTxt === '') {
                $depositoAsignadoTxt = '#'.$pickingDep;
            }
        }
    @endphp
    {{-- El depósito lo trae el stock al elegir el lote/OT. El select queda oculto y solo guarda el id. --}}
    <select class="form-control form-control-sm picking-deposito d-none"
            title="Dep&oacute;sito del stock del lote/OT"
            @if ($pickingFacturado || $pickingMarcado) disabled @endif>
        <option value="0">Dep&oacute;sito…</option>
        @foreach ($depositosPicking as $dep)
            <option value="{{ $dep->id }}" @if ($pickingDep === (int) $dep->id) selected @endif>
                {{ trim(($dep->codigo ?? '').' — '.($dep->nombre ?? ''), ' —') }}
            </option>
        @endforeach
    </select>
    <small class="d-block text-muted picking-deposito-asignado mb-1{{ $pickingDep > 0 ? '' : ' d-none' }}">
        @if ($pickingDep > 0)
            Dep&oacute;sito: {{ $depositoAsignadoTxt }}
        @endif
    </small>
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
            F1 trae el lote y el dep&oacute;sito del stock
        </small>
    @endif
</div>
