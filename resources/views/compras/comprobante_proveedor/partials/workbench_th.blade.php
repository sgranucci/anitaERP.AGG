@php
    $titulo = ($etiquetasColumnas ?? [])[$key] ?? $key;
    $alineacion = ($key === 'total' || $key === 'cotizacion') ? 'text-right' : '';
    if ($key === 'fuera_pago') {
        $alineacion = 'text-center';
    }
    $claseCol = '';
    $titleTh = $titulo;
    if ($key === 'fechaiva') {
        $titulo = 'F. IVA';
        $claseCol = 'cp-col-fechaiva';
        $titleTh = 'Fecha de contabilización e IVA compras';
    } elseif ($key === 'numero') {
        $claseCol = 'cp-col-numero';
        $titleTh = 'Número de comprobante';
    } elseif ($key === 'fechacomprobante') {
        $titulo = 'Fecha';
        $claseCol = 'cp-col-fecha';
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
    if ($esOrdenable) {
        $claseCol = trim($claseCol.' lw-col-sortable'.($dirCol ? ' lw-col-sorted' : ''));
        $titleTh .= ' — clic para ordenar';
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
<th class="{{ $alineacion }} {{ $claseCol }}" title="{{ $titleTh }}">
    @if ($esOrdenable)
        <a href="{{ route('comprobante_proveedor', $qsSort) }}" class="lw-sort-link">
            {{ $titulo }}
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
        {{ $titulo }}
    @endif
</th>
