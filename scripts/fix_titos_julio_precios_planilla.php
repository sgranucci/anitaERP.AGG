<?php

declare(strict_types=1);

/**
 * Revalúa TRCONT Titos julio a los promedios pedidos por contaduría (no recalcula):
 *   BSA (emp 1) = 10.02
 *   KSA (emp 2) = 10.30
 *   RSA (emp 3) = 10.02
 *
 * Actualiza: transferencia_mercaderia_articulo, articulo_movimiento, asiento_movimiento, ctamov Anita.
 *
 * Uso:
 *   php scripts/fix_titos_julio_precios_planilla.php
 *   php scripts/fix_titos_julio_precios_planilla.php apply
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contable\Asiento;
use App\Repositories\Contable\AsientoRepository;
use App\Support\Contable\PeriodoContableCierreSupport;
use Illuminate\Support\Facades\DB;

$apply = in_array('apply', $argv ?? [], true);

/** @var array<int, float> empresa_id => precio unitario pedido */
$preciosPorEmpresa = [
    1 => 10.02,  // BSA
    2 => 10.30,  // KSA
    3 => 10.02,  // RSA
];

$titoIds = [2114, 6253, 6254, 6696];
$fmt = static fn (float $v): string => number_format($v, 6, '.', '');
$fmt2 = static fn (float $v): string => number_format($v, 2, ',', '.');

echo '=== Revaluación Titos julio a precios planilla ('.($apply ? 'APLICAR' : 'DRY-RUN').") ===\n";
echo "BSA=10.02 | KSA=10.30 | RSA=10.02\n\n";

$rows = DB::table('transferencia_mercaderia as tm')
    ->join('transferencia_mercaderia_articulo as tma', 'tma.transferencia_mercaderia_id', '=', 'tm.id')
    ->join('articulo as a', 'a.id', '=', 'tma.articulo_origen_id')
    ->leftJoin('tipotransaccion_stock as tt', 'tt.id', '=', 'tm.tipotransaccion_stock_id')
    ->leftJoin('asiento as asi', 'asi.id', '=', 'tm.asiento_id')
    ->whereIn('tma.articulo_origen_id', $titoIds)
    ->whereBetween('tm.fecha', ['2026-07-01', '2026-07-31'])
    ->whereNull('tm.deleted_at')
    ->where('tm.estado', 'CONFIRMADA')
    ->where('tt.maneja_contabilidad', 1)
    ->whereNotNull('tm.asiento_id')
    ->orderBy('tm.empresa_id')
    ->orderBy('tm.id')
    ->get([
        'tm.id', 'tm.codigo', 'tm.fecha', 'tm.empresa_id', 'tm.asiento_id',
        'tm.movimientostock_salida_id', 'tm.movimientostock_entrada_id',
        'asi.numeroasiento', 'asi.empresa_id as asiento_empresa_id',
        'tma.id as linea_id', 'tma.articulo_origen_id', 'a.sku',
        'tma.cantidad_origen', 'tma.precio_costo_origen',
    ]);

$ajustes = [];
foreach ($rows as $r) {
    $emp = (int) $r->empresa_id;
    if (! isset($preciosPorEmpresa[$emp])) {
        echo "TM#{$r->id} emp={$emp} sin precio objetivo — skip\n";
        continue;
    }
    $precioObj = $preciosPorEmpresa[$emp];
    $cant = (float) $r->cantidad_origen;
    $importeObj = round($cant * $precioObj, 2);
    $montoActual = (float) DB::table('asiento_movimiento')
        ->where('asiento_id', $r->asiento_id)
        ->whereNull('deleted_at')
        ->where('monto', '>', 0)
        ->value('monto');

    $ajustes[] = [
        'tm_id' => (int) $r->id,
        'codigo' => (string) $r->codigo,
        'fecha' => (string) $r->fecha,
        'empresa_id' => $emp,
        'asiento_id' => (int) $r->asiento_id,
        'asiento_empresa_id' => (int) $r->asiento_empresa_id,
        'numeroasiento' => (string) $r->numeroasiento,
        'linea_id' => (int) $r->linea_id,
        'articulo_id' => (int) $r->articulo_origen_id,
        'sku' => (string) $r->sku,
        'cantidad' => $cant,
        'precio_actual' => (float) $r->precio_costo_origen,
        'precio_objetivo' => $precioObj,
        'importe_actual' => $montoActual,
        'importe_objetivo' => $importeObj,
        'salida_id' => (int) ($r->movimientostock_salida_id ?? 0),
        'entrada_id' => (int) ($r->movimientostock_entrada_id ?? 0),
    ];

    $label = match ($emp) {
        1 => 'BSA',
        2 => 'KSA',
        3 => 'RSA',
        default => (string) $emp,
    };
    echo sprintf(
        "%s TM#%d %s %s cant=%s p=%s→%s imp=%s→%s asiento#%s nro=%s\n",
        $label,
        $r->id,
        $r->codigo,
        $r->sku,
        $fmt($cant),
        $fmt((float) $r->precio_costo_origen),
        $fmt($precioObj),
        $fmt2($montoActual),
        $fmt2($importeObj),
        $r->asiento_id,
        $r->numeroasiento
    );
}

if ($ajustes === []) {
    fwrite(STDERR, "Sin ajustes.\n");
    exit(1);
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
            DB::table('articulo_movimiento')
                ->where('movimientostock_id', $movId)
                ->where('articulo_id', $aj['articulo_id'])
                ->whereNull('deleted_at')
                ->update([
                    'precio' => $precio,
                    'costo' => $precio,
                    'updated_at' => now(),
                ]);
        }

        $movs = DB::table('asiento_movimiento')
            ->where('asiento_id', $aj['asiento_id'])
            ->whereNull('deleted_at')
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
    // Conservar sistema S / clave TRA del asiento original si el payload del modelo no las trae
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
        ->whereNull('deleted_at')
        ->where('monto', '>', 0)
        ->value('monto');
    $ok = abs($pLin - $aj['precio_objetivo']) < 0.000001 && abs($monto - $aj['importe_objetivo']) < 0.01;
    echo sprintf(
        "TM#%d p=%s imp=%s %s\n",
        $aj['tm_id'],
        $fmt($pLin),
        $fmt2($monto),
        $ok ? 'OK' : 'FALLA'
    );
}

echo "Listo.\n";
