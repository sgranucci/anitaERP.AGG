<?php

/**
 * Alinea en Anita (Bierzo y Villafranca) los parámetros comerciales de las facturas
 * del día: vendedor, cobrador, zona, subzona, zonamult (cabecera y stkmov).
 *
 * Uso:
 *   php scripts/corregir_parametros_anita_facturas_dia.php --fecha=2026-08-28
 *   php scripts/corregir_parametros_anita_facturas_dia.php --fecha=2026-08-28 --ejecutar
 */

declare(strict_types=1);

use App\ApiAnita;
use App\Models\Ventas\Cobrador;
use App\Models\Ventas\Vendedor;
use App\Models\Ventas\Zonavta;
use App\Support\Ventas\ClienteAnitaZonamultSupport;
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
$consultar = static function (?string $path, string $tabla, string $campos, string $where) use ($api): ?object {
    $payload = [
        'acc' => 'list',
        'tabla' => $tabla,
        'campos' => $campos,
        'whereArmado' => $where,
    ];
    if ($path !== null) {
        $payload['path_sistema'] = $path;
    }
    if ($tabla === 'climae') {
        $payload['sistema'] = 'ventas';
    }

    return ApiAnita::primeraFilaLista($api->apiCall($payload));
};

$cobradorClimaeCache = [];
$cobradorDesdeClimae = static function (string $codigoCliente, ?string $path) use (&$cobradorClimaeCache, $consultar): int {
    $codigoPad = str_pad(ltrim($codigoCliente, '0') !== '' ? ltrim($codigoCliente, '0') : '0', 6, '0', STR_PAD_LEFT);
    $key = ($path ?? 'bierzo').'|'.$codigoPad;
    if (array_key_exists($key, $cobradorClimaeCache)) {
        return $cobradorClimaeCache[$key];
    }
    $fila = $consultar(
        $path,
        'climae',
        'clim_cliente,clim_cobrador',
        " WHERE clim_cliente = '".addslashes($codigoPad)."' "
    );
    $valor = (int) ($fila->clim_cobrador ?? 0);
    $cobradorClimaeCache[$key] = $valor;

    return $valor;
};

$rows = DB::table('venta as v')
    ->leftJoin('cliente as c', 'c.id', '=', 'v.cliente_id')
    ->whereDate('v.fecha', $fecha)
    ->select([
        'v.id', 'v.codigo', 'v.puntoventa_id', 'v.vendedor_id',
        'c.codigo as cli', 'c.cobrador_id', 'c.zonavta_id', 'c.subzonavta_id', 'c.provincia_id',
    ])
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
    $dest = $esVf ? 'VILLAFRANCA' : 'BIERZO';

    $whereVenta = " WHERE ven_tipo = '".addslashes($p['tipo'])."' AND ven_letra = '".addslashes($p['letra'])
        ."' AND ven_sucursal = '".$p['sucursal']."' AND ven_nro = '".$p['nro']."' ";
    $fila = $consultar($path, 'venta', 'ven_vendedor,ven_cobrador,ven_zonavta,ven_subzona,ven_zonamult', $whereVenta);
    if ($fila === null) {
        echo "{$dest}|{$r->codigo}|NO_EN_ANITA\n";
        continue;
    }

    $vendedor = Vendedor::codigoAnitaDesdeId((int) $r->vendedor_id) ?: 1;
    $cobrador = Cobrador::codigoAnitaDesdeId((int) ($r->cobrador_id ?? 0));
    if ($cobrador <= 0) {
        $cobrador = $cobradorDesdeClimae((string) ($r->cli ?? ''), $path);
    }
    $zona = Zonavta::codigoAnitaDesdeId((int) ($r->zonavta_id ?? 0));
    $subzona = (int) ($r->subzonavta_id ?? 0);
    $zonamult = ClienteAnitaZonamultSupport::codigoDesdeProvinciaId(
        isset($r->provincia_id) ? (int) $r->provincia_id : null
    );

    $esperado = [
        'ven_vendedor' => $vendedor,
        'ven_cobrador' => $cobrador,
        'ven_zonavta' => $zona,
        'ven_subzona' => $subzona,
        'ven_zonamult' => $zonamult,
    ];
    $cambios = [];
    foreach ($esperado as $campo => $valor) {
        $actual = (int) ($fila->{$campo} ?? 0);
        if ($actual !== (int) $valor) {
            $cambios[$campo] = ['de' => $actual, 'a' => (int) $valor];
        }
    }
    if ($cambios === []) {
        continue;
    }

    $pendientes[] = [
        'codigo' => (string) $r->codigo,
        'destino' => $dest,
        'path' => $path,
        'p' => $p,
        'cambios' => $cambios,
        'esperado' => $esperado,
    ];
}

echo 'Fecha '.$fecha.' — comprobantes a corregir: '.count($pendientes).($ejecutar ? " (EJECUTAR)\n" : " (dry-run)\n");
foreach ($pendientes as $item) {
    $partes = [];
    foreach ($item['cambios'] as $campo => $c) {
        $partes[] = $campo.' '.$c['de'].'->'.$c['a'];
    }
    echo $item['destino'].'|'.$item['codigo'].'|'.implode('; ', $partes)."\n";
}

if (! $ejecutar || $pendientes === []) {
    exit(0);
}

$ok = 0;
$errores = 0;
foreach ($pendientes as $item) {
    $p = $item['p'];
    $e = $item['esperado'];
    $whereVenta = " WHERE ven_tipo = '".addslashes($p['tipo'])."' AND ven_letra = '".addslashes($p['letra'])
        ."' AND ven_sucursal = '".$p['sucursal']."' AND ven_nro = '".$p['nro']."' ";
    $whereStk = " WHERE stkv_tipo = '".addslashes($p['tipo'])."' AND stkv_letra = '".addslashes($p['letra'])
        ."' AND stkv_sucursal = '".$p['sucursal']."' AND stkv_nro = '".$p['nro']."' ";

    $updVenta = [
        'acc' => 'update',
        'tabla' => 'venta',
        'valores' => " ven_vendedor = '".$e['ven_vendedor']."',"
            ." ven_cobrador = '".$e['ven_cobrador']."',"
            ." ven_zonavta = '".$e['ven_zonavta']."',"
            ." ven_subzona = '".$e['ven_subzona']."',"
            ." ven_zonamult = '".$e['ven_zonamult']."' ",
        'whereArmado' => $whereVenta,
    ];
    $updStk = [
        'acc' => 'update',
        'tabla' => 'stkmov',
        'valores' => " stkv_vendedor = '".$e['ven_vendedor']."',"
            ." stkv_zona_vta = '".$e['ven_zonavta']."',"
            ." stkv_zona_mult = '".$e['ven_zonamult']."',"
            ." stkv_subzona = '".$e['ven_subzona']."' ",
        'whereArmado' => $whereStk,
    ];
    if ($item['path'] !== null) {
        $updVenta['path_sistema'] = $item['path'];
        $updStk['path_sistema'] = $item['path'];
    }

    try {
        $api->apiCallEscritura($updVenta, 'venta params '.$item['codigo'], 'anita_bridge.fallo', true);
        try {
            $api->apiCallEscritura($updStk, 'stkmov params '.$item['codigo']);
        } catch (\Throwable $eStk) {
            echo 'WARN stkmov '.$item['codigo'].': '.$eStk->getMessage()."\n";
        }
        $ok++;
        echo 'OK '.$item['destino'].' '.$item['codigo']."\n";
    } catch (\Throwable $ex) {
        $errores++;
        echo 'ERROR '.$item['codigo'].': '.$ex->getMessage()."\n";
    }
}

echo "Listo ok={$ok} errores={$errores}\n";
exit($errores > 0 ? 2 : 0);
