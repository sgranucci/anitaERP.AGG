@php
    $titulo = ($etiquetasColumnas ?? [])[$key] ?? ($titulo ?? $key);
    $alineacion = match ($key) {
        'monto' => 'text-right',
        'mail' => 'text-center',
        default => '',
    };
    $titleTh = $key === 'mail' ? 'Correo enviado al proveedor' : $titulo;
    $camposOrden = $camposOrdenablesThead ?? [];
    $ordenActual = $ordenActualThead ?? [];
    $esOrdenable = isset($camposOrden[$key]);
    $dirCol = $esOrdenable
        ? \App\Support\Listado\ListadoOrdenamientoSupport::direccionDeCampo($ordenActual, $key)
        : null;
    $idxCol = $esOrdenable
        ? \App\Support\Listado\ListadoOrdenamientoSupport::indiceDeCampo($ordenActual, $key)
        : null;
    $claseCol = $alineacion;
    if ($esOrdenable) {
        $claseCol = trim($claseCol.' lw-col-sortable'.($dirCol ? ' lw-col-sorted' : ''));
        $titleTh .= ' — clic para ordenar';
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
<th class="{{ $claseCol }}" title="{{ $titleTh }}" @if ($key === 'mail') style="width:36px" @endif>
    @if ($esOrdenable)
        <a href="{{ route('pagoproveedor', $qsSort) }}" class="lw-sort-link">
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
    @elseif ($key === 'mail')
        <i class="fa fa-envelope" style="opacity:.45"></i>
    @else
        {{ $titulo }}
    @endif
</th>
