<?php

/**
 * Corrige ven_vendedor / stkv_vendedor en Anita (Bierzo y Villafranca)
 * según el vendedor de la factura en el ERP.
 *
 * Uso:
 *   php scripts/corregir_vendedor_anita_facturas_dia.php --fecha=2026-08-28
 *   php scripts/corregir_vendedor_anita_facturas_dia.php --fecha=2026-08-28 --ejecutar
 */

declare(strict_types=1);

use App\ApiAnita;
use App\Models\Ventas\Vendedor;
use App\Support\Ventas\PedidoFacturaAnitaArchivosSupport;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$fecha = '2026-08-28';
$ejecutar = false;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--fecha=')) {
        $fecha = substr($arg, 8);
    }
    if ($arg === '--ejecutar') {
        $ejecutar = true;
    }
}

if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    fwrite(STDERR, "Fecha inválida: {$fecha}\n");
    exit(1);
}

$parseCodigo = static function (string $codigo): ?array {
    if (! preg_match('/^([A-Z]{3})\s+([A-Z])-(\d+)-(\d+)$/', $codigo, $m)) {
        return null;
    }

    return [
        'tipo' => $m[1],
        'letra' => $m[2],
        'sucursal' => (int) $m[3],
        'nro' => (int) $m[4],
    ];
};

$api = new ApiAnita();
$consultarVenta = static function (?string $path, array $p) use ($api): ?object {
    $payload = [
        'acc' => 'list',
        'tabla' => 'venta',
        'campos' => 'ven_tipo,ven_letra,ven_sucursal,ven_nro,ven_vendedor',
        'whereArmado' => " WHERE ven_tipo = '".addslashes($p['tipo'])."' AND ven_letra = '".addslashes($p['letra'])
            ."' AND ven_sucursal = '".$p['sucursal']."' AND ven_nro = '".$p['nro']."' ",
    ];
    if ($path !== null) {
        $payload['path_sistema'] = $path;
    }

    return ApiAnita::primeraFilaLista($api->apiCall($payload));
};

$rows = DB::table('venta as v')
    ->whereDate('v.fecha', $fecha)
    ->select(['v.id', 'v.codigo', 'v.puntoventa_id', 'v.vendedor_id'])
    ->orderBy('v.id')
    ->get();

$pendientes = [];
foreach ($rows as $r) {
    $p = $parseCodigo((string) $r->codigo);
    if ($p === null) {
        echo "SKIP parse {$r->codigo}\n";
        continue;
    }
    $esVf = PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision((int) $r->puntoventa_id);
    $path = $esVf ? PedidoFacturaAnitaArchivosSupport::PATH_VILLAFRANCA : null;
    $esperado = Vendedor::codigoAnitaDesdeId((int) $r->vendedor_id);
    if ($esperado <= 0) {
        $esperado = 1;
    }
    $fila = $consultarVenta($path, $p);
    if ($fila === null) {
        echo ($esVf ? 'VILLAFRANCA' : 'BIERZO')."|{$r->codigo}|esperado={$esperado}|NO_EN_ANITA\n";
        continue;
    }
    $actual = (int) ($fila->ven_vendedor ?? 0);
    if ($actual === $esperado) {
        continue;
    }
    $pendientes[] = [
        'codigo' => (string) $r->codigo,
        'destino' => $esVf ? 'VILLAFRANCA' : 'BIERZO',
        'path' => $path,
        'p' => $p,
        'de' => $actual,
        'a' => $esperado,
    ];
}

echo 'Fecha '.$fecha.' — a corregir: '.count($pendientes).($ejecutar ? " (EJECUTAR)\n" : " (dry-run)\n");
foreach ($pendientes as $item) {
    echo "{$item['destino']}|{$item['codigo']}|{$item['de']}->{$item['a']}\n";
}

if (! $ejecutar || $pendientes === []) {
    exit(0);
}

$ok = 0;
$errores = 0;
foreach ($pendientes as $item) {
    $p = $item['p'];
    $whereVenta = " WHERE ven_tipo = '".addslashes($p['tipo'])."' AND ven_letra = '".addslashes($p['letra'])
        ."' AND ven_sucursal = '".$p['sucursal']."' AND ven_nro = '".$p['nro']."' ";
    $whereStk = " WHERE stkv_tipo = '".addslashes($p['tipo'])."' AND stkv_letra = '".addslashes($p['letra'])
        ."' AND stkv_sucursal = '".$p['sucursal']."' AND stkv_nro = '".$p['nro']."' ";

    $updVenta = [
        'acc' => 'update',
        'tabla' => 'venta',
        'valores' => " ven_vendedor = '".$item['a']."' ",
        'whereArmado' => $whereVenta,
    ];
    $updStk = [
        'acc' => 'update',
        'tabla' => 'stkmov',
        'valores' => " stkv_vendedor = '".$item['a']."' ",
        'whereArmado' => $whereStk,
    ];
    if ($item['path'] !== null) {
        $updVenta['path_sistema'] = $item['path'];
        $updStk['path_sistema'] = $item['path'];
    }

    try {
        $api->apiCallEscritura($updVenta, 'venta ven_vendedor '.$item['codigo'], 'anita_bridge.fallo', true);
        try {
            $api->apiCallEscritura($updStk, 'stkmov stkv_vendedor '.$item['codigo']);
        } catch (\Throwable $eStk) {
            echo 'WARN stkmov '.$item['codigo'].': '.$eStk->getMessage()."\n";
        }
        $ok++;
        echo 'OK '.$item['destino'].' '.$item['codigo'].' '.$item['de'].'->'.$item['a']."\n";
    } catch (\Throwable $e) {
        $errores++;
        echo 'ERROR '.$item['codigo'].': '.$e->getMessage()."\n";
    }
}

echo "Listo ok={$ok} errores={$errores}\n";
exit($errores > 0 ? 2 : 0);
