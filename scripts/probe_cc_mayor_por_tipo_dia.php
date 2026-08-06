<?php

/**
 * Reconciliación día × tipo de comprobante entre climov (CC) y subdiario (mayor de deudores),
 * enteramente sobre el cache local. No consulta el bridge.
 *
 * Uso: php scripts/probe_cc_mayor_por_tipo_dia.php [cuenta=113100000] [objetivo=3732.89]
 */

declare(strict_types=1);

use App\Support\Contable\Anita\AnitaSubdiarioMayorSupport;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

ini_set('memory_limit', '-1');

$cuenta = (int) ($argv[1] ?? (int) config('cliente.DEUDORES_POR_VENTAS'));
$objetivo = (float) ($argv[2] ?? 3732.89);
$cacheDir = storage_path('app/probe_cc_mayor');

$fmt = static fn (float $n): string => number_format($n, 2, ',', '.');
$tiposHaber = ['NCD', 'NCK', 'NCE', 'NCP', 'REC', 'COB', 'COA', 'ANT', 'RBO', 'AJU'];

// --- Mayor por (dia, tipo) desde subdiario cacheado ---
$mayor = [];
foreach (glob($cacheDir.'/subdiario_'.$cuenta.'_*.json') ?: [] as $archivo) {
    foreach (json_decode((string) file_get_contents($archivo), false) ?: [] as $f) {
        $fecha = (int) ($f->subd_fecha ?? 0);
        $tipo = strtoupper(trim((string) ($f->subd_tipo ?? ''))) ?: '(vacio)';
        foreach (AnitaSubdiarioMayorSupport::imputacionesLineaSubdiario($f) as $imp) {
            if ((int) $imp['cuenta'] !== $cuenta) {
                continue;
            }
            $dh = AnitaSubdiarioMayorSupport::debeHaberDesdeDh((string) $imp['dh'], (float) $imp['importe']);
            $neto = (float) ($dh['debe'] ?? 0) - (float) ($dh['haber'] ?? 0);
            $mayor[$fecha][$tipo] = round(($mayor[$fecha][$tipo] ?? 0) + $neto, 2);
        }
    }
}

// --- CC por (dia, tipo) desde climov agregado cacheado ---
$cc = [];
foreach (json_decode((string) file_get_contents($cacheDir.'/climov_por_dia_tipo.json'), false) ?: [] as $f) {
    $fecha = (int) ($f->cliv_fecha ?? 0);
    $tipo = strtoupper(trim((string) ($f->cliv_tipo ?? ''))) ?: '(vacio)';
    $monto = (float) ($f->monto ?? 0);
    $signo = in_array($tipo, $tiposHaber, true) ? -1 : 1;
    $cc[$fecha][$tipo] = round(($cc[$fecha][$tipo] ?? 0) + $signo * $monto, 2);
}

// Solo el período en que el subdiario tiene datos reales para la cuenta.
$fechasMayor = array_keys($mayor);
sort($fechasMayor);
$desde = 20250101;
foreach ($fechasMayor as $f) {
    if ($f >= 20250101) {
        $desde = $f;
        break;
    }
}
echo "cuenta={$cuenta} objetivo={$objetivo} periodo_desde={$desde}\n";

// --- Totales por tipo dentro del período ---
$totTipo = [];
foreach ($mayor as $fecha => $porTipo) {
    if ($fecha < $desde) {
        continue;
    }
    foreach ($porTipo as $tipo => $v) {
        $totTipo[$tipo]['mayor'] = round(($totTipo[$tipo]['mayor'] ?? 0) + $v, 2);
    }
}
foreach ($cc as $fecha => $porTipo) {
    if ($fecha < $desde) {
        continue;
    }
    foreach ($porTipo as $tipo => $v) {
        $totTipo[$tipo]['cc'] = round(($totTipo[$tipo]['cc'] ?? 0) + $v, 2);
    }
}

echo "\n=== TOTALES DEL PERÍODO POR TIPO (CC vs MAYOR) ===\n";
uasort($totTipo, static fn ($a, $b) => abs(($b['cc'] ?? 0) - ($b['mayor'] ?? 0)) <=> abs(($a['cc'] ?? 0) - ($a['mayor'] ?? 0)));
foreach ($totTipo as $tipo => $v) {
    $ccv = (float) ($v['cc'] ?? 0);
    $myv = (float) ($v['mayor'] ?? 0);
    echo sprintf(
        "  %-8s CC=%20s MAYOR=%20s DIFF=%20s%s\n",
        $tipo,
        $fmt($ccv),
        $fmt($myv),
        $fmt($ccv - $myv),
        abs(abs($ccv - $myv) - $objetivo) < 0.011 ? '  <<< COINCIDE' : '',
    );
}

// --- Diferencias por (dia, tipo) que coincidan con el objetivo ---
echo "\n=== (DÍA, TIPO) CON DIFERENCIA = {$objetivo} ===\n";
$hits = 0;
$fechas = array_unique(array_merge(array_keys($mayor), array_keys($cc)));
sort($fechas);
foreach ($fechas as $fecha) {
    if ($fecha < $desde) {
        continue;
    }
    $tipos = array_unique(array_merge(array_keys($mayor[$fecha] ?? []), array_keys($cc[$fecha] ?? [])));
    foreach ($tipos as $tipo) {
        $diff = round(((float) ($cc[$fecha][$tipo] ?? 0)) - ((float) ($mayor[$fecha][$tipo] ?? 0)), 2);
        if (abs(abs($diff) - $objetivo) < 0.011) {
            $hits++;
            echo sprintf(
                "  %8d %-8s CC=%18s MAYOR=%18s DIFF=%14s\n",
                $fecha,
                $tipo,
                $fmt((float) ($cc[$fecha][$tipo] ?? 0)),
                $fmt((float) ($mayor[$fecha][$tipo] ?? 0)),
                $fmt($diff),
            );
        }
    }
}
if ($hits === 0) {
    echo "  (ninguna)\n";
}

// --- Diferencia diaria total (tipos presentes en ambos lados) ---
$comunes = [];
foreach ($totTipo as $tipo => $v) {
    if (isset($v['cc'], $v['mayor'])) {
        $comunes[] = $tipo;
    }
}
echo "\n=== DÍAS CON DIFERENCIA = {$objetivo} (tipos comunes: ".implode(',', $comunes).") ===\n";
$hits = 0;
foreach ($fechas as $fecha) {
    if ($fecha < $desde) {
        continue;
    }
    $diff = 0.0;
    foreach ($comunes as $tipo) {
        $diff += ((float) ($cc[$fecha][$tipo] ?? 0)) - ((float) ($mayor[$fecha][$tipo] ?? 0));
    }
    $diff = round($diff, 2);
    if (abs(abs($diff) - $objetivo) < 0.011) {
        $hits++;
        echo sprintf("  %8d  DIFF=%s\n", $fecha, $fmt($diff));
    }
}
if ($hits === 0) {
    echo "  (ninguna)\n";
}

// --- Acumulado: ¿en qué día el desvío acumulado pasa por el objetivo? ---
echo "\n=== ACUMULADO DE DIFERENCIA (tipos comunes) — últimos 25 días ===\n";
$acum = 0.0;
$serie = [];
foreach ($fechas as $fecha) {
    if ($fecha < $desde) {
        continue;
    }
    $diff = 0.0;
    foreach ($comunes as $tipo) {
        $diff += ((float) ($cc[$fecha][$tipo] ?? 0)) - ((float) ($mayor[$fecha][$tipo] ?? 0));
    }
    $acum = round($acum + $diff, 2);
    $serie[] = ['fecha' => $fecha, 'diff' => round($diff, 2), 'acum' => $acum];
}
foreach (array_slice($serie, -25) as $s) {
    echo sprintf("  %8d  dia=%16s  acum=%18s\n", $s['fecha'], $fmt($s['diff']), $fmt($s['acum']));
}
