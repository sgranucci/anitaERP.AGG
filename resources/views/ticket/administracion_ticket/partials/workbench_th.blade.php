@php
    use App\Support\Listado\ListadoGrillaConfigSupport as LwGrilla;

    $titulo = ($etiquetasColumnas ?? [])[$key] ?? (($catalogoColumnas ?? [])[$key]['label'] ?? $key);
    $cfg = ($layoutPorKey ?? [])[$key] ?? null;
    $cfgPantalla = ($layoutPantallaPorKey ?? [])[$key] ?? $cfg;
    $alinea = is_array($cfg) ? ($cfg['alinea'] ?? 'izquierda') : 'izquierda';
    $ancho = (int) (is_array($cfgPantalla) ? ($cfgPantalla['ancho'] ?? 100) : 100);
    $minLegible = LwGrilla::anchoMinimoLegible(
        $key,
        ($catalogoColumnas ?? [])[$key] ?? [],
        $titulo
    );
    $usaScroll = (bool) ($usaScrollHorizontal ?? false);
    $sumaAnchos = max(1, (int) ($sumaAnchosPantalla ?? 1));
    $pctDatos = (float) ($pctDatosDisponible ?? 100);
    if ($usaScroll) {
        $styleCol = 'width:'.$ancho.'px;min-width:'.$minLegible.'px;';
    } else {
        $pct = round(($ancho / $sumaAnchos) * $pctDatos, 2);
        $styleCol = 'width:'.$pct.'%;min-width:'.$minLegible.'px;';
    }
    $cls = trim(LwGrilla::claseAlineacion($alinea).' lw-col lw-th-drag');
    $camposOrden = $camposOrdenablesThead ?? [];
    $ordenActual = $ordenActualThead ?? [];
    $esOrdenable = isset($camposOrden[$key]);
    $dirCol = $esOrdenable
        ? \App\Support\Listado\ListadoOrdenamientoSupport::direccionDeCampo($ordenActual, $key)
        : null;
    $idxCol = $esOrdenable
        ? \App\Support\Listado\ListadoOrdenamientoSupport::indiceDeCampo($ordenActual, $key)
        : null;
    $titleTh = $titulo.' — arrastrá para mover · × para ocultar (con confirmación)';
    if ($esOrdenable) {
        $cls = trim($cls.' lw-col-sortable'.($dirCol ? ' lw-col-sorted' : ''));
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
@endphp
<th class="{{ $cls }}"
    style="{{ $styleCol }}"
    title="{{ $titleTh }}"
    data-col-key="{{ $key }}"
    draggable="true">
    <span class="lw-th-drag-handle" title="Arrastrar columna" aria-hidden="true"><i class="fa fa-grip-vertical"></i></span>
    @if ($esOrdenable)
        <a href="{{ route('consulta_administracion_ticket', $qsSort) }}" class="lw-sort-link" draggable="false">
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
