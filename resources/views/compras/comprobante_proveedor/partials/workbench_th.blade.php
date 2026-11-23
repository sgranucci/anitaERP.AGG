@php
    $titulo = ($etiquetasColumnas ?? [])[$key] ?? $key;
    $alineacion = ($key === 'total' || $key === 'cotizacion') ? 'text-right' : '';
    if ($key === 'fuera_pago') {
        $alineacion = 'text-center';
    }
    $claseCol = 'lw-th-drag';
    $titleTh = $titulo;
    if ($key === 'fechaiva') {
        $titulo = 'F. IVA';
        $claseCol .= ' cp-col-fechaiva';
        $titleTh = 'Fecha de contabilización e IVA compras';
    } elseif ($key === 'numero') {
        $claseCol .= ' cp-col-numero';
        $titleTh = 'Número de comprobante';
    } elseif ($key === 'fechacomprobante') {
        $titulo = 'Fecha';
        $claseCol .= ' cp-col-fecha';
        $titleTh = 'Fecha del comprobante';
    }
    $camposOrden = $camposOrdenablesThead ?? [];
    $ordenActual = $ordenActualThead ?? [];
    $campoSort = $key === 'numero' && isset($camposOrden['numerocomprobante']) ? 'numerocomprobante' : $key;
    $esOrdenable = isset($camposOrden[$campoSort]);
    $dirCol = $esOrdenable
        ? \App\Support\Listado\ListadoOrdenamientoSupport::direccionDeCampo($ordenActual, $campoSort)
        : null;
    $idxCol = $esOrdenable
        ? \App\Support\Listado\ListadoOrdenamientoSupport::indiceDeCampo($ordenActual, $campoSort)
        : null;
    $titleTh .= ' — arrastrá para mover · × para ocultar (con confirmación)';
    if ($esOrdenable) {
        $claseCol = trim($claseCol.' lw-col-sortable'.($dirCol ? ' lw-col-sorted' : ''));
        $titleTh .= ' · clic para ordenar filas';
        $qsSort = $filtrosQuery ?? [];
        unset($qsSort['sort']);
        $qsSort = array_merge(
            $qsSort,
            \App\Support\Listado\ListadoOrdenamientoSupport::paraQueryString(
                \App\Support\Listado\ListadoOrdenamientoSupport::togglePrimario($ordenActual, $campoSort, $camposOrden)
            )
        );
    }
@endphp
<th class="{{ $alineacion }} {{ $claseCol }}" title="{{ $titleTh }}" data-col-key="{{ $key }}" draggable="true">
    <span class="lw-th-drag-handle" title="Arrastrar columna" aria-hidden="true"><i class="fa fa-grip-vertical"></i></span>
    @if ($esOrdenable)
        <a href="{{ route('comprobante_proveedor', $qsSort) }}" class="lw-sort-link" draggable="false">
            <span class="lw-th-label">{{ $titulo }}</span>
            @if ($dirCol === 'asc')
                <i class="fa fa-sort-up lw-sort-icon"></i>
            @elseif ($dirCol === 'desc')
                <i class="fa fa-sort-down lw-sort-icon"></i>
            @else
                <i class="fa fa-sort lw-sort-icon lw-sort-muted"></i>
            @endif
            @if ($idxCol !== null && count($ordenActual) > 1)
                <sup class="lw-sort-prio">{{ $idxCol + 1 }}</sup>
            @endif
        </a>
    @else
        <span class="lw-th-titulo">{{ $titulo }}</span>
    @endif
    <button type="button" class="lw-th-hide" title="Ocultar columna (pide confirmación)" aria-label="Ocultar columna" draggable="false">
        <i class="fa fa-times" aria-hidden="true"></i>
    </button>
</th>
