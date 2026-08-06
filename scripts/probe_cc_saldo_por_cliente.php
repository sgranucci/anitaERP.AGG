<?php

/**
 * Saldo de cuenta corriente por cliente (climov agregado) y detección de saldos que quedan
 * fuera del resumen de CC: clientes inexistentes en el maestro climae o códigos vacíos.
 *
 * Uso: php scripts/probe_cc_saldo_por_cliente.php [objetivo=3732.89] [--refrescar]
 */

declare(strict_types=1);

use App\ApiAnita;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

ini_set('memory_limit', '-1');
set_time_limit(0);

$args = array_slice($argv, 1);
$refrescar = in_array('--refrescar', $args, true);
$args = array_values(array_filter($args, static fn ($a) => ! str_starts_with($a, '--')));
$objetivo = (float) ($args[0] ?? 3732.89);
$cacheDir = storage_path('app/probe_cc_mayor');

$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');
$api = new ApiAnita();

function listar(ApiAnita $api, array $payload, int $intentos = 6): array
{
    for ($i = 1; $i <= $intentos; $i++) {
        $raw = (string) $api->apiCall($payload + ['acc' => 'list']);
        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            fwrite(STDERR, "ERROR {$payload['tabla']}: {$err}\n");
            if ($i === $intentos) {
                return [];
            }
        } else {
            $filas = ApiAnita::decodificarListaFilas($raw);
            if ($filas !== [] || $i === $intentos) {
                return $filas;
            }
        }
        usleep(300000);
    }

    return [];
}

function cache(string $archivo, bool $refrescar, callable $fn): array
{
    if (! $refrescar && is_readable($archivo)) {
        $d = json_decode((string) file_get_contents($archivo), false);
        if (is_array($d)) {
            fwrite(STDERR, 'cache: '.basename($archivo).' ('.count($d)." filas)\n");

            return $d;
        }
    }
    $d = $fn();
    file_put_contents($archivo, json_encode($d));
    fwrite(STDERR, 'bridge: '.basename($archivo).' ('.count($d)." filas)\n");

    return $d;
}

// Saldo por cliente y tipo (una sola agregación).
$filas = cache(
    $cacheDir.'/climov_por_cliente_tipo.json',
    $refrescar,
    static fn (): array => listar($api, [
        'sistema' => 'ventas',
        'tabla' => 'climov',
        'campos' => 'cliv_cliente,cliv_tipo,SUM(cliv_monto) AS monto,SUM(cliv_t_cobrado) AS cobrado,COUNT(*) AS n',
        'groupBy' => 'cliv_cliente,cliv_tipo',
    ]),
);

$credito = ['NCA', 'NCD', 'NCE', 'NCG', 'NCI', 'NCL', 'NCP', 'NCR', 'COB', 'COA', 'APA', 'AJS'];

$saldo = [];
foreach ($filas as $f) {
    $cli = trim((string) ($f->cliv_cliente ?? ''));
    $tipo = strtoupper(trim((string) ($f->cliv_tipo ?? '')));
    if ($tipo === 'COB') {
        // Las cobranzas se reflejan en cliv_t_cobrado de los comprobantes; no suman saldo propio.
        continue;
    }
    $pendiente = (float) ($f->monto ?? 0) - (float) ($f->cobrado ?? 0);
    $signo = in_array($tipo, $credito, true) ? -1 : 1;
    $saldo[$cli] = round(($saldo[$cli] ?? 0) + $signo * $pendiente, 2);
}

$total = 0.0;
foreach ($saldo as $v) {
    $total += $v;
}
echo 'clientes con movimientos: '.count($saldo).'  saldo total CC = '.$fmt($total)."\n";

// Maestro de clientes.
$climae = cache(
    $cacheDir.'/climae_codigos.json',
    $refrescar,
    static fn (): array => listar($api, [
        'sistema' => 'ventas',
        'tabla' => 'climae',
        'campos' => 'clim_codigo,clim_razon_social',
    ]),
);
$maestro = [];
foreach ($climae as $c) {
    $maestro[trim((string) ($c->clim_codigo ?? ''))] = trim((string) ($c->clim_razon_social ?? ''));
}
echo 'clientes en maestro climae: '.count($maestro)."\n";

echo "\n=== Clientes con saldo pero SIN maestro climae (quedan fuera del resumen de CC) ===\n";
$sumaHuerfanos = 0.0;
$huerfanos = [];
foreach ($saldo as $cli => $v) {
    if (abs($v) < 0.005) {
        continue;
    }
    if ($cli === '' || ! isset($maestro[$cli])) {
        $huerfanos[$cli] = $v;
        $sumaHuerfanos += $v;
    }
}
uasort($huerfanos, static fn ($a, $b) => abs($b) <=> abs($a));
foreach (array_slice($huerfanos, 0, 40, true) as $cli => $v) {
    echo sprintf("  cliente='%s' saldo=%16s%s\n", $cli, $fmt($v), abs(abs($v) - $objetivo) < 0.011 ? '   <<< COINCIDE' : '');
}
echo '  cantidad='.count($huerfanos).'  SUMA='.$fmt($sumaHuerfanos)
    .(abs(abs($sumaHuerfanos) - $objetivo) < 0.011 ? '   <<< COINCIDE' : '')."\n";

echo "\n=== Clientes cuyo saldo es exactamente {$objetivo} ===\n";
$hit = 0;
foreach ($saldo as $cli => $v) {
    if (abs(abs($v) - $objetivo) < 0.011) {
        $hit++;
        echo sprintf("  cliente=%-10s saldo=%16s  %s\n", $cli, $fmt($v), $maestro[$cli] ?? '(sin maestro)');
    }
}
if ($hit === 0) {
    echo "  (ninguno)\n";
}

// Saldos con centavos sueltos: candidatos a arrastre de redondeo.
echo "\n=== Saldos chicos (|saldo| < 100.000) — suma ===\n";
$chicos = 0.0;
$nChicos = 0;
foreach ($saldo as $v) {
    if (abs($v) > 0.005 && abs($v) < 100000) {
        $chicos += $v;
        $nChicos++;
    }
}
echo '  n='.$nChicos.' suma='.$fmt($chicos)."\n";
