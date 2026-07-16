<?php

/**
 * Valida cálculos EFE asumiendo mayor por concepto (motor nuevo) como verdad.
 * Uso:
 *   php scripts/efe_validar_vs_mayor.php [excel] [empresa_id] [mes] [anio]
 */

declare(strict_types=1);

use App\Services\Contable\EfeMensualReporteService;
use App\Services\Contable\MayorConceptoReporteService;
use App\Support\Contable\Efe\EfeClasificacionConceptoSupport;
use App\Support\Contable\Efe\EfeComparacionExcelSupport;
use App\Support\Contable\Efe\EfeDatosPagosCobrosSupport;
use App\Support\Contable\EfeMensualListadoFiltros;
use App\Support\Contable\MayorConcepto\MayorConceptoRuntimeSupport;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

MayorConceptoRuntimeSupport::elevarLimites();
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '0');

$rutaExcel = $argv[1] ?? '/home/sergio/tmp/Efe Anita BSA 31.05.26.xlsx';
$filtros = [
    'empresa_id' => (int) ($argv[2] ?? 1),
    'mes' => (int) ($argv[3] ?? 5),
    'anio' => (int) ($argv[4] ?? 2026),
    'moneda_id' => 1,
    'solo_moneda_origen' => false,
];

/** @var MayorConceptoReporteService $mayorSvc */
$mayorSvc = app(MayorConceptoReporteService::class);
/** @var EfeMensualReporteService $efeSvc */
$efeSvc = app(EfeMensualReporteService::class);
/** @var EfeClasificacionConceptoSupport $clasif */
$clasif = app(EfeClasificacionConceptoSupport::class);
/** @var EfeDatosPagosCobrosSupport $pagosCobros */
$pagosCobros = app(EfeDatosPagosCobrosSupport::class);
/** @var EfeComparacionExcelSupport $cmp */
$cmp = app(EfeComparacionExcelSupport::class);

echo "Generando mayor por concepto…\n";
$resultadoMayor = $mayorSvc->generarDesdeFiltros(
    EfeMensualListadoFiltros::filtrosParaMayorConcepto($filtros)
);
$auditoria = $mayorSvc->armarAuditoriaPanel($resultadoMayor);
$lineasMayor = $mayorSvc->aplanarFilas($resultadoMayor);

$nombres = [];
foreach (DB::table('conceptogasto')->get(['id', 'nombre']) as $row) {
    $nombres[(int) $row->id] = (string) ($row->nombre ?? '');
}

/** Base: mapeo directo mayor → EFE sin post-procesos Datos */
$basePorConcepto = [];
$omitidas = 0;
$detalleMayor = 0;
foreach ($lineasMayor as $ln) {
    if (($ln['tipo_fila'] ?? 'detalle') !== 'detalle') {
        continue;
    }
    $detalleMayor++;

    $conceptoId = $clasif->resolverConceptoId($ln);
    if ($conceptoId === null) {
        $omitidas++;
        continue;
    }
    if ($conceptoId === 63 && (int) ($ln['cuenta'] ?? 0) === 114010002) {
        $conceptoId = 0;
    }
    $importes = $pagosCobros->resolver($ln);
    if ($importes === null) {
        $omitidas++;
        continue;
    }
    if (in_array($conceptoId, [0, 63], true)) {
        continue;
    }

    if (! isset($basePorConcepto[$conceptoId])) {
        $basePorConcepto[$conceptoId] = ['pagos' => 0.0, 'cobros' => 0.0, 'n' => 0];
    }
    $basePorConcepto[$conceptoId]['pagos'] += (float) ($importes['pagos'] ?? 0);
    $basePorConcepto[$conceptoId]['cobros'] += (float) ($importes['cobros'] ?? 0);
    $basePorConcepto[$conceptoId]['n']++;
}

foreach ($basePorConcepto as $cid => $row) {
    $basePorConcepto[$cid]['neto'] = round($row['cobros'] - $row['pagos'], 2);
    $basePorConcepto[$cid]['pagos'] = round($row['pagos'], 2);
    $basePorConcepto[$cid]['cobros'] = round($row['cobros'], 2);
}

// Rollup impuestos igual que EFE
$rollup = [58, 59, 61, 63];
$rollupNeto = 0.0;
$rollupPagos = 0.0;
$rollupCobros = 0.0;
foreach ($rollup as $cid) {
    if (! isset($basePorConcepto[$cid])) {
        continue;
    }
    $rollupNeto += $basePorConcepto[$cid]['neto'];
    $rollupPagos += $basePorConcepto[$cid]['pagos'];
    $rollupCobros += $basePorConcepto[$cid]['cobros'];
    unset($basePorConcepto[$cid]);
}
if (isset($basePorConcepto[8]) && (abs($rollupNeto) > 0.005 || abs($rollupPagos) > 0.005)) {
    $basePorConcepto[8]['neto'] = round($basePorConcepto[8]['neto'] + $rollupNeto, 2);
    $basePorConcepto[8]['pagos'] = round($basePorConcepto[8]['pagos'] + $rollupPagos, 2);
    $basePorConcepto[8]['cobros'] = round($basePorConcepto[8]['cobros'] + $rollupCobros, 2);
}

echo "Generando EFE completo (con post-procesos)…\n";
$resultadoEfe = $efeSvc->generarDesdeFiltros($filtros);
$efePorConcepto = [];
foreach ($resultadoEfe['resumen_pagos'] ?? [] as $fila) {
    $cid = (int) ($fila['concepto_id'] ?? 0);
    $efePorConcepto[$cid] = [
        'neto' => (float) ($fila['neto'] ?? 0),
        'pagos' => (float) ($fila['pagos'] ?? 0),
        'cobros' => (float) ($fila['cobros'] ?? 0),
        'n' => (int) ($fila['cantidad_lineas'] ?? 0),
    ];
}

// Consistencia Resumen = sum Datos
$sumaDatos = [];
foreach ($resultadoEfe['filas_datos'] ?? [] as $fila) {
    $cid = (int) ($fila['concepto_id'] ?? 0);
    if (! isset($sumaDatos[$cid])) {
        $sumaDatos[$cid] = ['pagos' => 0.0, 'cobros' => 0.0, 'n' => 0];
    }
    $sumaDatos[$cid]['pagos'] += (float) ($fila['pagos'] ?? 0);
    $sumaDatos[$cid]['cobros'] += (float) ($fila['cobros'] ?? 0);
    $sumaDatos[$cid]['n']++;
}
foreach ($sumaDatos as $cid => $row) {
    $sumaDatos[$cid]['neto'] = round($row['cobros'] - $row['pagos'], 2);
}

$inconsistenciasResumen = [];
$ids = array_unique(array_merge(array_keys($efePorConcepto), array_keys($sumaDatos)));
foreach ($ids as $cid) {
    if (in_array($cid, $rollup, true)) {
        continue;
    }
    $netoDatos = (float) ($sumaDatos[$cid]['neto'] ?? 0);
    $netoRes = (float) ($efePorConcepto[$cid]['neto'] ?? 0);
    // Concepto 8: Datos aún tiene 58/59/61/63; resumen ya rollupea.
    if ($cid === 8) {
        foreach ($rollup as $r) {
            $netoDatos += (float) ($sumaDatos[$r]['neto'] ?? 0);
        }
        $netoDatos = round($netoDatos, 2);
    }
    $diff = round($netoRes - $netoDatos, 2);
    if (abs($diff) > 0.05) {
        $inconsistenciasResumen[] = compact('cid', 'netoDatos', 'netoRes', 'diff');
    }
}

$excelRef = is_file($rutaExcel) ? $cmp->leerReferenciaResumenPagos($rutaExcel) : [];

echo "\n========== AUDITORÍA MAYOR ==========\n";
$conc = $auditoria['conciliacion'] ?? [];
echo sprintf(
    "cuadra=%s · asientos=%d · descuadrados=%d\n",
    ! empty($auditoria['cuadra']) ? 'SI' : 'NO',
    (int) ($conc['asientos_analizados'] ?? 0),
    (int) ($conc['asientos_descuadrados'] ?? 0),
);

echo "\n========== CONSISTENCIA INTERNA EFE ==========\n";
echo "Líneas mayor detalle: {$detalleMayor}\n";
echo "Líneas Datos EFE: ".count($resultadoEfe['filas_datos'] ?? [])."\n";
echo "Omitidas en mapeo base (sin concepto/importes): {$omitidas}\n";
if ($inconsistenciasResumen === []) {
    echo "Resumen de pagos = agregación Datos (+ rollup 8): OK\n";
} else {
    echo "INCONSISTENCIAS Resumen vs Datos:\n";
    foreach ($inconsistenciasResumen as $row) {
        echo "  C{$row['cid']}: Datos {$row['netoDatos']} Resumen {$row['netoRes']} Δ {$row['diff']}\n";
    }
}

$sumarias = $cmp->compararSumariasTotal($resultadoEfe['sumarias'] ?? [], $rutaExcel);
echo sprintf(
    "Sumarias E68: ERP %s · Excel %s · Δ %s\n",
    $sumarias['total_e68'],
    $sumarias['excel_e68'],
    $sumarias['diff'],
);

echo "\n========== POST-PROCESOS EFE vs MAYOR DIRECTO ==========\n";
echo "(Δ post = neto EFE final − neto mapeo directo del mayor; |Δ|>1 ⇒ el EFE altera el mayor)\n";
$postDiffs = [];
$ids = array_unique(array_merge(array_keys($basePorConcepto), array_keys($efePorConcepto)));
sort($ids);
foreach ($ids as $cid) {
    $base = (float) ($basePorConcepto[$cid]['neto'] ?? 0);
    $efe = (float) ($efePorConcepto[$cid]['neto'] ?? 0);
    $diff = round($efe - $base, 2);
    if (abs($diff) <= 1.0 && abs($base) < 0.005 && abs($efe) < 0.005) {
        continue;
    }
    if (abs($diff) > 1.0) {
        $postDiffs[] = [
            'cid' => $cid,
            'nombre' => $nombres[$cid] ?? '',
            'base' => $base,
            'efe' => $efe,
            'diff' => $diff,
        ];
    }
}
usort($postDiffs, fn ($a, $b) => abs($b['diff']) <=> abs($a['diff']));
foreach (array_slice($postDiffs, 0, 25) as $row) {
    echo sprintf(
        "C%-3d %-28s mayor→EFE %s · EFE %s · post Δ %s\n",
        $row['cid'],
        mb_substr($row['nombre'], 0, 28),
        number_format($row['base'], 2, '.', ','),
        number_format($row['efe'], 2, '.', ','),
        number_format($row['diff'], 2, '.', ','),
    );
}
if ($postDiffs === []) {
    echo "Ningún post-proceso altera netos > \$1 (salvo ruido).\n";
}

echo "\n========== DESVÍOS VS EXCEL (clasificados) ==========\n";
echo "Leyenda:\n";
echo "  ESPERADO_MOTOR = Excel Anita ≠ mayor nuevo; EFE sigue al mayor (post≈0)\n";
echo "  AJUSTE_EFE     = EFE post-proceso cambia el mayor (revisar si sigue haciendo falta)\n";
echo "  BUG_CALCULO    = Resumen ≠ Datos, o Sumarias, u otra inconsistencia interna\n";
echo "  REVISAR_CONTAD = |Excel−EFE| grande y no explicado solo por motor vs post\n\n";

$filasCmp = [];
$ids = array_unique(array_merge(array_keys($excelRef), array_keys($efePorConcepto), array_keys($basePorConcepto)));
sort($ids);
foreach ($ids as $cid) {
    if (in_array($cid, $rollup, true)) {
        continue;
    }
    $excel = (float) ($excelRef[$cid] ?? 0);
    $efe = (float) ($efePorConcepto[$cid]['neto'] ?? 0);
    $base = (float) ($basePorConcepto[$cid]['neto'] ?? 0);
    $diffExcelEfe = round($efe - $excel, 2);
    $diffExcelBase = round($base - $excel, 2);
    $diffPost = round($efe - $base, 2);

    if (abs($excel) < 0.005 && abs($efe) < 0.005 && abs($base) < 0.005) {
        continue;
    }
    if (abs($diffExcelEfe) <= 1.0) {
        continue; // coincide
    }

    $clase = 'REVISAR_CONTAD';
    if (abs($diffPost) <= 1.0 && abs($diffExcelBase) > 1.0) {
        // EFE ≈ mayor y ambos ≠ Excel → diferencia de motor Anita vs nuevo
        $clase = 'ESPERADO_MOTOR';
    } elseif (abs($diffPost) > 1.0) {
        // Post-proceso mueve el neto
        // Si el post acerca a Excel: AJUSTE_EFE (legacy Anita)
        // Si aleja de Excel y de mayor: más raro
        $clase = 'AJUSTE_EFE';
    }

    $filasCmp[] = [
        'cid' => $cid,
        'nombre' => $nombres[$cid] ?? '',
        'excel' => $excel,
        'mayor' => $base,
        'efe' => $efe,
        'd_excel_efe' => $diffExcelEfe,
        'd_excel_mayor' => $diffExcelBase,
        'd_post' => $diffPost,
        'clase' => $clase,
    ];
}
usort($filasCmp, fn ($a, $b) => abs($b['d_excel_efe']) <=> abs($a['d_excel_efe']));

$porClase = [];
foreach ($filasCmp as $row) {
    $porClase[$row['clase']] = ($porClase[$row['clase']] ?? 0) + 1;
    echo sprintf(
        "[%s] C%-3d %-26s Excel %s · mayor %s · EFE %s · ΔEFE %s · post %s\n",
        $row['clase'],
        $row['cid'],
        mb_substr($row['nombre'], 0, 26),
        number_format($row['excel'], 2, '.', ','),
        number_format($row['mayor'], 2, '.', ','),
        number_format($row['efe'], 2, '.', ','),
        number_format($row['d_excel_efe'], 2, '.', ','),
        number_format($row['d_post'], 2, '.', ','),
    );
}

echo "\n========== RESUMEN PARA CONTADURÍA ==========\n";
echo 'Desvíos Excel vs EFE (>\$1): '.count($filasCmp)."\n";
foreach ($porClase as $k => $n) {
    echo "  {$k}: {$n}\n";
}
if ($inconsistenciasResumen !== []) {
    echo "BUG_CALCULO interno: ".count($inconsistenciasResumen)." conceptos\n";
}
echo sprintf(
    "Sumarias: %s\n",
    abs((float) $sumarias['diff']) <= 0.05 ? 'OK (coincide Excel)' : 'Δ '.$sumarias['diff'],
);
echo sprintf(
    "Auditoría mayor: %s\n",
    ! empty($auditoria['cuadra']) ? 'CUADRA' : 'CON DESCUADRES',
);

echo "\nConceptos a enviar a contaduría (ESPERADO_MOTOR + REVISAR_CONTAD + AJUSTE_EFE grandes):\n";
foreach (array_slice($filasCmp, 0, 20) as $row) {
    if ($row['clase'] === 'ESPERADO_MOTOR' || abs($row['d_excel_efe']) > 100000) {
        echo sprintf(
            "  C%d %s | Excel %s | ERP(EFE) %s | Δ %s | clase %s\n",
            $row['cid'],
            $row['nombre'],
            number_format($row['excel'], 2, ',', '.'),
            number_format($row['efe'], 2, ',', '.'),
            number_format($row['d_excel_efe'], 2, ',', '.'),
            $row['clase'],
        );
    }
}

echo "\nListo.\n";
