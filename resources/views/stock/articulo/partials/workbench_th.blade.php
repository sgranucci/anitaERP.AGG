@php
    $titulos = [
        'sku' => 'SKU',
        'codigobarra' => 'Cód. barra',
        'descripcion' => 'Descripción',
        'unidadmedida' => 'Unidad',
        'categoria' => 'Categoría',
        'tipoarticulo' => 'Tipo',
        'usoarticulo' => 'Uso',
        'canal' => 'Canal',
        'empresa' => 'Empresa',
        'numeroparte' => 'Parte',
        'ubicacionparte' => 'Ubic.',
        'saldo' => 'Saldo',
        'proveedor_art' => 'Proveedor',
        'precio_proveedor' => 'Precio proveedor',
        'nofactura' => 'Fact.',
        'estado' => 'Estado',
        'fecha_alta' => 'Fecha de alta',
        'fecha_modificacion' => 'Fecha de modificación',
        'cuentaventa' => 'Cta. contable venta',
        'cuentacompra' => 'Cta. contable compra',
        'cuentagasto' => 'Cta. contable gasto',
    ];
    $titulo = ($etiquetasColumnas ?? [])[$key] ?? ($titulos[$key] ?? $key);
    $alineacion = in_array($key, ['saldo', 'precio_proveedor'], true) ? 'text-right' : '';
    $titleTh = $key === 'saldo'
        ? 'Saldo en anitaERP del depósito de entrega'
        : $titulo;
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
        <a href="{{ route('articulo', $qsSort) }}" class="lw-sort-link" draggable="false">
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
