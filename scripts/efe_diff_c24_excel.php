<?php

/**
 * Compara C24 EFE vs Excel por asiento (JSON de scripts/python previo).
 * Uso: php scripts/efe_diff_c24_excel.php [empresa] [mes] [anio]
 */

declare(strict_types=1);

use App\Services\Contable\EfeMensualReporteService;
use App\Support\Contable\MayorConcepto\MayorConceptoRuntimeSupport;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

MayorConceptoRuntimeSupport::elevarLimites();
ini_set('memory_limit', '-1');

$filtros = [
    'empresa_id' => (int) ($argv[1] ?? 1),
    'mes' => (int) ($argv[2] ?? 5),
    'anio' => (int) ($argv[3] ?? 2026),
    'moneda_id' => 1,
    'solo_moneda_origen' => false,
];

$excelPath = '/tmp/excel_c24_by_asi.json';
if (! is_file($excelPath)) {
    fwrite(STDERR, "Falta $excelPath — generar con python Datos C24\n");
    exit(1);
}
/** @var array<string, float> $excel */
$excel = json_decode((string) file_get_contents($excelPath), true, 512, JSON_THROW_ON_ERROR);

echo "Generando EFE…\n";
$efe = app(EfeMensualReporteService::class)->generarDesdeFiltros($filtros);
$erp = [];
$erpCta = [];
foreach ($efe['filas_datos'] as $f) {
    if ((int) ($f['concepto_id'] ?? 0) !== 24) {
        continue;
    }
    $asi = (int) ($f['nro_asiento'] ?? 0);
    $neto = round((float) ($f['cobros'] ?? 0) - (float) ($f['pagos'] ?? 0), 2);
    $erp[$asi] = ($erp[$asi] ?? 0) + $neto;
    $cta = (string) ($f['cuenta_codigo'] !== '' ? $f['cuenta_codigo'] : $f['cuenta']);
    $erpCta[$cta] = ($erpCta[$cta] ?? 0) + $neto;
}

$asientos = array_unique(array_merge(array_map('intval', array_keys($excel)), array_keys($erp)));
sort($asientos);

$diffs = [];
$sumExcel = 0.0;
$sumErp = 0.0;
foreach ($asientos as $asi) {
    $ex = round((float) ($excel[(string) $asi] ?? 0), 2);
    $er = round((float) ($erp[$asi] ?? 0), 2);
    $sumExcel += $ex;
    $sumErp += $er;
    $d = round($er - $ex, 2);
    if (abs($d) > 0.02) {
        $diffs[] = ['asi' => $asi, 'excel' => $ex, 'erp' => $er, 'delta' => $d];
    }
}
usort($diffs, fn ($a, $b) => abs($b['delta']) <=> abs($a['delta']));

echo sprintf(
    "C24 neto Excel=%s ERP=%s Δ=%s · asientos con Δ: %d\n",
    number_format($sumExcel, 2, '.', ','),
    number_format($sumErp, 2, '.', ','),
    number_format($sumErp - $sumExcel, 2, '.', ','),
    count($diffs)
);
echo "Top 25 Δ (ERP−Excel):\n";
foreach (array_slice($diffs, 0, 25) as $d) {
    echo sprintf(
        "  asi %d Excel %s ERP %s Δ %s\n",
        $d['asi'],
        number_format($d['excel'], 2, '.', ','),
        number_format($d['erp'], 2, '.', ','),
        number_format($d['delta'], 2, '.', ',')
    );
}

echo "\nERP C24 por cuenta:\n";
arsort($erpCta);
foreach ($erpCta as $cta => $neto) {
    echo sprintf("  %s: %s\n", $cta, number_format($neto, 2, '.', ','));
}

echo "\nListo.\n";
