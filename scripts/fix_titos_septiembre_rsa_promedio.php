<?php

declare(strict_types=1);

/**
 * Revalúa las TRCONT Titos Rebisco de septiembre que salieron a 6.9414
 * porque el promedio incluía COM devueltas (166530 y 166953).
 *
 * Precio pedido: 9.983433 = (10.2603 + 9.728 + 9.962) / 3
 *   COM 166615, 159766 y 159351.
 *
 * Uso:
 *   php scripts/fix_titos_septiembre_rsa_promedio.php
 *   php scripts/fix_titos_septiembre_rsa_promedio.php apply
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contable\Asiento;
use App\Repositories\Contable\AsientoRepository;
use App\Support\Contable\PeriodoContableCierreSupport;
use Illuminate\Support\Facades\DB;

$apply = in_array('apply', $argv ?? [], true);

/** @var array<int, float> tm.id => precio unitario */
$preciosPorTm = [
    935 => 9.983433,  // 02-09  200.000
    1140 => 9.983433, // 15-09  200.000  TR-00001139
    1155 => 9.983433, // 15-09    9.000
    1388 => 9.983433, // 25-09  200.000
];

$fmt = static fn (float $v): string => number_format($v, 6, '.', '');
$fmt2 = static fn (float $v): string => number_format($v, 2, ',', '.');

echo '=== Revaluación Titos RSA septiembre a 9.983433 ('.($apply ? 'APLICAR' : 'DRY-RUN').") ===\n\n";

$rows = DB::table('transferencia_mercaderia as tm')
    ->join('transferencia_mercaderia_articulo as tma', 'tma.transferencia_mercaderia_id', '=', 'tm.id')
    ->join('articulo as a', 'a.id', '=', 'tma.articulo_origen_id')
    ->leftJoin('tipotransaccion_stock as tt', 'tt.id', '=', 'tm.tipotransaccion_stock_id')
    ->leftJoin('asiento as asi', 'asi.id', '=', 'tm.asiento_id')
    ->whereIn('tm.id', array_keys($preciosPorTm))
    ->where('tm.estado', 'CONFIRMADA')
    ->where('tm.empresa_id', 3)
    ->where('tt.maneja_contabilidad', 1)
    ->whereNotNull('tm.asiento_id')
    ->orderBy('tm.id')
    ->get([
        'tm.id', 'tm.codigo', 'tm.fecha', 'tm.empresa_id', 'tm.asiento_id',
        'tm.movimientostock_salida_id', 'tm.movimientostock_entrada_id',
        'asi.numeroasiento', 'asi.empresa_id as asiento_empresa_id',
        'tma.id as linea_id', 'tma.articulo_origen_id', 'a.sku',
        'tma.cantidad_origen', 'tma.precio_costo_origen',
    ]);

if ($rows->count() !== count($preciosPorTm)) {
    fwrite(STDERR, 'Se esperaban '.count($preciosPorTm).' TRA y vinieron '.$rows->count().".\n");
    exit(1);
}

$ajustes = [];
foreach ($rows as $r) {
    $tmId = (int) $r->id;
    $fecha = substr((string) $r->fecha, 0, 10);
    if ($fecha < '2026-09-01' || $fecha > '2026-09-30') {
        throw new RuntimeException('Guarda: TM#'.$tmId.' fecha '.$fecha);
    }
    if ((string) $r->sku !== '201266') {
        throw new RuntimeException('Guarda: TM#'.$tmId.' sku '.$r->sku);
    }
    $precioActual = (float) $r->precio_costo_origen;
    if (abs($precioActual - 6.9414) > 0.000001) {
        throw new RuntimeException('Guarda: TM#'.$tmId.' precio actual '.$precioActual.' (se esperaba 6.9414)');
    }

    $precioObj = $preciosPorTm[$tmId];
    $cant = (float) $r->cantidad_origen;
    $importeObj = round($cant * $precioObj, 2);
    $montoActual = (float) DB::table('asiento_movimiento')
        ->where('asiento_id', $r->asiento_id)
        ->where('monto', '>', 0)
        ->value('monto');

    $ajustes[] = [
        'tm_id' => $tmId,
        'codigo' => (string) $r->codigo,
        'fecha' => $fecha,
        'empresa_id' => (int) $r->empresa_id,
        'asiento_id' => (int) $r->asiento_id,
        'asiento_empresa_id' => (int) $r->asiento_empresa_id,
        'numeroasiento' => (string) $r->numeroasiento,
        'linea_id' => (int) $r->linea_id,
        'articulo_id' => (int) $r->articulo_origen_id,
        'sku' => (string) $r->sku,
        'cantidad' => $cant,
        'precio_actual' => $precioActual,
        'precio_objetivo' => $precioObj,
        'importe_actual' => $montoActual,
        'importe_objetivo' => $importeObj,
        'salida_id' => (int) ($r->movimientostock_salida_id ?? 0),
        'entrada_id' => (int) ($r->movimientostock_entrada_id ?? 0),
    ];

    echo sprintf(
        "RSA TM#%d %s %s cant=%s p=%s→%s imp=%s→%s asiento#%s nro=%s\n",
        $tmId,
        $r->codigo,
        $fecha,
        $fmt($cant),
        $fmt($precioActual),
        $fmt($precioObj),
        $fmt2($montoActual),
        $fmt2($importeObj),
        $r->asiento_id,
        $r->numeroasiento
    );
}

if (! $apply) {
    echo "\nDRY-RUN: no se persistió nada. Ejecutar con 'apply' para grabar ERP + ctamov.\n";
    exit(0);
}

echo "\n=== APLICANDO ===\n";
$repo = app(AsientoRepository::class);

DB::transaction(function () use ($ajustes) {
    foreach ($ajustes as $aj) {
        $precio = $aj['precio_objetivo'];
        $importe = $aj['importe_objetivo'];

        DB::table('transferencia_mercaderia_articulo')->where('id', $aj['linea_id'])->update([
            'precio_costo_origen' => $precio,
            'precio_costo_destino' => $precio,
            'updated_at' => now(),
        ]);

        foreach (['salida_id', 'entrada_id'] as $key) {
            $movId = $aj[$key];
            if ($movId <= 0) {
                continue;
            }
            $n = DB::table('articulo_movimiento')
                ->where('movimientostock_id', $movId)
                ->where('articulo_id', $aj['articulo_id'])
                ->update([
                    'precio' => $precio,
                    'costo' => $precio,
                    'updated_at' => now(),
                ]);
            if ($n !== 1) {
                throw new RuntimeException('TM#'.$aj['tm_id'].' movimiento '.$key.' actualizó '.$n.' filas.');
            }
        }

        $movs = DB::table('asiento_movimiento')
            ->where('asiento_id', $aj['asiento_id'])
            ->orderBy('id')
            ->get(['id', 'monto']);

        if ($movs->count() !== 2) {
            throw new RuntimeException('Asiento #'.$aj['asiento_id'].' no tiene exactamente 2 líneas (tiene '.$movs->count().').');
        }

        foreach ($movs as $mov) {
            $nuevo = ((float) $mov->monto) >= 0 ? $importe : -1 * $importe;
            DB::table('asiento_movimiento')->where('id', $mov->id)->update([
                'monto' => $nuevo,
                'updated_at' => now(),
            ]);
        }

        echo "ERP TM#{$aj['tm_id']}: p={$precio} importe={$importe}\n";
    }
});

echo "\n=== Sync ctamov Anita ===\n";
foreach ($ajustes as $aj) {
    $asiento = Asiento::with(['asiento_movimientos.monedas'])->findOrFail($aj['asiento_id']);
    $payload = $repo->armarPayloadAnitaDesdeModelo($asiento);
    $payload['omitir_validacion'] = true;
    $payload['alcance_cierre_contable'] = PeriodoContableCierreSupport::ALCANCE_TRANSFERENCIA;
    if (empty($payload['sistema_ctav'])) {
        $payload['sistema_ctav'] = 'S';
    }
    if (empty($payload['tipo'])) {
        $payload['tipo'] = 'TRA';
        $payload['letra'] = ' ';
        $payload['sucursal'] = 0;
        if (preg_match('/(\d{6,})$/', $aj['codigo'], $m)) {
            $payload['nro'] = (int) substr($m[1], -8);
        }
    }

    $repo->sincronizarCtamovAnita($payload);
    echo "ctamov emp={$aj['asiento_empresa_id']} nro={$aj['numeroasiento']} OK (importe {$aj['importe_objetivo']})\n";
}

echo "\n=== Verificación ERP ===\n";
foreach ($ajustes as $aj) {
    $pLin = (float) DB::table('transferencia_mercaderia_articulo')->where('id', $aj['linea_id'])->value('precio_costo_origen');
    $monto = (float) DB::table('asiento_movimiento')
        ->where('asiento_id', $aj['asiento_id'])
        ->where('monto', '>', 0)
        ->value('monto');
    $pStock = (float) DB::table('articulo_movimiento')
        ->where('movimientostock_id', $aj['salida_id'])
        ->where('articulo_id', $aj['articulo_id'])
        ->value('costo');
    $ok = abs($pLin - $aj['precio_objetivo']) < 0.000001
        && abs($monto - $aj['importe_objetivo']) < 0.01
        && abs($pStock - $aj['precio_objetivo']) < 0.000001;
    echo sprintf("TM#%d p=%s stock=%s imp=%s %s\n", $aj['tm_id'], $fmt($pLin), $fmt($pStock), $fmt2($monto), $ok ? 'OK' : 'FALLA');
    if (! $ok) {
        exit(1);
    }
}

echo "Listo.\n";
