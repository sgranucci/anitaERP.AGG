@include('stock.movimiento_stock_articulo_reporte.partials.tabla_datos', [
    'filas' => $filas ?? [],
    'totales' => $totales ?? [],
    'titulo' => $titulo ?? 'Movimientos de stock',
    'subtitulo' => $subtitulo ?? '',
    'excel' => true,
    'puede_ver_articulo' => false,
])
