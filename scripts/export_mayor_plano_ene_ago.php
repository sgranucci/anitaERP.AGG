<?php

/**
 * Exporta mayor plano desde cache en disco (o regenera) a CSV público.
 * Uso: php -d memory_limit=4096M scripts/export_mayor_plano_ene_ago.php
 */

declare(strict_types=1);

use App\Services\Contable\MayorPlanoCuentaReporteService;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaCacheSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaRuntimeSupport;
use App\Support\Contable\MayorPlanoCuentaListadoFiltros;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

MayorPlanoCuentaRuntimeSupport::elevarLimites();
@ini_set('memory_limit', '4096M');
@set_time_limit(0);
ignore_user_abort(true);

$filtros = [
    'empresa_ids' => [1, 2, 3],
    'consolidar_empresas' => true,
    'moneda_id' => 1,
    'modo_periodo' => 'rango',
    'mes' => 0,
    'anio' => 0,
    'fecha_desde' => '2026-01-01',
    'fecha_hasta' => '2026-08-31',
    'solo_moneda_origen' => false,
    'incluye_subdiario' => true,
    'modo_inclusion_asientos' => 'sin_cierre_ni_inflacion',
    'cuenta_desde' => 0,
    'cuenta_hasta' => 0,
    'cuentas' => [],
    'filtro_texto' => '',
    'centrocostos_codigo' => '',
    'cc_desde' => '',
    'cc_hasta' => '',
    'incluir_sin_cc' => null,
    'agrupar_por_cc' => false,
    'solo_movimientos_ventas' => false,
    'mostrar_columna_centrocosto' => true,
    'excel_solapas_separadas' => false,
];

$outDir = storage_path('app/public/exports');
if (! is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

$stamp = date('Ymd_His');
$outPath = $outDir.'/mayor_plano_20260101_20260831_emp1-2-3_'.$stamp.'.csv';
$statusPath = $outDir.'/mayor_plano_20260101_20260831.status.txt';

$writeStatus = static function (string $msg) use ($statusPath): void {
    file_put_contents($statusPath, date('Y-m-d H:i:s').' '.$msg.PHP_EOL, FILE_APPEND);
    echo date('H:i:s').' '.$msg.PHP_EOL;
};

$writeStatus('START firma='.MayorPlanoCuentaListadoFiltros::firma($filtros));

/** @var MayorPlanoCuentaReporteService $svc */
$svc = app(MayorPlanoCuentaReporteService::class);

// Preferir cache ya generado (user 2 / misma firma de negocio sin user en filtros).
$cacheDirs = glob(storage_path('framework/cache/data/mayor_plano/mayor_plano_cuenta_v6_*')) ?: [];
$resultado = null;
foreach ($cacheDirs as $dir) {
    $metaPath = $dir.'/meta.gz';
    if (! is_file($metaPath)) {
        continue;
    }
    $raw = @file_get_contents($metaPath);
    if ($raw === false) {
        continue;
    }
    $pack = @unserialize((string) @gzuncompress($raw));
    if (! is_array($pack) || ! isset($pack['resultado'], $pack['seccion_count'])) {
        continue;
    }
    if ((int) ($pack['expires_at'] ?? 0) < time()) {
        continue;
    }
    $lineas = (int) ($pack['resultado']['totales']['lineas'] ?? 0);
    if ($lineas < 100000) {
        continue; // quedarse con el ene–ago grande
    }
    $writeStatus('Usando cache '.$dir.' lineas='.$lineas);
    $secciones = [];
    $count = (int) $pack['seccion_count'];
    for ($i = 0; $i < $count; $i++) {
        $path = $dir.'/s'.sprintf('%05d', $i).'.gz';
        $sec = @unserialize((string) @gzuncompress((string) file_get_contents($path)));
        if (! is_array($sec)) {
            $writeStatus('ERROR seccion '.$i);

            exit(1);
        }
        $secciones[] = $sec;
    }
    $resultado = $pack['resultado'];
    $resultado['secciones'] = $secciones;
    break;
}

if ($resultado === null) {
    $writeStatus('Cache grande no encontrado; regenerando vía bridge (puede demorar ~5 min)…');
    $resultado = $svc->generarDesdeFiltros($filtros);
    MayorPlanoCuentaCacheSupport::guardar($resultado, $filtros);
    $writeStatus('Generado lineas='.(int) ($resultado['totales']['lineas'] ?? 0));
}

$mostrarCc = true;
$headers = [
    'Empresa', 'Nro.Asi.', 'Fecha', 'Cuenta', 'Descripcion', 'C.Costo', 'Mon', 'Cotizacion',
    'Debe', 'Haber', 'Detalle', 'Cod. emisor', 'Nombre emisor', 'Usuario', 'fecha ult. mod',
    'O.Compra', 'proyecto CAPEX', 'Que se compro (OC)', 'Numeros de Facturas',
];

$out = fopen($outPath, 'w');
if ($out === false) {
    $writeStatus('ERROR no se pudo crear '.$outPath);

    exit(1);
}
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, $headers, ';');

$n = 0;
$t0 = microtime(true);
$writeStatus('Exportando CSV…');
foreach ($svc->iterarMovimientosExcelPlano($resultado, $filtros, 1500) as $fila) {
    $row = [
        $fila['empresa_id'] ?? '',
        $fila['nro_asiento_fmt'] ?? $fila['nro_asiento'] ?? '',
        $fila['fecha_fmt'] ?? '',
        $fila['cuenta_codigo'] ?? '',
        $fila['cuenta_nombre'] ?? '',
        $fila['centrocosto_codigo'] ?? '',
        $fila['moneda_abrev'] ?? '',
        $fila['cotizacion'] ?? '',
        $fila['debe'] ?? '',
        $fila['haber'] ?? '',
        $fila['descripcion'] ?? '',
        $fila['emisor'] ?? '',
        $fila['emisor_nombre'] ?? '',
        $fila['usuario'] ?? '',
        $fila['fecha_ult_mod'] ?? '',
        ((int) ($fila['nro_oc'] ?? 0) > 0) ? $fila['nro_oc'] : '',
        $fila['proyecto_capex'] ?? '',
        $fila['observacion_oc'] ?? '',
        $fila['numeros_facturas'] ?? '',
    ];
    fputcsv($out, $row, ';');
    $n++;
    if ($n % 10000 === 0) {
        fflush($out);
        $writeStatus('progreso filas='.$n.' mem_mb='.round(memory_get_usage(true) / 1048576, 1));
    }
}
fclose($out);
@chmod($outPath, 0664);

$secs = round(microtime(true) - $t0, 1);
$sizeMb = round(filesize($outPath) / 1048576, 2);
$writeStatus('DONE filas='.$n.' secs='.$secs.' size_mb='.$sizeMb.' file='.$outPath);

$latest = $outDir.'/mayor_plano_20260101_20260831_LATEST.csv';
@unlink($latest);
@symlink(basename($outPath), $latest);
$writeStatus('LATEST -> '.basename($outPath));

exit(0);
