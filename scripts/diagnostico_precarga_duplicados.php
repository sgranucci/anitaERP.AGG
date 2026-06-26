<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\ApiAnita;

function filaVal(object|array $row, string $key): mixed
{
    if (is_array($row)) {
        foreach ([$key, strtoupper($key), strtolower($key)] as $k) {
            if (array_key_exists($k, $row)) {
                return $row[$k];
            }
        }

        return null;
    }

    foreach ([$key, strtoupper($key), strtolower($key)] as $k) {
        if (isset($row->{$k})) {
            return $row->{$k};
        }
    }

    return null;
}

echo "=== DUPLICADOS EN ERP (precarga_comprobante_proveedor) ===\n";
$dupErp = DB::select("
    SELECT empresa_id, proveedor_id, tipotransaccion_compra_id, letra, sucursal, numerocomprobante,
           COUNT(*) as cnt, GROUP_CONCAT(id ORDER BY id) as ids
    FROM precarga_comprobante_proveedor
    GROUP BY empresa_id, proveedor_id, tipotransaccion_compra_id, letra, sucursal, numerocomprobante
    HAVING cnt > 1
    ORDER BY cnt DESC
");
echo 'Grupos duplicados ERP: '.count($dupErp)."\n";
foreach ($dupErp as $r) {
    printf(
        "  %s %s-%s emp=%d prov=%d tipo=%d cnt=%d ids=%s\n",
        $r->letra,
        $r->sucursal,
        $r->numerocomprobante,
        $r->empresa_id,
        $r->proveedor_id,
        $r->tipotransaccion_compra_id,
        $r->cnt,
        $r->ids
    );
}

echo "\nTotal precargas ERP: ".DB::table('precarga_comprobante_proveedor')->count()."\n";

echo "\n=== LISTADO ANITA precarga ===\n";
$api = new ApiAnita();
$resp = $api->apiCall([
    'acc' => 'list',
    'sistema' => 'compras',
    'tabla' => 'precarga',
    'campos' => 'prec_id, prec_proveedor, prec_empresa, prec_tipo, prec_letra, prec_sucursal, prec_numero, prec_ordencompra, prec_total',
    'whereArmado' => '',
]);
$rows = ApiAnita::decodificarListaFilas($resp);
echo 'Filas Anita precarga: '.count($rows)."\n";

$groups = [];
foreach ($rows as $row) {
    $key = implode('|', [
        trim((string) filaVal($row, 'prec_empresa')),
        trim((string) filaVal($row, 'prec_proveedor')),
        trim((string) filaVal($row, 'prec_tipo')),
        trim((string) filaVal($row, 'prec_letra')),
        trim((string) filaVal($row, 'prec_sucursal')),
        trim((string) filaVal($row, 'prec_numero')),
    ]);
    $precId = (int) filaVal($row, 'prec_id');
    $groups[$key][] = $precId;
}

$dupAnita = array_filter($groups, fn ($ids) => count($ids) > 1);
echo 'Grupos duplicados Anita (misma factura): '.count($dupAnita)."\n";
$shown = 0;
foreach ($dupAnita as $key => $ids) {
    if ($shown++ >= 50) {
        echo "  ... (truncado)\n";
        break;
    }
    [$emp, $prov, $tipo, $letra, $suc, $num] = explode('|', $key);
    echo "  {$tipo} {$letra} {$suc}-{$num} emp={$emp} prov={$prov} prec_ids=".implode(',', $ids)."\n";
}

$erpIds = DB::table('precarga_comprobante_proveedor')->pluck('id')->flip()->all();
$huerfanos = [];
foreach ($rows as $row) {
    $id = (int) filaVal($row, 'prec_id');
    if ($id > 0 && ! isset($erpIds[$id])) {
        $huerfanos[] = $row;
    }
}
echo "\nFilas Anita sin registro ERP (huérfanas): ".count($huerfanos)."\n";
foreach (array_slice($huerfanos, 0, 30) as $row) {
    $id = filaVal($row, 'prec_id');
    $tipo = filaVal($row, 'prec_tipo');
    $letra = filaVal($row, 'prec_letra');
    $suc = filaVal($row, 'prec_sucursal');
    $num = filaVal($row, 'prec_numero');
    echo "  prec_id={$id} {$tipo} {$letra} {$suc}-{$num}\n";
}
