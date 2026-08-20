<?php

/**
 * Solo lectura: clasifica subd_emisor por subsistema/tipo de Anita contra los
 * maestros del ERP (proveedor, cliente, cuentacaja) para el mayor plano.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\ApiAnita;
use Illuminate\Support\Facades\DB;

$desde = (int) ($argv[1] ?? 20250101);
$hasta = (int) ($argv[2] ?? 20261231);

$api = new ApiAnita();
$raw = $api->apiCall([
    'acc' => 'list',
    'sistema' => 'contab',
    'tabla' => 'subdiario',
    'campos' => 'subd_empresa,subd_sistema,subd_tipo,subd_emisor',
    'whereArmado' => ' WHERE subd_fecha BETWEEN '.$desde.' AND '.$hasta,
]);
$error = ApiAnita::extraerMensajeError($raw);
if ($error !== null) {
    echo 'ERROR: '.$error.PHP_EOL;
    exit(1);
}

$filas = ApiAnita::decodificarListaFilas($raw);
echo 'lineas subdiario: '.count($filas).PHP_EOL;

$agg = [];
foreach ($filas as $f) {
    $clave = trim((string) ($f->subd_sistema ?? '')).'|'.strtoupper(trim((string) ($f->subd_tipo ?? '')));
    $emisor = trim((string) ($f->subd_emisor ?? ''));
    $agg[$clave]['n'] = ($agg[$clave]['n'] ?? 0) + 1;
    if ($emisor !== '' && ltrim($emisor, '0') !== '') {
        $agg[$clave]['e'][$emisor] = ($agg[$clave]['e'][$emisor] ?? 0) + 1;
    }
}
ksort($agg);

$norm = static fn (string $c): string => ltrim(trim($c), '0');
$prov = [];
$cli = [];
$cta = [];
foreach (DB::table('proveedor')->pluck('codigo') as $c) {
    $prov[$norm((string) $c)] = true;
}
foreach (DB::table('cliente')->pluck('codigo') as $c) {
    $cli[$norm((string) $c)] = true;
}
foreach (DB::table('cuentacaja')->pluck('codigo') as $c) {
    $cta[$norm((string) $c)] = true;
}

printf("%-11s %8s %6s %6s %6s %6s %6s  %s\n", 'SIST|TIPO', 'lineas', 'cods', 'prov', 'clie', 'ctacj', 'nada', 'ejemplos');
foreach ($agg as $clave => $v) {
    $codigos = $v['e'] ?? [];
    $np = $nc = $nk = $nn = 0;
    foreach (array_keys($codigos) as $cod) {
        $n = $norm((string) $cod);
        $p = isset($prov[$n]);
        $c = isset($cli[$n]);
        $k = isset($cta[$n]);
        $np += $p ? 1 : 0;
        $nc += $c ? 1 : 0;
        $nk += $k ? 1 : 0;
        $nn += (! $p && ! $c && ! $k) ? 1 : 0;
    }
    printf(
        "%-11s %8d %6d %6d %6d %6d %6d  %s\n",
        $clave,
        $v['n'],
        count($codigos),
        $np,
        $nc,
        $nk,
        $nn,
        implode(',', array_slice(array_keys($codigos), 0, 4)),
    );
}
