@php
    $titulo = ($etiquetasColumnas ?? [])[$key] ?? $key;
    $alineaCfg = is_array($cfg ?? null) ? (string) ($cfg['alinea'] ?? '') : '';
    $alineacion = match ($alineaCfg !== '' ? $alineaCfg : $key) {
        'derecha', 'monto' => 'text-right',
        'centro' => 'text-center',
        'izquierda' => 'text-left',
        default => '',
    };
    $titleTh = $titulo;
    $camposOrden = $camposOrdenablesThead ?? [];
    $ordenActual = $ordenActualThead ?? [];
    $esOrdenable = isset($camposOrden[$key]);
    $dirCol = $esOrdenable
        ? \App\Support\Listado\ListadoOrdenamientoSupport::direccionDeCampo($ordenActual, $key)
        : null;
    $idxCol = $esOrdenable
        ? \App\Support\Listado\ListadoOrdenamientoSupport::indiceDeCampo($ordenActual, $key)
        : null;
    $claseCol = trim($alineacion.' lw-col lw-th-drag');
    $titleTh .= ' — arrastrá para mover · × para ocultar (con confirmación)';
    if ($esOrdenable) {
        $claseCol = trim($claseCol.' lw-col-sortable'.($dirCol ? ' lw-col-sorted' : ''));
        $titleTh .= ' · clic para ordenar filas';
        $qsSort = $filtrosQuery ?? [];
        unset($qsSort['sort']);
        $qsSort = array_merge(
            $qsSort,
            \App\Support\Listado\ListadoOrdenamientoSupport::paraQueryString(
                \App\Support\Listado\ListadoOrdenamientoSupport::togglePrimario($ordenActual, $key, $camposOrden)
            )
        );
    }
    $styleAttr = trim((string) ($styleCol ?? ''));
@endphp
<th class="{{ $claseCol }}"
    @if ($styleAttr !== '') style="{{ $styleAttr }}" @endif
    title="{{ $titleTh }}"
    data-col-key="{{ $key }}"
    draggable="true">
    <span class="lw-th-drag-handle" title="Arrastrar columna" aria-hidden="true"><i class="fa fa-grip-vertical"></i></span>
    @if ($esOrdenable)
        <a href="{{ route('ingresoegreso', $qsSort) }}" class="lw-sort-link" draggable="false">
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
