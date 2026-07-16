<?php

/**
 * Mide neto C24 tras cada post-proceso EFE.
 * Uso: php scripts/efe_auditar_c24_cadena.php [empresa] [mes] [anio]
 */

declare(strict_types=1);

use App\Services\Contable\MayorConceptoReporteService;
use App\Support\Contable\Efe\EfeClasificacionConceptoSupport;
use App\Support\Contable\Efe\EfeDatosBienesUsoSupport;
use App\Support\Contable\Efe\EfeDatosExcluirIvaOppGastoSupport;
use App\Support\Contable\Efe\EfeDatosGamingSuppliesSupport;
use App\Support\Contable\Efe\EfeDatosGastronomiaSupport;
use App\Support\Contable\Efe\EfeDatosMantenimientoEdificioSupport;
use App\Support\Contable\Efe\EfeDatosOppGastoComSupport;
use App\Support\Contable\Efe\EfeDatosPagosCobrosSupport;
use App\Support\Contable\Efe\EfeDatosReimputaAnticipoSupport;
use App\Support\Contable\Efe\EfeDatosVariosSupport;
use App\Support\Contable\EfeMensualListadoFiltros;
use App\Support\Contable\MayorConcepto\MayorConceptoRuntimeSupport;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

MayorConceptoRuntimeSupport::elevarLimites();
ini_set('memory_limit', '-1');
ini_set('max_execution_time', '0');

$filtros = [
    'empresa_id' => (int) ($argv[1] ?? 1),
    'mes' => (int) ($argv[2] ?? 5),
    'anio' => (int) ($argv[3] ?? 2026),
    'moneda_id' => 1,
    'solo_moneda_origen' => false,
];

$mayorSvc = app(MayorConceptoReporteService::class);
$clasif = app(EfeClasificacionConceptoSupport::class);
$pagosCobros = app(EfeDatosPagosCobrosSupport::class);

$nombres = [];
foreach (DB::table('conceptogasto')->get(['id', 'nombre']) as $row) {
    $nombres[(int) $row->id] = (string) ($row->nombre ?? '');
}

echo "Generando mayor…\n";
$resultadoMayor = $mayorSvc->generarDesdeFiltros(
    EfeMensualListadoFiltros::filtrosParaMayorConcepto($filtros)
);

$filas = [];
$empresaId = (int) $filtros['empresa_id'];
foreach ($mayorSvc->aplanarFilas($resultadoMayor) as $ln) {
    if (($ln['tipo_fila'] ?? 'detalle') !== 'detalle') {
        continue;
    }
    $conceptoId = $clasif->resolverConceptoId($ln);
    if ($conceptoId === null) {
        continue;
    }
    if ($conceptoId === 63 && (int) ($ln['cuenta'] ?? 0) === 114010002) {
        $conceptoId = 0;
    }
    $importes = $pagosCobros->resolver($ln);
    if ($importes === null || in_array($conceptoId, [0, 63], true)) {
        continue;
    }
    $filas[] = [
        'clasificacion_efe' => $clasif->formatearClave($conceptoId, $nombres[$conceptoId] ?? ''),
        'cuenta' => (int) ($ln['cuenta'] ?? 0),
        'cuenta_disponibilidad' => (int) ($ln['cuenta_disponibilidad'] ?? 0),
        'cuenta_codigo' => (string) ($ln['cuenta_codigo'] ?? ''),
        'cuenta_nombre' => (string) ($ln['cuenta_nombre'] ?? ''),
        'fecha' => (int) ($ln['fecha'] ?? 0),
        'fecha_fmt' => (string) ($ln['fecha_fmt'] ?? ''),
        'nro_asiento' => (int) ($ln['nro_asiento'] ?? 0),
        'tipo_comp' => (string) ($ln['tipo_comp'] ?? ''),
        'comprobante' => (string) ($ln['comprobante'] ?? ''),
        'cheque' => (string) ($ln['cheque'] ?? ''),
        'nro_oc' => (int) ($ln['nro_oc'] ?? 0),
        'descripcion' => (string) ($ln['descripcion'] ?? ''),
        'moneda_abrev' => (string) ($ln['moneda_abrev'] ?? ''),
        'cotizacion' => (float) ($ln['cotizacion'] ?? 0),
        'mon_referencia' => null,
        'pagos' => $importes['pagos'],
        'cobros' => $importes['cobros'],
        'empresa_id' => $empresaId,
        'concepto_id' => $conceptoId,
        'concepto_nombre' => $nombres[$conceptoId] ?? '',
        'origen' => (string) ($ln['origen'] ?? ''),
    ];
}

$netoC = static function (array $filas, int $cid): array {
    $n = 0;
    $neto = 0.0;
    foreach ($filas as $f) {
        if ((int) ($f['concepto_id'] ?? 0) !== $cid) {
            continue;
        }
        $n++;
        $neto += (float) ($f['cobros'] ?? 0) - (float) ($f['pagos'] ?? 0);
    }

    return ['n' => $n, 'neto' => round($neto, 2)];
};

$pasos = [
    '0_base_mayor' => null,
    '1_gaming' => fn ($f) => app(EfeDatosGamingSuppliesSupport::class)->aplicar($f, $filtros, $nombres),
    '2_mant_edificio' => fn ($f) => app(EfeDatosMantenimientoEdificioSupport::class)->aplicar($f, $filtros, $nombres),
    '3_varios' => fn ($f) => app(EfeDatosVariosSupport::class)->aplicar($f, $filtros, $nombres),
    '4_opp_gasto_com' => fn ($f) => app(EfeDatosOppGastoComSupport::class)->aplicar($f, $filtros, $nombres),
    '5_reimputa_anticipo' => fn ($f) => app(EfeDatosReimputaAnticipoSupport::class)->aplicar($f, $nombres),
    '6_gastronomia' => fn ($f) => app(EfeDatosGastronomiaSupport::class)->aplicar($f, $filtros, $nombres),
    '7_bienes_uso' => fn ($f) => app(EfeDatosBienesUsoSupport::class)->aplicar($f, $filtros),
    '8_excluir_iva' => fn ($f) => app(EfeDatosExcluirIvaOppGastoSupport::class)->aplicar($f),
];

echo "\nExcel C24 ref: -95,319,079.70\n";
echo str_pad('Paso', 22).str_pad('líneas', 8).str_pad('neto C24', 18)."Δ vs ant\n";
$prev = null;
$snapshot = null;
foreach ($pasos as $nombre => $fn) {
    if ($fn !== null) {
        $filas = $fn($filas);
    }
    $stat = $netoC($filas, 24);
    $delta = $prev === null ? 0.0 : round($stat['neto'] - $prev, 2);
    echo sprintf(
        "%-22s %-8d %-18s %s\n",
        $nombre,
        $stat['n'],
        number_format($stat['neto'], 2, '.', ','),
        $prev === null ? '—' : number_format($delta, 2, '.', ','),
    );
    if (abs($delta) > 1000) {
        // detalle: qué conceptos perdieron/ganaron neto hacia/desde 24
        // (aproximación: comparar concepto_id por índice no sirve si se agregan filas)
    }
    $prev = $stat['neto'];
    if ($nombre === '4_opp_gasto_com' || $nombre === '5_reimputa_anticipo') {
        $snapshot = $filas;
    }
}

// Detalle del salto grande: comparar base vs después de opp_gasto_com
echo "\n=== Detalle líneas C24 nuevas/cambiadas tras opp_gasto_com (si aplica) ===\n";
echo "Ver totales arriba: el paso con mayor |Δ| es el responsable.\n";

echo "\nListo.\n";
