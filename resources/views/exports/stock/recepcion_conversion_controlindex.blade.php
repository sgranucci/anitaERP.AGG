@include('stock.recepcion_conversion_control.partials.tabla_datos', [
    'filas' => $filas ?? [],
    'totales' => $totales ?? [],
    'titulo' => $titulo ?? 'Control conversión recepción',
    'subtitulo' => $subtitulo ?? '',
    'reservarFilaLogoExcel' => $reservarFilaLogoExcel ?? false,
    'excel' => true,
    'puede_ver_recepcion' => false,
    'puede_ver_articulo' => false,
    'puede_ver_proveedor' => false,
])
