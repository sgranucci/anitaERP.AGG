<?php

/**
 * Lista los comprobantes que la alineación ARCA modificó en un día, con el monto antes,
 * el monto informado a ARCA y qué se tocó en cada uno. Genera además un CSV.
 *
 * Uso: php scripts/listar_facturas_alineadas_dia.php [dia=20260805]
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dia = (string) ($argv[1] ?? '20260805');
$dir = storage_path('app/reportes/alineacion_anita_arca');
$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');

$porClave = [];

foreach (glob($dir.'/backup_*.json') ?: [] as $archivo) {
    if (! preg_match('/backup_([A-Z]+)_([A-Z])_(\d+)_(\d+)_(\d{8})_(\d{6})\.json$/', basename($archivo), $m)) {
        continue;
    }
    if ($m[5] !== $dia) {
        continue;
    }

    $d = json_decode((string) file_get_contents($archivo), true);
    if (! is_array($d)) {
        continue;
    }

    $clave = (int) $m[4];
    $stamp = $m[5].$m[6];
    $aplicado = ! empty($d['aplicar']);

    // Puede haber una corrida en seco y otra aplicada del mismo comprobante: gana la aplicada más reciente.
    $previo = $porClave[$clave] ?? null;
    if ($previo !== null) {
        if ($previo['aplicado'] && ! $aplicado) {
            continue;
        }
        if ($previo['aplicado'] === $aplicado && $previo['stamp'] > $stamp) {
            continue;
        }
    }

    $venta = (array) ($d['antes']['venta'] ?? []);
    $arca = (array) ($d['arca'] ?? []);

    $tocado = [];
    foreach ((array) ($d['plan'] ?? []) as $paso) {
        $tabla = (string) ($paso['tabla'] ?? '');
        $acc = (string) ($paso['acc'] ?? '');
        if ($tabla === '') {
            continue;
        }
        $tocado[$tabla] ??= [];
        if ($acc !== '' && ! in_array($acc, $tocado[$tabla], true)) {
            $tocado[$tabla][] = $acc;
        }
    }
    $resumen = [];
    foreach ($tocado as $tabla => $accs) {
        $resumen[] = $tabla.($accs === [] ? '' : ' ('.implode('/', $accs).')');
    }

    $porClave[$clave] = [
        'nro' => $clave,
        'tipo' => $m[1],
        'letra' => $m[2],
        'sucursal' => (int) $m[3],
        'stamp' => $stamp,
        'hora' => substr($m[6], 0, 2).':'.substr($m[6], 2, 2).':'.substr($m[6], 4, 2),
        'aplicado' => $aplicado,
        'cliente' => trim((string) ($venta['ven_cliente'] ?? '')),
        'fecha' => trim((string) ($venta['ven_fecha'] ?? '')),
        'monto_antes' => (float) ($venta['ven_monto'] ?? 0),
        'gravado_antes' => (float) ($venta['ven_gravado'] ?? 0),
        'monto_arca' => (float) ($arca['total'] ?? 0),
        'gravado_arca' => (float) ($arca['gravado'] ?? 0),
        'pasos' => count((array) ($d['plan'] ?? [])),
        'tocado' => implode(', ', $resumen),
        'archivo' => basename($archivo),
    ];
}

ksort($porClave);

$fechaLegible = substr($dia, 6, 2).'/'.substr($dia, 4, 2).'/'.substr($dia, 0, 4);
echo "Comprobantes alineados contra ARCA el {$fechaLegible}: ".count($porClave)."\n\n";

printf(
    "%-4s %-12s %-8s %-10s %-9s | %16s %16s %14s | %5s | %s\n",
    '#', 'comprobante', 'cliente', 'fecha comp', 'hora alin', 'monto antes', 'monto ARCA', 'diferencia', 'pasos', 'tablas tocadas'
);
echo str_repeat('-', 170), "\n";

$i = 0;
$sumAntes = 0.0;
$sumArca = 0.0;
$conCambioMonto = 0;
$noAplicados = [];
$csv = [];

foreach ($porClave as $r) {
    $i++;
    $dif = $r['monto_arca'] - $r['monto_antes'];
    $sumAntes += $r['monto_antes'];
    $sumArca += $r['monto_arca'];
    if (abs(round($dif, 2)) > 0.009) {
        $conCambioMonto++;
    }
    if (! $r['aplicado']) {
        $noAplicados[] = $r['nro'];
    }

    printf(
        "%-4d %-12s %-8s %-10s %-9s | %16s %16s %14s | %5d | %s\n",
        $i,
        $r['tipo'].' '.$r['letra'].'-'.$r['sucursal'].'-'.$r['nro'],
        $r['cliente'],
        $r['fecha'],
        $r['hora'],
        $fmt($r['monto_antes']),
        $fmt($r['monto_arca']),
        $fmt($dif),
        $r['pasos'],
        $r['tocado'],
    );

    $csv[] = [
        $i,
        $r['tipo'].' '.$r['letra'].'-'.$r['sucursal'].'-'.$r['nro'],
        $r['nro'],
        $r['cliente'],
        $r['fecha'],
        $r['hora'],
        $r['aplicado'] ? 'SI' : 'NO',
        number_format($r['monto_antes'], 2, ',', ''),
        number_format($r['monto_arca'], 2, ',', ''),
        number_format($dif, 2, ',', ''),
        number_format($r['gravado_antes'], 2, ',', ''),
        number_format($r['gravado_arca'], 2, ',', ''),
        $r['pasos'],
        $r['tocado'],
    ];
}

echo "\n=== RESUMEN ===\n";
echo 'comprobantes: '.count($porClave)."\n";
echo 'con cambio de monto: '.$conCambioMonto."\n";
echo 'sin cambio de monto (solo reimputación de cuentas): '.(count($porClave) - $conCambioMonto)."\n";
echo 'NO aplicados (quedaron en simulación): '.count($noAplicados).($noAplicados === [] ? '' : ' -> '.implode(',', $noAplicados))."\n";
echo 'total monto antes: '.$fmt($sumAntes)."\n";
echo 'total monto ARCA : '.$fmt($sumArca)."\n";
echo 'diferencia total : '.$fmt($sumArca - $sumAntes)."\n";

$salida = storage_path('app/reportes/alineacion_anita_arca/listado_alineadas_'.$dia.'.csv');
$fh = fopen($salida, 'w');
fputcsv($fh, ['#', 'comprobante', 'nro', 'cliente', 'fecha_comprobante', 'hora_alineacion', 'aplicado',
    'monto_antes', 'monto_arca', 'diferencia', 'gravado_antes', 'gravado_arca', 'pasos', 'tablas_tocadas'], ';');
foreach ($csv as $fila) {
    fputcsv($fh, $fila, ';');
}
fclose($fh);

echo "\nCSV: {$salida}\n";
