@php
    $titulos = [
        'sku' => 'Código',
        'descripcion' => 'Descripción',
        'categoria' => 'Categoría',
        'marca' => 'Marca',
        'linea' => 'Línea',
        'canal' => 'Canal',
        'nofactura' => 'Facturable',
        'estado' => 'Estado',
        'fecha_alta' => 'Fecha de alta',
        'fecha_modificacion' => 'Fecha de modificación',
    ];
    $titulo = ($etiquetasColumnas ?? [])[$key] ?? ($titulos[$key] ?? $key);
    $ordenables = array_keys(\App\Support\Stock\ArticuloFerliListadoFiltros::camposOrdenables());
    $esOrdenable = in_array($key, $ordenables, true) && $key !== 'canal';
    $claseCol = 'lw-th-drag';
    $titleTh = $titulo.' — arrastrá para mover · × para ocultar (con confirmación)';
@endphp
<th class="{{ $claseCol }}" title="{{ $titleTh }}" data-col-key="{{ $key }}" draggable="true">
    <span class="lw-th-drag-handle" title="Arrastrar columna" aria-hidden="true"><i class="fa fa-grip-vertical"></i></span>
    @if ($esOrdenable)
        <a href="{{ $urlOrdenProducto($key) }}" class="text-dark lw-sort-link" draggable="false"><span class="lw-th-label">{{ $titulo }}</span>{{ $marcaOrdenProducto($key) }}</a>
    @else
        <span class="lw-th-titulo">{{ $titulo }}</span>
    @endif
    <button type="button" class="lw-th-hide" title="Ocultar columna (pide confirmación)" aria-label="Ocultar columna" draggable="false">
        <i class="fa fa-times" aria-hidden="true"></i>
    </button>
</th>
