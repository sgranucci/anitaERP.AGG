<?php

/**
 * Busca un importe puntual en el detalle de climov cacheado y resume los tipos que
 * cargan cuenta corriente sin contrapartida en el mayor. No consulta el bridge.
 *
 * Uso: php scripts/probe_climov_importe.php [objetivo=3732.89] [desde=20260801] [hasta=20260806]
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

ini_set('memory_limit', '-1');

$objetivo = (float) ($argv[1] ?? 3732.89);
$desde = (int) ($argv[2] ?? 20260801);
$hasta = (int) ($argv[3] ?? 20260806);
$cacheDir = storage_path('app/probe_cc_mayor');

$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');

$archivo = $cacheDir.'/climov_detalle_'.$desde.'_'.$hasta.'.json';
$climov = is_readable($archivo) ? (json_decode((string) file_get_contents($archivo), false) ?: []) : [];
echo 'climov detalle '.$desde.'-'.$hasta.': '.count($climov)." filas\n";

// Tipos presentes en climov que el mayor no registra con ese tipo.
$soloCc = ['NDI', 'NDP', 'COC', 'CIE', 'APA', 'AJE', 'AJS', 'NCI', 'NCA', 'NCL', 'FAU'];

echo "\n=== Filas de climov con monto ≈ {$objetivo} ===\n";
$hit = 0;
foreach ($climov as $c) {
    $monto = (float) ($c->cliv_monto ?? 0);
    if (abs(abs($monto) - $objetivo) < 0.011) {
        $hit++;
        echo sprintf(
            "  %s %s-%s-%s-%s cli=%-8s monto=%14s cobrado=%14s estado=%s\n",
            (string) ($c->cliv_fecha ?? ''),
            trim((string) ($c->cliv_tipo ?? '')),
            trim((string) ($c->cliv_letra ?? '')),
            trim((string) ($c->cliv_sucursal ?? '')),
            trim((string) ($c->cliv_nro ?? '')),
            trim((string) ($c->cliv_cliente ?? '')),
            $fmt($monto),
            $fmt((float) ($c->cliv_t_cobrado ?? 0)),
            trim((string) ($c->cliv_estado ?? '')),
        );
    }
}
if ($hit === 0) {
    echo "  (ninguna)\n";
}

echo "\n=== climov por día y tipo (rango) ===\n";
$porDiaTipo = [];
foreach ($climov as $c) {
    $fecha = (int) ($c->cliv_fecha ?? 0);
    $tipo = strtoupper(trim((string) ($c->cliv_tipo ?? '')));
    $porDiaTipo[$fecha][$tipo]['monto'] = round(($porDiaTipo[$fecha][$tipo]['monto'] ?? 0) + (float) ($c->cliv_monto ?? 0), 2);
    $porDiaTipo[$fecha][$tipo]['n'] = ($porDiaTipo[$fecha][$tipo]['n'] ?? 0) + 1;
}
ksort($porDiaTipo);
foreach ($porDiaTipo as $fecha => $tipos) {
    ksort($tipos);
    echo "  {$fecha}\n";
    foreach ($tipos as $tipo => $v) {
        $marca = abs(abs((float) $v['monto']) - $objetivo) < 0.011 ? '   <<< COINCIDE' : '';
        $flag = in_array($tipo, $soloCc, true) ? ' [solo CC]' : '';
        echo sprintf("     %-5s n=%-5d monto=%16s%s%s\n", $tipo, $v['n'], $fmt((float) $v['monto']), $flag, $marca);
    }
}

// Suma de los tipos que solo existen en CC, por día: candidato natural al descalce.
echo "\n=== Suma diaria de tipos [solo CC] ===\n";
foreach ($porDiaTipo as $fecha => $tipos) {
    $suma = 0.0;
    $detalle = [];
    foreach ($tipos as $tipo => $v) {
        if (in_array($tipo, $soloCc, true)) {
            $suma += (float) $v['monto'];
            $detalle[] = $tipo.'='.$fmt((float) $v['monto']);
        }
    }
    if ($detalle !== []) {
        echo sprintf(
            "  %8d  suma=%16s  (%s)%s\n",
            $fecha,
            $fmt($suma),
            implode(' ', $detalle),
            abs(abs($suma) - $objetivo) < 0.011 ? '   <<< COINCIDE' : '',
        );
    }
}
