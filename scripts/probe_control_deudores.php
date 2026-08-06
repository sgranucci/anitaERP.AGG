<?php

/**
 * Conciliación de la cuenta de control de deudores: saldo del mayor (ctamov histórico +
 * subdiario actual) contra el saldo de la cuenta corriente (climov pendiente).
 *
 * Uso: php scripts/probe_control_deudores.php [cuenta=113100000] [objetivo=3732.89] [--refrescar]
 */

declare(strict_types=1);

use App\ApiAnita;
use App\Support\Contable\Anita\AnitaSubdiarioMayorSupport;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

ini_set('memory_limit', '-1');
set_time_limit(0);

$args = array_slice($argv, 1);
$refrescar = in_array('--refrescar', $args, true);
$args = array_values(array_filter($args, static fn ($a) => ! str_starts_with($a, '--')));
$cuenta = (int) ($args[0] ?? (int) config('cliente.DEUDORES_POR_VENTAS'));
$objetivo = (float) ($args[1] ?? 3732.89);
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
            return $d;
        }
    }
    $d = $fn();
    file_put_contents($archivo, json_encode($d));

    return $d;
}

// ---------------------------------------------------------------------------
// 1) ctamov: acumulado por D/H (agregado en el bridge, no trae filas)
// ---------------------------------------------------------------------------
$ctaDh = cache(
    $cacheDir.'/ctamov_dh_'.$cuenta.'.json',
    $refrescar,
    static fn (): array => listar($api, [
        'sistema' => 'contab',
        'tabla' => 'ctamov',
        'campos' => 'ctav_d_h,SUM(ctav_importe) AS importe,COUNT(*) AS n',
        'whereArmado' => ' WHERE ctav_cuenta = '.$cuenta,
        'groupBy' => 'ctav_d_h',
    ]),
);

$ctaDebe = 0.0;
$ctaHaber = 0.0;
echo "=== ctamov (mayor histórico hasta 2025-01-01) ===\n";
foreach ($ctaDh as $r) {
    $dh = strtoupper(trim((string) ($r->ctav_d_h ?? '')));
    $imp = (float) ($r->importe ?? 0);
    echo sprintf("  %s n=%-12s importe=%20s\n", $dh, (string) ($r->n ?? ''), $fmt($imp));
    if ($dh === 'D') {
        $ctaDebe += $imp;
    } elseif ($dh === 'H') {
        $ctaHaber += $imp;
    }
}
$ctaNeto = $ctaDebe - $ctaHaber;
echo '  Neto ctamov = '.$fmt($ctaNeto)."\n";

// ---------------------------------------------------------------------------
// 2) subdiario: acumulado desde cache
// ---------------------------------------------------------------------------
$subDebe = 0.0;
$subHaber = 0.0;
foreach (glob($cacheDir.'/subdiario_'.$cuenta.'_*.json') ?: [] as $archivo) {
    foreach (json_decode((string) file_get_contents($archivo), false) ?: [] as $f) {
        foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($f) as $imp) {
            if ((int) $imp['cuenta'] !== $cuenta) {
                continue;
            }
            $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
            $subDebe += (float) ($dh['debe'] ?? 0);
            $subHaber += (float) ($dh['haber'] ?? 0);
        }
    }
}
$subNeto = $subDebe - $subHaber;
echo "\n=== subdiario (mayor desde 2025-01-02) ===\n";
echo '  Debe='.$fmt($subDebe).' Haber='.$fmt($subHaber).' Neto='.$fmt($subNeto)."\n";

$mayorTotal = $ctaNeto + $subNeto;
echo "\n  SALDO MAYOR TOTAL = ".$fmt($mayorTotal)."\n";

// ---------------------------------------------------------------------------
// 3) Cuenta corriente: saldo pendiente por tipo
// ---------------------------------------------------------------------------
$pend = cache(
    $cacheDir.'/climov_pendiente_por_tipo.json',
    false,
    static fn (): array => listar($api, [
        'sistema' => 'ventas',
        'tabla' => 'climov',
        'campos' => 'cliv_tipo,SUM(cliv_monto) AS monto,SUM(cliv_t_cobrado) AS cobrado,COUNT(*) AS n',
        'groupBy' => 'cliv_tipo',
    ]),
);

// Tipos que suman deuda (débito) y que la restan (crédito).
$credito = ['NCA', 'NCD', 'NCE', 'NCG', 'NCI', 'NCL', 'NCP', 'NCR', 'COB', 'COA', 'APA', 'AJS'];

echo "\n=== Cuenta corriente: pendiente por tipo ===\n";
$saldoCc = 0.0;
$saldoCcSinCob = 0.0;
foreach ($pend as $p) {
    $tipo = strtoupper(trim((string) ($p->cliv_tipo ?? '')));
    $pendiente = (float) ($p->monto ?? 0) - (float) ($p->cobrado ?? 0);
    if (abs($pendiente) < 0.005) {
        continue;
    }
    $signo = in_array($tipo, $credito, true) ? -1 : 1;
    $saldoCc += $signo * $pendiente;
    if ($tipo !== 'COB') {
        $saldoCcSinCob += $signo * $pendiente;
    }
    echo sprintf("  %-5s signo=%+d pendiente=%20s\n", $tipo, $signo, $fmt($pendiente));
}

echo "\n  SALDO CC (todos los tipos)      = ".$fmt($saldoCc)."\n";
echo '  SALDO CC (sin COB no aplicadas) = '.$fmt($saldoCcSinCob)."\n";

echo "\n=== CONTROL ===\n";
foreach ([
    'mayor - cc (todos)' => $mayorTotal - $saldoCc,
    'mayor - cc (sin COB)' => $mayorTotal - $saldoCcSinCob,
    'subdiario - cc (sin COB)' => $subNeto - $saldoCcSinCob,
] as $etiqueta => $valor) {
    echo sprintf(
        "  %-26s = %22s%s\n",
        $etiqueta,
        $fmt($valor),
        abs(abs($valor) - $objetivo) < 0.011 ? '   <<< COINCIDE' : '',
    );
}
