<?php

declare(strict_types=1);

/**
 * Corrección producción TRCONT Titos julio 2026:
 * 1) Costos de línea/movimiento = precio unitario del asiento (promedio 3 compras ya impactado).
 * 2) TM#28: asiento + ctamov de Biyemas → Kandiko (empresa destino del depósito).
 *
 * Uso:
 *   php scripts/fix_titos_julio_tm28_empresa_costos.php           # dry-run
 *   php scripts/fix_titos_julio_tm28_empresa_costos.php apply     # aplica ERP + ctamov
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Contable\Asiento;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Repositories\Contable\AsientoRepository;
use Illuminate\Support\Facades\DB;

$apply = in_array('apply', $argv ?? [], true);
$fmt = static fn (float $v): string => number_format($v, 6, '.', '');

$titoIds = [2114, 6253, 6254, 6696];
$tmEmpresaFixId = 28;
$empresaBiyemas = 1;
$empresaKandiko = 2;

echo '=== Fix Titos julio ('.($apply ? 'APLICAR' : 'DRY-RUN').") ===\n\n";

// --- 1) Inventario costos ---
$tms = DB::table('transferencia_mercaderia as tm')
    ->join('transferencia_mercaderia_articulo as tma', 'tma.transferencia_mercaderia_id', '=', 'tm.id')
    ->join('articulo as a', 'a.id', '=', 'tma.articulo_origen_id')
    ->leftJoin('tipotransaccion_stock as tt', 'tt.id', '=', 'tm.tipotransaccion_stock_id')
    ->whereIn('tma.articulo_origen_id', $titoIds)
    ->whereBetween('tm.fecha', ['2026-07-01', '2026-07-31'])
    ->whereNull('tm.deleted_at')
    ->where('tm.estado', 'CONFIRMADA')
    ->where('tt.maneja_contabilidad', 1)
    ->whereNotNull('tm.asiento_id')
    ->orderBy('tm.id')
    ->get([
        'tm.id', 'tm.codigo', 'tm.fecha', 'tm.empresa_id', 'tm.asiento_id',
        'tm.movimientostock_salida_id', 'tm.movimientostock_entrada_id',
        'tma.id as linea_id', 'tma.articulo_origen_id', 'a.sku',
        'tma.cantidad_origen', 'tma.precio_costo_origen', 'tma.precio_costo_destino',
    ]);

$ajustesCosto = [];
echo "--- Costos línea (alinear a precio del asiento) ---\n";
foreach ($tms as $r) {
    $monto = (float) DB::table('asiento_movimiento')
        ->where('asiento_id', $r->asiento_id)
        ->whereNull('deleted_at')
        ->where('monto', '>', 0)
        ->value('monto');
    $cant = (float) $r->cantidad_origen;
    if ($cant <= 0 || $monto <= 0) {
        echo "TM#{$r->id} sin monto/cant — skip\n";
        continue;
    }
    $precioObjetivo = round($monto / $cant, 6);
    $delta = abs($precioObjetivo - (float) $r->precio_costo_origen);
    $ajustesCosto[] = [
        'tm_id' => (int) $r->id,
        'codigo' => $r->codigo,
        'linea_id' => (int) $r->linea_id,
        'sku' => $r->sku,
        'cantidad' => $cant,
        'precio_actual' => (float) $r->precio_costo_origen,
        'precio_objetivo' => $precioObjetivo,
        'importe_asiento' => $monto,
        'salida_id' => (int) ($r->movimientostock_salida_id ?? 0),
        'entrada_id' => (int) ($r->movimientostock_entrada_id ?? 0),
        'articulo_id' => (int) $r->articulo_origen_id,
        'cambia' => $delta > 0.0000005,
    ];
    echo sprintf(
        "TM#%d %s %s cant=%s actual=%s objetivo=%s %s\n",
        $r->id,
        $r->codigo,
        $r->sku,
        $fmt($cant),
        $fmt((float) $r->precio_costo_origen),
        $fmt($precioObjetivo),
        $delta > 0.0000005 ? '*CAMBIA' : 'ok'
    );
}

// --- 2) Empresa TM#28 ---
$tm28 = Transferencia_Mercaderia::query()->with(['asientos.asiento_movimientos'])->findOrFail($tmEmpresaFixId);
$asiento28 = $tm28->asientos;
if (! $asiento28) {
    fwrite(STDERR, "TM#28 sin asiento.\n");
    exit(1);
}
$nroViejo = (string) $asiento28->numeroasiento;
$empViejo = (int) $asiento28->empresa_id;

echo "\n--- Empresa asiento TM#28 ---\n";
echo "TM empresa_id={$tm28->empresa_id} (debe quedar {$empresaKandiko})\n";
echo "Asiento#{$asiento28->id} emp={$empViejo} nro={$nroViejo} fecha={$asiento28->fecha}\n";
echo "Depósito destino debe ser Kandiko; monto asiento se conserva.\n";

if ($empViejo !== $empresaBiyemas) {
    fwrite(STDERR, "Asiento TM#28 ya no está en Biyemas (emp={$empViejo}). Abortar para revisar.\n");
    exit(1);
}

$repo = app(AsientoRepository::class);

// Verificar ctamov Biyemas presente
$api = app(\App\ApiAnita::class);
$check = $api->apiCall([
    'acc' => 'list',
    'sistema' => 'contab',
    'tabla' => 'ctamov',
    'campos' => 'ctav_empresa,ctav_nro_asiento,ctav_nro_linea,ctav_d_h,ctav_cuenta,ctav_importe',
    'whereArmado' => " WHERE ctav_empresa = '1' AND ctav_nro_asiento = '".str_replace("'", "''", $nroViejo)."' ",
    'orderBy' => 'ctav_nro_linea',
]);
$filasBiyemas = \App\ApiAnita::decodificarListaFilas($check);
echo 'ctamov Biyemas nro '.$nroViejo.': '.count($filasBiyemas)." filas\n";
if (count($filasBiyemas) < 2) {
    fwrite(STDERR, "No se encontraron las 2 líneas ctamov en Biyemas. Abortar.\n");
    exit(1);
}

if (! $apply) {
    echo "\nDRY-RUN: no se persistió nada. Ejecutar con 'apply' para grabar.\n";
    exit(0);
}

echo "\n=== APLICANDO ===\n";

DB::transaction(function () use ($ajustesCosto, $tm28, $asiento28, $nroViejo, $empresaKandiko, $repo) {
    // A) Costos ERP
    foreach ($ajustesCosto as $aj) {
        if (! $aj['cambia']) {
            continue;
        }
        $precio = $aj['precio_objetivo'];
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
        echo "Costo TM#{$aj['tm_id']} {$aj['sku']}: {$aj['precio_actual']} → {$precio}\n";
    }

    // B) Mover asiento TM#28 a Kandiko
    // 1) Borrar ctamov Biyemas (no valida período)
    $repo->eliminarCtamovAnitaPorNumero(1, $nroViejo);
    echo "ctamov Biyemas {$nroViejo} eliminado.\n";

    // 2) Nuevo número Kandiko (consume numerador Anita)
    $ref = new ReflectionClass($repo);
    $metodo = $ref->getMethod('ultimoAsientoAnita');
    $metodo->setAccessible(true);
    $nroNuevo = (string) $metodo->invoke($repo, $empresaKandiko);
    echo "Nuevo numeroasiento Kandiko: {$nroNuevo}\n";

    // 3) ERP: TM + asiento
    $tm28->empresa_id = $empresaKandiko;
    $tm28->save();

    // update vía Eloquent directo para no chocar Auth/período en repository update
    $asiento28->empresa_id = $empresaKandiko;
    $asiento28->numeroasiento = $nroNuevo;
    $asiento28->save();

    echo "ERP: TM#28 empresa=2, asiento#{$asiento28->id} emp=2 nro={$nroNuevo}\n";
});

// C) Sync ctamov Kandiko (fuera de la TX MySQL; Anita es otro sistema)
$asientoFresh = Asiento::with(['asiento_movimientos.monedas'])->findOrFail($asiento28->id);
$payload = $repo->armarPayloadAnitaDesdeModelo($asientoFresh);
$payload['omitir_validacion'] = true;
$payload['alcance_cierre_contable'] = \App\Support\Contable\PeriodoContableCierreSupport::ALCANCE_TRANSFERENCIA;
$repo->sincronizarCtamovAnita($payload);
echo "ctamov Kandiko nro {$asientoFresh->numeroasiento} sincronizado.\n";

// Verificación
$checkK = $api->apiCall([
    'acc' => 'list',
    'sistema' => 'contab',
    'tabla' => 'ctamov',
    'campos' => 'ctav_empresa,ctav_nro_asiento,ctav_nro_linea,ctav_d_h,ctav_cuenta,ctav_importe',
    'whereArmado' => " WHERE ctav_empresa = '2' AND ctav_nro_asiento = '".str_replace("'", "''", (string) $asientoFresh->numeroasiento)."' ",
    'orderBy' => 'ctav_nro_linea',
]);
$filasK = \App\ApiAnita::decodificarListaFilas($checkK);
echo 'Verificación ctamov Kandiko: '.count($filasK)." filas\n";
foreach ($filasK as $f) {
    $a = is_array($f) ? $f : get_object_vars($f);
    echo "  L{$a['ctav_nro_linea']} {$a['ctav_d_h']} {$a['ctav_cuenta']} {$a['ctav_importe']}\n";
}

$checkB = $api->apiCall([
    'acc' => 'list',
    'sistema' => 'contab',
    'tabla' => 'ctamov',
    'campos' => 'ctav_empresa,ctav_nro_asiento,ctav_nro_linea',
    'whereArmado' => " WHERE ctav_empresa = '1' AND ctav_nro_asiento = '".str_replace("'", "''", $nroViejo)."' ",
]);
$filasB = \App\ApiAnita::decodificarListaFilas($checkB);
echo 'Verificación ctamov Biyemas nro viejo '.$nroViejo.': '.count($filasB)." filas (debe 0)\n";

$tmCheck = DB::table('transferencia_mercaderia')->where('id', 28)->first(['empresa_id', 'asiento_id']);
$asCheck = DB::table('asiento')->where('id', $tmCheck->asiento_id)->first(['empresa_id', 'numeroasiento']);
$lineaCheck = DB::table('transferencia_mercaderia_articulo')->where('transferencia_mercaderia_id', 28)->first(['precio_costo_origen', 'precio_costo_destino']);
echo "\nERP final TM#28 emp={$tmCheck->empresa_id} asiento emp={$asCheck->empresa_id} nro={$asCheck->numeroasiento} p_costo={$lineaCheck->precio_costo_origen}\n";
echo "OK.\n";
