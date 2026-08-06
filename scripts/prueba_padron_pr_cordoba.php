<?php

/**
 * Prueba manual del motor de padrones con el formato P/R de Córdoba.
 *
 * Corre dentro de una transacción que siempre se revierte: no deja datos en la
 * base. Usa un archivo sintético porque el padrón real de Córdoba no está en el
 * servidor.
 *
 * Uso: php scripts/prueba_padron_pr_cordoba.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Configuracion\PadronIibbTasaCargaService;
use App\Support\Configuracion\PadronIibb\PadronIibbCordobaParser;
use Illuminate\Support\Facades\DB;

$provinciaId = (int) DB::table('provincia')->where('jurisdiccion', '904')->value('id');
$archivo = sys_get_temp_dir() . '/padron_cordoba_prueba.csv';

// Los CUIT 1 y 2 traen percepción y retención; el 3 solo percepción; el 4 solo
// retención (la segunda pasada tiene que insertar esa fila). Las líneas R van
// primero a propósito, para comprobar que el orden dentro del archivo no importa.
file_put_contents($archivo, implode("\n", [
    'R;x;01082026;31082026;30111111118;CM;a;b;2,50',
    'R;x;01082026;31082026;30222222226;CD;a;b;1,25',
    'R;x;01082026;31082026;30444444442;CM;a;b;0,75',
    'P;x;01082026;31082026;30111111118;CM;a;b;3,00',
    'P;x;01082026;31082026;30222222226;CD;a;b;1,50',
    'P;x;01082026;31082026;30333333334;CM;a;b;4,25',
    'basura que se debe omitir',
    'P;x;01082026;31082026;31122025;CM;a;b;9,99',
]) . "\n");

$service = app(PadronIibbTasaCargaService::class);

DB::beginTransaction();

try {
    $stats = $service->cargar($archivo, $provinciaId, new PadronIibbCordobaParser, 2, 0);

    echo "== stats ==\n";
    foreach (['leidas', 'omitidas', 'insertadas_tasa', 'actualizadas_tasa', 'insertadas_cuit', 'errores', 'borrados'] as $clave) {
        printf("  %-20s %s\n", $clave, $stats[$clave]);
    }
    printf("  %-20s %s a %s\n", 'periodo', $stats['desdefecha'], $stats['hastafecha']);

    $filas = DB::table('padron_iibb_tasa as t')
        ->join('padron_iibb as p', 'p.id', '=', 't.padron_iibb_id')
        ->where('t.provincia_id', $provinciaId)
        ->where('t.desdefecha', '2026-08-01')
        ->select('p.cuit', 't.tasapercepcion', 't.tasaretencion', 't.tipocontribuyente')
        ->orderBy('p.cuit')
        ->get();

    echo "\n== filas resultantes ==\n";
    foreach ($filas as $fila) {
        echo '  ', json_encode($fila), "\n";
    }

    $esperado = [
        '30111111118' => [3.0, 2.5],
        '30222222226' => [1.5, 1.25],
        '30333333334' => [4.25, null],
        '30444444442' => [null, 0.75],
    ];
    $obtenido = [];
    foreach ($filas as $fila) {
        $obtenido[$fila->cuit] = [
            $fila->tasapercepcion !== null ? (float) $fila->tasapercepcion : null,
            $fila->tasaretencion !== null ? (float) $fila->tasaretencion : null,
        ];
    }
    ksort($obtenido);

    echo "\n== control ==\n";
    echo $obtenido === $esperado
        ? "  OK: percepciones y retenciones combinadas correctamente\n"
        : '  FALLA: esperado ' . json_encode($esperado) . ' / obtenido ' . json_encode($obtenido) . "\n";

    // El CUIT basura ya existe en padron_iibb por la carga corrupta de diciembre;
    // lo que se verifica es que esta importación no le haya creado una tasa.
    $tasasBasura = DB::table('padron_iibb_tasa as t')
        ->join('padron_iibb as p', 'p.id', '=', 't.padron_iibb_id')
        ->where('p.cuit', '31122025')
        ->where('t.provincia_id', $provinciaId)
        ->where('t.desdefecha', '2026-08-01')
        ->count();

    echo $tasasBasura === 0
        ? "  OK: la linea con CUIT invalido de 8 digitos fue descartada\n"
        : "  FALLA: se cargo la linea con CUIT invalido\n";
} finally {
    DB::rollBack();
    @unlink($archivo);
    echo "\n(transaccion revertida: no quedaron datos)\n";
}
