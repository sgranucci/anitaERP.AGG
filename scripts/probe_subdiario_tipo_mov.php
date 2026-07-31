<?php

declare(strict_types=1);

use App\ApiAnita;
use App\Support\Contable\Anita\AnitaSubdiarioMayorSupport;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$api = new ApiAnita();
$cuentaDeudores = (int) config('cliente.DEUDORES_POR_VENTAS');
$fecha = (int) ($argv[1] ?? 20260730);
$sistema = (string) ($argv[2] ?? 'ventas');

$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');

function listarConRetry(ApiAnita $api, string $sistema, string $tabla, string $campos, string $where, int $intentos = 6): array
{
    $raw = '';
    for ($i = 1; $i <= $intentos; $i++) {
        $raw = (string) $api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
        ]);
        $err = ApiAnita::extraerMensajeError($raw);
        $filas = ApiAnita::decodificarListaFilas($raw);
        echo "  try{$i} {$sistema}/{$tabla} err=".($err ?? 'null').' n='.count($filas).' rawlen='.strlen($raw)."\n";
        if ($filas !== [] || $err !== null) {
            return $filas;
        }
        usleep(250000);
    }

    return [];
}

echo "fecha={$fecha} sistema={$sistema} deudores={$cuentaDeudores}\n";

$camposSub = 'subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_importe,subd_emisor,subd_desc_mov';
echo "subdiario…\n";
$subdiario = listarConRetry($api, $sistema, 'subdiario', $camposSub, ' WHERE subd_fecha = '.$fecha);
if ($subdiario === []) {
    echo "Sin subdiario\n";
    exit(1);
}

echo "sample[0]=".json_encode($subdiario[0], JSON_UNESCAPED_UNICODE)."\n";

$cuentas = [];
$contras = [];
$tipos = [];
$movs = [];
$debeCuenta = 0.0;
$haberCuenta = 0.0;
$nImpCuenta = 0;
$detalle = [];

foreach ($subdiario as $f) {
    $t = strtoupper(trim((string) ($f->subd_tipo ?? '')));
    $m = strtoupper(trim((string) ($f->subd_tipo_mov ?? '')));
    $c = (string) ($f->subd_cuenta ?? '');
    $k = (string) ($f->subd_contrapartida ?? '');
    $tipos[$t] = ($tipos[$t] ?? 0) + 1;
    $movs[$m] = ($movs[$m] ?? 0) + 1;
    $cuentas[$c] = ($cuentas[$c] ?? 0) + 1;
    $contras[$k] = ($contras[$k] ?? 0) + 1;

    foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($f) as $imp) {
        $cuenta = (int) $imp['cuenta'];
        $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
        if ($cuenta !== $cuentaDeudores) {
            continue;
        }
        $nImpCuenta++;
        $debeCuenta += $dh['debe'];
        $haberCuenta += $dh['haber'];
        $lado = ((int) ($f->subd_cuenta ?? 0) === $cuentaDeudores) ? 'subd_cuenta' : 'subd_contrapartida';
        $detalle[] = [
            'tipo' => $t,
            'comp' => trim(($f->subd_letra ?? '').'-'.($f->subd_sucursal ?? '').'-'.($f->subd_nro ?? '')),
            'tipo_mov' => $m,
            'lado' => $lado,
            'dh' => $imp['dh'],
            'importe' => (float) $imp['importe'],
            'desc' => (string) ($f->subd_desc_mov ?? ''),
        ];
    }
}

arsort($cuentas);
arsort($contras);
echo 'tipos='.json_encode($tipos)."\n";
echo 'tipo_mov='.json_encode($movs)."\n";
echo 'top subd_cuenta='.json_encode(array_slice($cuentas, 0, 20, true))."\n";
echo 'top subd_contrapartida='.json_encode(array_slice($contras, 0, 20, true))."\n";
echo "imputaciones a deudores {$cuentaDeudores}: n={$nImpCuenta} Debe=".$fmt($debeCuenta).' Haber='.$fmt($haberCuenta).' Neto='.$fmt($debeCuenta - $haberCuenta)."\n";

// Si 0, probar todas las cuentas 113*
echo "\nSaldos por cuenta (expansión tipo_mov) cuentas 113*:\n";
$porCuenta = [];
foreach ($subdiario as $f) {
    foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($f) as $imp) {
        $cuenta = (int) $imp['cuenta'];
        if ($cuenta < 113000000 || $cuenta > 113999999) {
            continue;
        }
        if (! isset($porCuenta[$cuenta])) {
            $porCuenta[$cuenta] = ['n' => 0, 'debe' => 0.0, 'haber' => 0.0];
        }
        $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
        $porCuenta[$cuenta]['n']++;
        $porCuenta[$cuenta]['debe'] += $dh['debe'];
        $porCuenta[$cuenta]['haber'] += $dh['haber'];
    }
}
ksort($porCuenta);
foreach ($porCuenta as $c => $a) {
    echo sprintf("  %d n=%d D=%s H=%s neto=%s\n", $c, $a['n'], $fmt($a['debe']), $fmt($a['haber']), $fmt($a['debe'] - $a['haber']));
}

if ($detalle !== []) {
    echo "\nPrimeras 12 imputaciones deudores:\n";
    foreach (array_slice($detalle, 0, 12) as $d) {
        echo sprintf(
            "  %s %s mov=%s lado=%s dh=%s imp=%s %s\n",
            $d['tipo'],
            $d['comp'],
            $d['tipo_mov'],
            $d['lado'],
            $d['dh'],
            $fmt($d['importe']),
            substr($d['desc'], 0, 40),
        );
    }
}

// Climov mismo día para comparar
echo "\nclimov…\n";
$climov = listarConRetry(
    $api,
    'ventas',
    'climov',
    'cliv_cliente,cliv_tipo,cliv_letra,cliv_sucursal,cliv_nro,cliv_monto,cliv_t_cobrado,cliv_estado',
    ' WHERE cliv_fecha = '.$fecha,
);
$porTipoCli = [];
foreach ($climov as $f) {
    $t = strtoupper(trim((string) ($f->cliv_tipo ?? '')));
    if (! isset($porTipoCli[$t])) {
        $porTipoCli[$t] = ['n' => 0, 'monto' => 0.0];
    }
    $porTipoCli[$t]['n']++;
    $porTipoCli[$t]['monto'] += (float) ($f->cliv_monto ?? 0);
}
ksort($porTipoCli);
echo "climov por tipo:\n";
foreach ($porTipoCli as $t => $a) {
    echo sprintf("  %-4s n=%d monto=%s\n", $t, $a['n'], $fmt($a['monto']));
}

echo "\nOK\n";
