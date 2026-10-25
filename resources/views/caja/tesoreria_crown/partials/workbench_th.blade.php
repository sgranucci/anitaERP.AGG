@php
    $titulo = ($etiquetasColumnas ?? [])[$key] ?? $key;
    $camposOrden = $camposOrdenables ?? [];
    $ordenActual = $filtros['sort'] ?? [];
    $esOrdenable = isset($camposOrden[$key]);
    $dirCol = $esOrdenable
        ? \App\Support\Listado\ListadoOrdenamientoSupport::direccionDeCampo($ordenActual, $key)
        : null;
    $idxCol = $esOrdenable
        ? \App\Support\Listado\ListadoOrdenamientoSupport::indiceDeCampo($ordenActual, $key)
        : null;
    $claseCol = $esOrdenable ? 'lw-col-sortable'.($dirCol ? ' lw-col-sorted' : '') : '';
    if ($esOrdenable) {
        $qsSort = $filtrosQuery ?? [];
        unset($qsSort['sort']);
        $qsSort = array_merge(
            $qsSort,
            \App\Support\Listado\ListadoOrdenamientoSupport::paraQueryString(
                \App\Support\Listado\ListadoOrdenamientoSupport::togglePrimario($ordenActual, $key, $camposOrden)
            )
        );
    }
@endphp
<th class="{{ $claseCol }}" @if ($esOrdenable) title="{{ $titulo }} — clic para ordenar" @endif>
    @if ($esOrdenable)
        <a href="{{ route($rutaListado, $qsSort) }}" class="lw-sort-link">
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
