<?php

/**
 * Verifica las facturas A del punto de venta 10: monto y cuentas contables entre
 * venta (ERP), climov (cuenta corriente Anita) y subdiario (mayor Anita).
 *
 * Uso: php scripts/probe_facturas_a10.php [sucursal=10] [letra=A] [--refrescar]
 */

declare(strict_types=1);

use App\ApiAnita;
use App\Support\Contable\Anita\AnitaSubdiarioMayorSupport;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

ini_set('memory_limit', '-1');
set_time_limit(0);

$args = array_slice($argv, 1);
$refrescar = in_array('--refrescar', $args, true);
$args = array_values(array_filter($args, static fn ($a) => ! str_starts_with($a, '--')));

$sucursal = (int) ($args[0] ?? 10);
$letra = strtoupper((string) ($args[1] ?? 'A'));
$cuentaDeudores = (int) config('cliente.DEUDORES_POR_VENTAS');
$sistemaSub = (string) config('anita.subdiario_sistema', 'ventas');
$cacheDir = storage_path('app/probe_cc_mayor');
if (! is_dir($cacheDir)) {
    mkdir($cacheDir, 0775, true);
}

$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');
$api = new ApiAnita();

// Cuentas esperadas para una factura de venta, según config/facturacion.php.
$cuentasEsperadas = [
    (int) $cuentaDeudores => 'DEUDORES',
    (int) config('facturacion.CUENTACONTABLE_VENTA') => 'VENTA',
    (int) config('facturacion.CUENTACONTABLE_IVA') => 'IVA',
    (int) config('facturacion.CUENTACONTABLE_PERCEPCION_IVA') => 'PERCEP_IVA',
];

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

$sufijo = $letra.'_'.$sucursal;

// --- Subdiario: TODAS las líneas de esas facturas (cualquier cuenta) ---
$subdiario = cache(
    $cacheDir.'/subdiario_fac_'.$sufijo.'.json',
    $refrescar,
    static fn (): array => listar($api, [
        'sistema' => $sistemaSub,
        'tabla' => 'subdiario',
        'campos' => 'subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_tipo_mov,'
            .'subd_cuenta,subd_contrapartida,subd_importe,subd_emisor,subd_desc_mov',
        'whereArmado' => " WHERE subd_tipo = 'FAC' AND subd_letra = '".$letra."' AND subd_sucursal = ".$sucursal,
    ]),
);

// --- climov de esas facturas ---
$climov = cache(
    $cacheDir.'/climov_fac_'.$sufijo.'.json',
    $refrescar,
    static fn (): array => listar($api, [
        'sistema' => 'ventas',
        'tabla' => 'climov',
        'campos' => 'cliv_cliente,cliv_tipo,cliv_letra,cliv_sucursal,cliv_nro,cliv_fecha,'
            .'cliv_monto,cliv_t_cobrado,cliv_estado',
        'whereArmado' => " WHERE cliv_tipo = 'FAC' AND cliv_letra = '".$letra."' AND cliv_sucursal = ".$sucursal,
    ]),
);

// --- venta del ERP para ese punto de venta (puede no existir: PV nativo de Anita) ---
$puntoventaId = DB::table('puntoventa')
    ->whereIn('codigo', [(string) $sucursal, str_pad((string) $sucursal, 5, '0', STR_PAD_LEFT)])
    ->value('id');

$ventas = collect();
if ($puntoventaId === null) {
    echo "AVISO: no hay punto de venta {$sucursal} en el ERP; se compara solo climov vs subdiario.\n";
} else {
    $ventas = DB::table('venta')
        ->leftJoin('tipotransaccion', 'tipotransaccion.id', '=', 'venta.tipotransaccion_id')
        ->where('venta.puntoventa_id', $puntoventaId)
        ->whereNull('venta.deleted_at')
        ->select([
            'venta.id',
            'venta.numerocomprobante',
            'venta.fecha',
            'venta.total',
            'venta.estado',
            'venta.cae',
            'tipotransaccion.abreviatura',
        ])
        ->get();
}

echo "\nfacturas {$letra}-{$sucursal}: subdiario_lineas=".count($subdiario)
    .' climov='.count($climov).' venta_erp='.count($ventas)."\n";

// --- Agregar subdiario por comprobante ---
$mayor = [];
foreach ($subdiario as $f) {
    $nro = (int) ($f->subd_nro ?? 0);
    $mayor[$nro] ??= ['fecha' => (int) ($f->subd_fecha ?? 0), 'deudores' => 0.0, 'cuentas' => [], 'lineas' => 0];
    $mayor[$nro]['lineas']++;

    foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($f) as $imp) {
        $cta = (int) $imp['cuenta'];
        $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
        $neto = (float) ($dh['debe'] ?? 0) - (float) ($dh['haber'] ?? 0);
        if ($cta === $cuentaDeudores) {
            $mayor[$nro]['deudores'] += $neto;
        }
        $mayor[$nro]['cuentas'][$cta] = round(($mayor[$nro]['cuentas'][$cta] ?? 0) + $neto, 2);
    }
}

// --- climov por comprobante ---
$cc = [];
foreach ($climov as $c) {
    $nro = (int) ($c->cliv_nro ?? 0);
    $cc[$nro] ??= [
        'fecha' => (int) ($c->cliv_fecha ?? 0),
        'cliente' => trim((string) ($c->cliv_cliente ?? '')),
        'monto' => 0.0,
        'estado' => trim((string) ($c->cliv_estado ?? '')),
    ];
    $cc[$nro]['monto'] += (float) ($c->cliv_monto ?? 0);
}

// --- venta ERP por número ---
$erp = [];
foreach ($ventas as $v) {
    $nro = (int) $v->numerocomprobante;
    $erp[$nro] ??= ['total' => 0.0, 'fecha' => (string) $v->fecha, 'estado' => (string) $v->estado, 'id' => (int) $v->id, 'cae' => (string) ($v->cae ?? ''), 'abrev' => (string) ($v->abreviatura ?? '')];
    $erp[$nro]['total'] += (float) $v->total;
}

echo 'comprobantes distintos: mayor='.count($mayor).' cc='.count($cc).' erp='.count($erp)."\n";

// --- Cruce ---
$nros = array_unique(array_merge(array_keys($mayor), array_keys($cc), array_keys($erp)));
sort($nros);

$problemas = [];
$cuentasVistas = [];
$sumaDiffCcMayor = 0.0;
$sumaDiffErpCc = 0.0;

foreach ($nros as $nro) {
    $my = $mayor[$nro] ?? null;
    $c = $cc[$nro] ?? null;
    $e = $erp[$nro] ?? null;

    $montoCc = $c ? (float) $c['monto'] : null;
    $montoMayor = $my ? (float) $my['deudores'] : null;
    $montoErp = $e ? (float) $e['total'] : null;

    foreach (($my['cuentas'] ?? []) as $cta => $v) {
        $cuentasVistas[$cta] = round(($cuentasVistas[$cta] ?? 0) + $v, 2);
    }

    $motivos = [];
    if ($c === null) {
        $motivos[] = 'sin climov';
    }
    if ($my === null) {
        $motivos[] = 'sin subdiario';
    }
    if ($e === null && $puntoventaId !== null) {
        $motivos[] = 'sin venta ERP';
    }
    if ($montoCc !== null && $montoMayor !== null && abs(round($montoCc - $montoMayor, 2)) > 0.009) {
        $motivos[] = 'monto CC≠mayor ('.$fmt($montoCc - $montoMayor).')';
        $sumaDiffCcMayor += $montoCc - $montoMayor;
    }
    if ($montoErp !== null && $montoCc !== null && abs(round($montoErp - $montoCc, 2)) > 0.009) {
        $motivos[] = 'monto ERP≠CC ('.$fmt($montoErp - $montoCc).')';
        $sumaDiffErpCc += $montoErp - $montoCc;
    }

    $ctasRaras = [];
    foreach (array_keys($my['cuentas'] ?? []) as $cta) {
        if (! isset($cuentasEsperadas[$cta])) {
            $ctasRaras[] = $cta;
        }
    }
    if ($ctasRaras !== []) {
        $motivos[] = 'cuentas fuera de config: '.implode(',', $ctasRaras);
    }

    // La factura debe dejar la partida doble balanceada dentro del comprobante.
    $sumaComp = 0.0;
    foreach (($my['cuentas'] ?? []) as $v) {
        $sumaComp += $v;
    }
    if ($my !== null && abs(round($sumaComp, 2)) > 0.009) {
        $motivos[] = 'asiento desbalanceado ('.$fmt($sumaComp).')';
    }

    if ($motivos !== []) {
        $problemas[] = [
            'nro' => $nro,
            'fecha' => $my['fecha'] ?? $c['fecha'] ?? $e['fecha'] ?? '',
            'cc' => $montoCc,
            'mayor' => $montoMayor,
            'erp' => $montoErp,
            'motivos' => $motivos,
        ];
    }
}

echo "\n=== CUENTAS USADAS EN LAS FACTURAS {$letra}-{$sucursal} ===\n";
krsort($cuentasVistas);
foreach ($cuentasVistas as $cta => $v) {
    echo sprintf(
        "  %-12s neto=%20s   %s\n",
        $cta,
        $fmt($v),
        $cuentasEsperadas[$cta] ?? '*** FUERA DE CONFIG ***',
    );
}

echo "\n=== COMPROBANTES CON PROBLEMAS ===\n";
echo 'cantidad='.count($problemas).' de '.count($nros)."\n";
echo 'suma diff CC-mayor='.$fmt($sumaDiffCcMayor).'  suma diff ERP-CC='.$fmt($sumaDiffErpCc)."\n\n";
foreach ($problemas as $p) {
    echo sprintf(
        "  nro=%-8s %s  CC=%14s MAYOR=%14s ERP=%14s | %s\n",
        $p['nro'],
        (string) $p['fecha'],
        $p['cc'] === null ? '-' : $fmt($p['cc']),
        $p['mayor'] === null ? '-' : $fmt($p['mayor']),
        $p['erp'] === null ? '-' : $fmt($p['erp']),
        implode('; ', $p['motivos']),
    );
}
