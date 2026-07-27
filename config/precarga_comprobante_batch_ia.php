<?php

/*
|--------------------------------------------------------------------------
| BATCH_IA — carpeta caliente de facturas de proveedor
|--------------------------------------------------------------------------
| PDFs depositados en entrada/ se reclaman atómicamente, pasan a la cola y
| reutilizan ComprobanteProveedorPdfIaService. El PDF final se mueve a la
| estructura canónica de Facturas_scan al confirmar la precarga.
*/

$facturasScan = rtrim((string) env('PRECARGA_FACTURAS_SCAN_BASE', '/Facturas_scan'), '/');

return [
    'habilitada' => filter_var(env('PRECARGA_BATCH_IA_HABILITADA', false), FILTER_VALIDATE_BOOLEAN),
    'base_path' => rtrim((string) env('PRECARGA_BATCH_IA_BASE_PATH', $facturasScan.'/entrada_ia'), '/'),
    'entrada' => env('PRECARGA_BATCH_IA_CARPETA_ENTRADA', 'entrada'),
    'procesando' => env('PRECARGA_BATCH_IA_CARPETA_PROCESANDO', 'procesando'),
    'errores' => env('PRECARGA_BATCH_IA_CARPETA_ERRORES', 'errores'),
    'archivo' => env('PRECARGA_BATCH_IA_CARPETA_ARCHIVO', 'archivo'),
    'max_archivos' => max(1, (int) env('PRECARGA_BATCH_IA_MAX_ARCHIVOS', 20)),
    'intervalo_minutos' => max(1, (int) env('PRECARGA_BATCH_IA_INTERVALO_MIN', 5)),
    // Evita reclamar PDFs que el scanner todavía está escribiendo.
    'estabilidad_segundos' => max(0, (int) env('PRECARGA_BATCH_IA_ESTABILIDAD_SEG', 3)),
    'cola' => env('PRECARGA_BATCH_IA_COLA', 'default'),
];
