<?php

/**
 * Dimensiona el volumen de una cuenta en subdiario/climov antes de traerlos completos.
 *
 * Uso: php scripts/probe_conteo_cuenta_subdiario.php [cuenta=113100000]
 */

declare(strict_types=1);

use App\ApiAnita;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cuenta = (int) ($argv[1] ?? (int) config('cliente.DEUDORES_POR_VENTAS'));
$sistemaSub = (string) config('anita.subdiario_sistema', 'ventas');
$api = new ApiAnita();

function uno(ApiAnita $api, string $sistema, string $tabla, string $campos, string $where = ''): ?object
{
    for ($i = 1; $i <= 5; $i++) {
        $raw = (string) $api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
        ]);
        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            fwrite(STDERR, "ERROR {$sistema}/{$tabla}: {$err}\n");

            return null;
        }
        $fila = ApiAnita::primeraFilaLista($raw);
        if ($fila !== null) {
            return $fila;
        }
        usleep(250000);
    }

    return null;
}

echo "cuenta={$cuenta} sistema_subdiario={$sistemaSub}\n\n";

$f = uno(
    $api,
    $sistemaSub,
    'subdiario',
    'COUNT(*) AS n, MIN(subd_fecha) AS fmin, MAX(subd_fecha) AS fmax',
    ' WHERE subd_cuenta = '.$cuenta.' OR subd_contrapartida = '.$cuenta,
);
echo 'subdiario cuenta: n='.($f->n ?? '?').' fmin='.($f->fmin ?? '?').' fmax='.($f->fmax ?? '?')."\n";

$f = uno($api, 'ventas', 'climov', 'COUNT(*) AS n, MIN(cliv_fecha) AS fmin, MAX(cliv_fecha) AS fmax');
echo 'climov total:    n='.($f->n ?? '?').' fmin='.($f->fmin ?? '?').' fmax='.($f->fmax ?? '?')."\n";

$f = uno($api, 'ventas', 'climov', 'COUNT(*) AS n', ' WHERE cliv_monto <> cliv_t_cobrado');
echo 'climov pendiente (monto<>cobrado): n='.($f->n ?? '?')."\n";
