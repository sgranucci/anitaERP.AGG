{{-- Encabezado de una columna del workbench de importaciones (orden por clic, ancho y alineación del diseñador). --}}
@php
    use App\Support\Listado\ListadoGrillaConfigSupport as LwGrilla;
    use App\Support\Listado\ListadoOrdenamientoSupport;

    $cfg = $layoutPorKey[$key] ?? null;
    $cfgPantalla = $layoutPantallaPorKey[$key] ?? $cfg;
    $alinea = $cfg['alinea'] ?? 'izquierda';
    $ancho = (int) ($cfgPantalla['ancho'] ?? 100);
    $cls = LwGrilla::claseAlineacion($alinea);
    $tituloCol = $etiquetasColumnas[$key] ?? ($catalogoColumnas[$key]['label'] ?? $key);
    $minLegible = LwGrilla::anchoMinimoLegible($key, $catalogoColumnas[$key] ?? [], $tituloCol);
    if ($usaScrollHorizontal) {
        $styleCol = 'width:'.$ancho.'px;min-width:'.$minLegible.'px;';
    } else {
        $pct = round(($ancho / $sumaAnchosPantalla) * $pctDatosDisponible, 2);
        $styleCol = 'width:'.$pct.'%;min-width:'.$minLegible.'px;';
    }
    $esOrdenable = isset($camposOrdenablesThead[$key]);
    $dirCol = ListadoOrdenamientoSupport::direccionDeCampo($ordenActualThead, $key);
    $idxCol = ListadoOrdenamientoSupport::indiceDeCampo($ordenActualThead, $key);
    $qsSort = $filtrosQuery ?? [];
    unset($qsSort['sort']);
    if ($esOrdenable) {
        $ordenToggle = ListadoOrdenamientoSupport::togglePrimario($ordenActualThead, $key, $camposOrdenablesThead);
        $qsSort = array_merge($qsSort, ListadoOrdenamientoSupport::paraQueryString($ordenToggle));
    }
@endphp
<th class="{{ $cls }} lw-col {{ $esOrdenable ? 'lw-col-sortable' : '' }} {{ $dirCol ? 'lw-col-sorted' : '' }}"
    style="{{ $styleCol }}" title="{{ $tituloCol }}{{ $esOrdenable ? ' — clic para ordenar' : '' }}">
    @if ($esOrdenable)
        <a href="{{ route('importacion', $qsSort) }}" class="lw-sort-link">
            <span class="lw-th-label">{{ $tituloCol }}</span>
            @if ($dirCol === 'asc')
                <i class="fa fa-sort-up lw-sort-icon"></i>
            @elseif ($dirCol === 'desc')
                <i class="fa fa-sort-down lw-sort-icon"></i>
            @else
                <i class="fa fa-sort lw-sort-icon lw-sort-muted"></i>
            @endif
            @if ($idxCol !== null && count($ordenActualThead) > 1)
                <sup class="lw-sort-prio">{{ $idxCol + 1 }}</sup>
            @endif
        </a>
    @else
        {{ $tituloCol }}
    @endif
</th>
