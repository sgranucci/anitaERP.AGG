<?php

/**
 * Audita C24 con el mismo armado que EfeMensualReporteService (incluye concepto 0 en posts).
 */

declare(strict_types=1);

use App\Services\Contable\EfeMensualReporteService;
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
    if ($importes === null) {
        continue;
    }
    // Igual que armarFilasDatos: deja 0/63 durante posts
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
        '_concepto_inicial' => $conceptoId,
        '_cuenta_inicial' => (int) ($ln['cuenta'] ?? 0),
    ];
}

$netoC = static function (array $filas, int $cid): float {
    $neto = 0.0;
    foreach ($filas as $f) {
        if ((int) ($f['concepto_id'] ?? 0) !== $cid) {
            continue;
        }
        $neto += (float) ($f['cobros'] ?? 0) - (float) ($f['pagos'] ?? 0);
    }

    return round($neto, 2);
};

$pasos = [
    '0_base' => null,
    '1_gaming' => fn ($f) => app(EfeDatosGamingSuppliesSupport::class)->aplicar($f, $filtros, $nombres),
    '2_mant24' => fn ($f) => app(EfeDatosMantenimientoEdificioSupport::class)->aplicar($f, $filtros, $nombres),
    '3_varios' => fn ($f) => app(EfeDatosVariosSupport::class)->aplicar($f, $filtros, $nombres),
    '4_opp_com' => fn ($f) => app(EfeDatosOppGastoComSupport::class)->aplicar($f, $filtros, $nombres),
    '5_reimputa' => fn ($f) => app(EfeDatosReimputaAnticipoSupport::class)->aplicar($f, $nombres),
    '6_gastro' => fn ($f) => app(EfeDatosGastronomiaSupport::class)->aplicar($f, $filtros, $nombres),
    '7_bienes' => fn ($f) => app(EfeDatosBienesUsoSupport::class)->aplicar($f, $filtros),
    '8_iva' => fn ($f) => app(EfeDatosExcluirIvaOppGastoSupport::class)->aplicar($f),
    '9_filtra_0_63' => fn ($f) => array_values(array_filter(
        $f,
        fn (array $fila): bool => ! in_array((int) ($fila['concepto_id'] ?? -1), [0, 63], true),
    )),
];

echo "Excel C24: -95,319,079.70\n";
$prev = null;
foreach ($pasos as $nombre => $fn) {
    if ($fn !== null) {
        $filas = $fn($filas);
    }
    $n = 0;
    foreach ($filas as $f) {
        if ((int) $f['concepto_id'] === 24) {
            $n++;
        }
    }
    $neto = $netoC($filas, 24);
    $delta = $prev === null ? null : round($neto - $prev, 2);
    echo sprintf(
        "%-12s n=%-4d neto=%s Δ=%s\n",
        $nombre,
        $n,
        number_format($neto, 2, '.', ','),
        $delta === null ? '—' : number_format($delta, 2, '.', ','),
    );
    $prev = $neto;
}

// Quién entró a 24 desde otro concepto
$entradas = [];
foreach ($filas as $f) {
    if ((int) $f['concepto_id'] !== 24) {
        continue;
    }
    $ini = (int) ($f['_concepto_inicial'] ?? -1);
    if ($ini === 24) {
        continue;
    }
    $key = $ini;
    if (! isset($entradas[$key])) {
        $entradas[$key] = ['n' => 0, 'neto' => 0.0];
    }
    $entradas[$key]['n']++;
    $entradas[$key]['neto'] += (float) ($f['cobros'] ?? 0) - (float) ($f['pagos'] ?? 0);
}

echo "\nLíneas finales en C24 que NO nacieron en 24:\n";
uasort($entradas, fn ($a, $b) => abs($b['neto']) <=> abs($a['neto']));
foreach ($entradas as $cid => $info) {
    echo sprintf(
        "  desde C%d: %d líneas · neto %s\n",
        $cid,
        $info['n'],
        number_format($info['neto'], 2, '.', ','),
    );
}

$movidas = [];
foreach ($filas as $f) {
    if ((int) $f['concepto_id'] !== 24) {
        continue;
    }
    $ini = (int) ($f['_concepto_inicial'] ?? -1);
    if ($ini === 24) {
        continue;
    }
    $movidas[] = [
        'de' => $ini,
        'asiento' => (int) $f['nro_asiento'],
        'cta_ini' => (int) ($f['_cuenta_inicial'] ?? 0),
        'cta' => (int) $f['cuenta'],
        'codigo' => $f['cuenta_codigo'],
        'tipo' => $f['tipo_comp'],
        'comp' => $f['comprobante'],
        'neto' => round((float) ($f['cobros'] ?? 0) - (float) ($f['pagos'] ?? 0), 2),
        'desc' => mb_substr((string) $f['descripcion'], 0, 45),
    ];
}
usort($movidas, fn ($a, $b) => abs($b['neto']) <=> abs($a['neto']));
echo "\nTop 25 movidas a C24:\n";
foreach (array_slice($movidas, 0, 25) as $m) {
    echo sprintf(
        "  asi %d C%d→24 cta %d→%s %s %s neto %s %s\n",
        $m['asiento'],
        $m['de'],
        $m['cta_ini'],
        $m['codigo'] !== '' ? $m['codigo'] : (string) $m['cta'],
        $m['tipo'],
        $m['comp'],
        number_format($m['neto'], 2, '.', ','),
        $m['desc'],
    );
}

echo "\nListo.\n";
