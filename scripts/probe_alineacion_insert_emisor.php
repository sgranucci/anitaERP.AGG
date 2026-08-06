<?php

/**
 * Verifica que el INSERT de subdiario de la alineación ARCA lleve emisor y descripción,
 * reproduciendo el escenario de FAC A-10-254 (percepción de IVA faltante). Solo lectura.
 */

declare(strict_types=1);

use App\Services\Ventas\AlinearFacturaAnitaArcaService;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$servicio = app(AlinearFacturaAnitaArcaService::class);
$metodo = new ReflectionMethod($servicio, 'planSubdiario');
$metodo->setAccessible(true);

// Subdiario de FAC A-10-254 antes de la alineación: sin línea de percepción de IVA (211290000).
$sub = [
    ['subd_cuenta' => '211170000', 'subd_contrapartida' => '113100000', 'subd_importe' => '13065.09', 'subd_tipo_mov' => 'H', 'subd_fecha' => '20260804', 'subd_emisor' => '006676', 'subd_desc_mov' => 'FAC A10-254 TUPAC EN EXPANSION'],
    ['subd_cuenta' => '211271000', 'subd_contrapartida' => '113100000', 'subd_importe' => '2177.52', 'subd_tipo_mov' => 'H', 'subd_fecha' => '20260804', 'subd_emisor' => '006676', 'subd_desc_mov' => 'FAC A10-254 TUPAC EN EXPANSION'],
    ['subd_cuenta' => '301100000', 'subd_contrapartida' => '113100000', 'subd_importe' => '62214.72', 'subd_tipo_mov' => 'H', 'subd_fecha' => '20260804', 'subd_emisor' => '006676', 'subd_desc_mov' => 'FAC A10-254 TUPAC EN EXPANSION'],
];

$porCuenta = [
    '211170000' => 26130.18,
    '211290000' => 3732.88, // la que hay que insertar
    '211271000' => 4355.03,
];

$plan = $metodo->invoke(
    $servicio,
    'FAC', 'A', 10, 254,
    $sub,
    $porCuenta,
    124429.44,
    ['ven_cliente' => '006676', 'ven_fecha' => '20260804', 'ven_logistica' => 0],
    [],
);

$inserts = 0;
foreach ($plan as $p) {
    if (($p['acc'] ?? '') !== 'insert') {
        continue;
    }
    $inserts++;
    echo "descripcion: ", $p['descripcion'], "\n";
    echo "campos     : ", $p['campos'], "\n";
    echo "valores    : ", $p['valores'], "\n\n";

    $campos = explode(',', $p['campos']);
    $tieneEmisor = in_array('subd_emisor', $campos, true);
    $tieneDesc = in_array('subd_desc_mov', $campos, true);
    $emisorEnValores = str_contains($p['valores'], "'006676'");

    echo '  subd_emisor en campos      : ', $tieneEmisor ? 'SI' : 'NO', "\n";
    echo '  subd_desc_mov en campos    : ', $tieneDesc ? 'SI' : 'NO', "\n";
    echo '  cliente 006676 en valores  : ', $emisorEnValores ? 'SI' : 'NO', "\n";
    echo '  cantidad campos = valores  : ',
        count($campos) === count(explode(',', $p['valores'])) ? 'SI' : 'NO', "\n";
}

echo "\ninserts planificados: {$inserts}\n";
echo "\n--- resto del plan ---\n";
foreach ($plan as $p) {
    if (($p['acc'] ?? '') === 'insert') {
        continue;
    }
    echo '  [', $p['acc'] ?? '', '] ', $p['descripcion'] ?? '', "\n";
}
