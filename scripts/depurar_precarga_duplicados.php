<?php

/**
 * Depura duplicados de precarga en Anita (y opcionalmente en ERP).
 *
 * Uso:
 *   php scripts/depurar_precarga_duplicados.php           # dry-run
 *   php scripts/depurar_precarga_duplicados.php --apply   # ejecuta borrados
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\ApiAnita;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Repositories\Compras\Precarga_Comprobante_ProveedorRepository;
use App\Services\Compras\PrecargaComprobanteAnitaSyncService;
use Illuminate\Support\Facades\DB;

$apply = in_array('--apply', $argv ?? [], true);

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

function claveFacturaAnita(object|array $row): string
{
    return implode('|', [
        trim((string) filaVal($row, 'prec_empresa')),
        trim((string) filaVal($row, 'prec_proveedor')),
        trim((string) filaVal($row, 'prec_tipo')),
        trim((string) filaVal($row, 'prec_letra')),
        trim((string) filaVal($row, 'prec_sucursal')),
        trim((string) filaVal($row, 'prec_numero')),
    ]);
}

function claveFacturaErp(object $row): string
{
    $tipo = DB::table('tipotransaccion_compra')
        ->where('id', $row->tipotransaccion_compra_id)
        ->value('abreviatura');

    $empresaCodigo = DB::table('empresa')->where('id', $row->empresa_id)->value('codigo');
    $proveedorCodigo = str_pad(
        (string) DB::table('proveedor')->where('id', $row->proveedor_id)->value('codigo'),
        6,
        '0',
        STR_PAD_LEFT
    );

    return implode('|', [
        (string) (int) $empresaCodigo,
        $proveedorCodigo,
        substr((string) $tipo, 0, 3),
        strtoupper(trim((string) $row->letra)),
        (string) (int) $row->sucursal,
        (string) (int) $row->numerocomprobante,
    ]);
}

echo ($apply ? "MODO APLICAR\n" : "MODO DRY-RUN (use --apply para borrar)\n");

$anitaSync = app(PrecargaComprobanteAnitaSyncService::class);
$precargaRepo = app(Precarga_Comprobante_ProveedorRepository::class);

$api = new ApiAnita();
$resp = $api->apiCall([
    'acc' => 'list',
    'sistema' => 'compras',
    'tabla' => 'precarga',
    'campos' => 'prec_id, prec_proveedor, prec_empresa, prec_tipo, prec_letra, prec_sucursal, prec_numero',
    'whereArmado' => '',
]);
$filasAnita = ApiAnita::decodificarListaFilas($resp);

$gruposAnita = [];
foreach ($filasAnita as $row) {
    $precId = (int) filaVal($row, 'prec_id');
    $gruposAnita[claveFacturaAnita($row)][] = $precId;
}

$borrarAnita = [];
$conservarAnita = [];
foreach ($gruposAnita as $clave => $ids) {
    sort($ids, SORT_NUMERIC);
    $conservarAnita[$clave] = max($ids);
    if (count($ids) > 1) {
        foreach ($ids as $id) {
            if ($id !== $conservarAnita[$clave]) {
                $borrarAnita[] = $id;
            }
        }
    }
}

echo "\n=== ANITA precarga ===\n";
echo 'Filas actuales: '.count($filasAnita)."\n";
echo 'Duplicados a borrar: '.count($borrarAnita)."\n";
foreach ($borrarAnita as $id) {
    echo "  DELETE Anita prec_id={$id}\n";
}

if ($apply && $borrarAnita !== []) {
    foreach ($borrarAnita as $id) {
        $anitaSync->deleteCabecera($id);
        echo "  Borrado Anita prec_id={$id}\n";
    }
}

$precargasErp = DB::table('precarga_comprobante_proveedor')
    ->select('id', 'empresa_id', 'proveedor_id', 'tipotransaccion_compra_id', 'letra', 'sucursal', 'numerocomprobante')
    ->orderBy('id')
    ->get();

$gruposErp = [];
foreach ($precargasErp as $row) {
    $gruposErp[claveFacturaErp($row)][] = (int) $row->id;
}

$anitaIdsPost = null;
if ($apply) {
    $resp2 = $api->apiCall([
        'acc' => 'list',
        'sistema' => 'compras',
        'tabla' => 'precarga',
        'campos' => 'prec_id',
        'whereArmado' => '',
    ]);
    $anitaIdsPost = collect(ApiAnita::decodificarListaFilas($resp2))
        ->map(fn ($r) => (int) filaVal($r, 'prec_id'))
        ->flip()
        ->all();
} else {
    $anitaIdsPost = collect($filasAnita)
        ->map(fn ($r) => (int) filaVal($r, 'prec_id'))
        ->flip()
        ->all();
    foreach ($borrarAnita as $id) {
        unset($anitaIdsPost[$id]);
    }
}

$borrarErp = [];
$conservarErp = [];
foreach ($gruposErp as $clave => $ids) {
    sort($ids, SORT_NUMERIC);
    $candidatosConAnita = array_values(array_filter($ids, fn ($id) => isset($anitaIdsPost[$id])));
    $conservar = $candidatosConAnita !== [] ? max($candidatosConAnita) : max($ids);
    $conservarErp[$clave] = $conservar;

    if (count($ids) > 1) {
        foreach ($ids as $id) {
            if ($id !== $conservar) {
                $borrarErp[] = $id;
            }
        }
    }
}

echo "\n=== ERP precarga_comprobante_proveedor ===\n";
echo 'Filas actuales: '.$precargasErp->count()."\n";
echo 'Duplicados a borrar: '.count($borrarErp)."\n";
foreach ($borrarErp as $id) {
    echo "  DELETE ERP precarga id={$id}\n";
}

if ($apply && $borrarErp !== []) {
    foreach ($borrarErp as $id) {
        $precargaRepo->delete($id);
        echo "  Borrado ERP precarga id={$id}\n";
    }
}

echo "\n=== RESUMEN ===\n";
if (! $apply) {
    echo "Ejecute con --apply para aplicar los borrados.\n";
} else {
    $respFinal = $api->apiCall([
        'acc' => 'list',
        'sistema' => 'compras',
        'tabla' => 'precarga',
        'campos' => 'prec_id',
        'whereArmado' => '',
    ]);
    $totalAnita = count(ApiAnita::decodificarListaFilas($respFinal));
    $totalErp = DB::table('precarga_comprobante_proveedor')->count();
    echo "Anita precarga: {$totalAnita} filas\n";
    echo "ERP precargas: {$totalErp} filas\n";
}
