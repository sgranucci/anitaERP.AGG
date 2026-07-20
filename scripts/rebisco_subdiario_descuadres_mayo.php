<?php

/**
 * Diagnóstico SOLO LECTURA: asientos descuadrados del mayor por concepto.
 *
 * Usa el MOTOR real (MayorConceptoReporteService) y su conciliador
 * (MayorConceptoConciliacionAsientoSupport, regla "neto analítico +
 * neto concepto = 0 por asiento") — el mismo que muestra el panel de
 * conciliación del reporte. NO recalcula ni escribe nada.
 *
 * Uso:
 *   php scripts/rebisco_subdiario_descuadres_mayo.php [empresa=3] [mes=5] [anio=2026]
 */

declare(strict_types=1);

use App\Services\Contable\MayorConceptoReporteService;
use App\Support\Contable\MayorConcepto\MayorConceptoRuntimeSupport;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

MayorConceptoRuntimeSupport::elevarLimites();
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '0');

$empresa = (int) ($argv[1] ?? 3);
$mes = (int) ($argv[2] ?? 5);
$anio = (int) ($argv[3] ?? 2026);

$filtros = [
    'empresa_id' => $empresa,
    'empresa_ids' => [$empresa],
    'consolidar_empresas' => true,
    'modo_periodo' => 'mes',
    'mes' => $mes,
    'anio' => $anio,
    'moneda_id' => 1,
    'solo_moneda_origen' => false,
];

/** @var MayorConceptoReporteService $svc */
$svc = app(MayorConceptoReporteService::class);

echo "Generando mayor por concepto · empresa {$empresa} · {$mes}/{$anio}…\n";
$resultado = $svc->generarDesdeFiltros($filtros);

$panel = $svc->armarAuditoriaPanel($resultado);
$conc = $panel['conciliacion'] ?? [];

$fmt = static fn ($n): string => number_format((float) $n, 2, ',', '.');

echo "\n".str_repeat('=', 96)."\n";
echo sprintf(
    "Conciliación por asiento (%s) · tolerancia ±%s\n",
    (string) ($conc['regla'] ?? ''),
    $fmt($conc['tolerancia'] ?? 1.0),
);
echo sprintf(
    "Analizados: %d · Cuadrados: %d · DESCUADRADOS: %d · %% cuadrado: %s\n",
    (int) ($conc['asientos_analizados'] ?? 0),
    (int) ($conc['asientos_cuadrados'] ?? 0),
    (int) ($conc['asientos_descuadrados'] ?? 0),
    $fmt($conc['porcentaje_cuadrado'] ?? 0),
);
echo str_repeat('=', 96)."\n\n";

$filas = $conc['filas_descuadradas'] ?? [];
if ($filas === []) {
    echo "El conciliador no reporta asientos descuadrados.\n";
} else {
    foreach ($filas as $f) {
        echo sprintf(
            "Asiento %d%s · %s · %s\n",
            (int) ($f['nro_asiento'] ?? 0),
            ((int) ($f['asiento_id'] ?? 0) > 0) ? ' (ERP id '.$f['asiento_id'].')' : '',
            (string) ($f['fecha_fmt'] ?? ''),
            (string) ($f['origen'] ?? ''),
        );
        echo sprintf(
            "  Analítico  D %s  H %s  (neto %s)\n",
            $fmt($f['debe_analitico'] ?? 0),
            $fmt($f['haber_analitico'] ?? 0),
            $fmt($f['neto_analitico'] ?? 0),
        );
        echo sprintf(
            "  Concepto   D %s  H %s  (neto %s)\n",
            $fmt($f['debe_concepto'] ?? 0),
            $fmt($f['haber_concepto'] ?? 0),
            $fmt($f['neto_concepto'] ?? 0),
        );
        echo sprintf("  DIFERENCIA: %s\n", $fmt($f['diferencia'] ?? 0));
        if (($f['cuentas_analitico'] ?? '') !== '') {
            echo '  Cuentas analítico: '.$f['cuentas_analitico']."\n";
        }
        if (($f['cuentas_concepto'] ?? '') !== '') {
            echo '  Cuentas concepto:  '.$f['cuentas_concepto']."\n";
        }
        echo str_repeat('-', 96)."\n";
    }
}

$errores = $resultado['errores'] ?? ($resultado['parametros']['errores'] ?? []);
if (is_array($errores) && $errores !== []) {
    echo "\nAvisos/errores del bridge:\n";
    foreach ($errores as $e) {
        echo '  - '.$e."\n";
    }
}

echo "\nListo.\n";
