<?php

/**
 * Solo lectura: corre el mayor plano por cuenta y muestra cómo queda resuelto el
 * emisor (entidad, código, nombre, CUIT) por línea.
 *
 * php scripts/probe_mayor_emisor_resultado.php [empresaId] [anio] [mes] [cuenta,cuenta]
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Contable\MayorPlanoCuentaReporteService;

$empresaId = (int) ($argv[1] ?? 2);
$anio = (int) ($argv[2] ?? 2026);
$mes = (int) ($argv[3] ?? 1);
$cuentasArg = (string) ($argv[4] ?? '111040001');
$rango = str_contains($cuentasArg, '-') ? explode('-', $cuentasArg, 2) : null;
$cuentas = $rango === null
    ? array_values(array_filter(array_map('intval', explode(',', $cuentasArg))))
    : [];
$soloTotales = (bool) ($argv[5] ?? false);

$filtros = [
    'modo_periodo' => 'mes',
    'mes' => $mes,
    'anio' => $anio,
    'empresa_ids' => [$empresaId],
    'consolidar_empresas' => true,
    'cuentas' => $cuentas,
    'cuenta_desde' => $rango !== null ? (int) $rango[0] : 0,
    'cuenta_hasta' => $rango !== null ? (int) $rango[1] : 0,
    'moneda_id' => 1,
    'solo_moneda_origen' => false,
    'incluye_subdiario' => true,
    'modo_inclusion_asientos' => 'sin_cierre_ni_inflacion',
    'agrupar_por_cc' => false,
];

$service = app(MayorPlanoCuentaReporteService::class);
$resultado = $service->generarDesdeFiltros($filtros);
$filas = $service->aplanarFilas($resultado, [], true);

if (! $soloTotales) {
    printf(
        "%-9s %-10s %-4s %-14s %-10s %-13s %-16s %-30s %s\n",
        'Fecha',
        'N.Asi.',
        'Tip',
        'Comprobante',
        'Entidad',
        'CUIT',
        'Emisor',
        'Nombre emisor',
        'Descripción',
    );
}

$porEntidad = [];
$porTipo = [];
foreach ($filas as $fila) {
    if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
        continue;
    }
    $entidad = (string) ($fila['emisor_entidad'] ?? '');
    $porEntidad[$entidad] = ($porEntidad[$entidad] ?? 0) + 1;
    $claveTipo = strtoupper(trim((string) ($fila['tipo_comp'] ?? ''))).'|'.$entidad
        .'|'.(trim((string) ($fila['emisor_nombre'] ?? '')) !== '' ? 'resuelto' : 'sin nombre');
    $porTipo[$claveTipo] = ($porTipo[$claveTipo] ?? 0) + 1;
    if ($soloTotales) {
        continue;
    }
    printf(
        "%-9s %-10s %-4s %-14s %-10s %-13s %-16s %-30s %s\n",
        (string) ($fila['fecha_fmt'] ?? ''),
        (string) ($fila['nro_asiento_fmt'] ?? ''),
        (string) ($fila['tipo_comp'] ?? ''),
        (string) ($fila['comprobante'] ?? ''),
        $entidad,
        (string) ($fila['cuit'] ?? ''),
        (string) ($fila['emisor'] ?? ''),
        mb_substr((string) ($fila['emisor_nombre'] ?? ''), 0, 30),
        (string) ($fila['descripcion'] ?? ''),
    );
}

echo PHP_EOL.'Líneas por entidad: ';
foreach ($porEntidad as $entidad => $n) {
    echo ($entidad !== '' ? $entidad : 'sin entidad').'='.$n.'  ';
}
echo PHP_EOL.PHP_EOL;

ksort($porTipo);
foreach ($porTipo as $clave => $n) {
    printf("%-40s %6d\n", $clave, $n);
}
