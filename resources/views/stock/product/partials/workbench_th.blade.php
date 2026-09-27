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
@endphp
<th>
    @if (in_array($key, $ordenables, true) && $key !== 'canal')
        <a href="{{ $urlOrdenProducto($key) }}" class="text-dark">{{ $titulo }}{{ $marcaOrdenProducto($key) }}</a>
    @else
        {{ $titulo }}
    @endif
</th>
