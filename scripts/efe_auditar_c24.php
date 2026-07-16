<?php

/**
 * Audita C24: qué filas reclasifica el post EfeDatosMantenimientoEdificioSupport.
 * Uso: php scripts/efe_auditar_c24.php [empresa] [mes] [anio]
 */

declare(strict_types=1);

use App\Services\Contable\EfeMensualReporteService;
use App\Services\Contable\MayorConceptoReporteService;
use App\Support\Contable\Efe\EfeClasificacionConceptoSupport;
use App\Support\Contable\Efe\EfeDatosMantenimientoEdificioSupport;
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
$post24 = app(EfeDatosMantenimientoEdificioSupport::class);

$nombres = [];
foreach (DB::table('conceptogasto')->get(['id', 'nombre']) as $row) {
    $nombres[(int) $row->id] = (string) ($row->nombre ?? '');
}

echo "Generando mayor…\n";
$resultadoMayor = $mayorSvc->generarDesdeFiltros(
    EfeMensualListadoFiltros::filtrosParaMayorConcepto($filtros)
);

$filas = [];
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
        'cuenta_codigo' => (string) ($ln['cuenta_codigo'] ?? ''),
        'nro_asiento' => (int) ($ln['nro_asiento'] ?? 0),
        'tipo_comp' => (string) ($ln['tipo_comp'] ?? ''),
        'comprobante' => (string) ($ln['comprobante'] ?? ''),
        'descripcion' => (string) ($ln['descripcion'] ?? ''),
        'pagos' => $importes['pagos'],
        'cobros' => $importes['cobros'],
        'concepto_id' => $conceptoId,
        'concepto_nombre' => $nombres[$conceptoId] ?? '',
        'origen' => (string) ($ln['origen'] ?? ''),
    ];
}

$neto = static function (array $f): float {
    return round((float) ($f['cobros'] ?? 0) - (float) ($f['pagos'] ?? 0), 2);
};

$antes24 = array_values(array_filter($filas, fn ($f) => (int) $f['concepto_id'] === 24));
$netoAntes = round(array_sum(array_map($neto, $antes24)), 2);

echo "Aplicando solo post C24…\n";
$despues = $post24->aplicar($filas, $filtros, $nombres);
$despues24 = array_values(array_filter($despues, fn ($f) => (int) $f['concepto_id'] === 24));
$netoDespues = round(array_sum(array_map($neto, $despues24)), 2);

echo "\n=== TOTALES C24 ===\n";
echo "Antes post (mayor+clasif): líneas=".count($antes24)." neto={$netoAntes}\n";
echo "Después post C24:          líneas=".count($despues24)." neto={$netoDespues}\n";
echo "Δ post: ".round($netoDespues - $netoAntes, 2)."\n";

// Filas que cambiaron HACIA 24
$movidas = [];
foreach ($despues as $i => $f) {
    $antes = $filas[$i] ?? null;
    if ($antes === null) {
        continue;
    }
    $cAntes = (int) $antes['concepto_id'];
    $cDesp = (int) $f['concepto_id'];
    if ($cAntes !== 24 && $cDesp === 24) {
        $movidas[] = [
            'de' => $cAntes,
            'de_nombre' => $antes['concepto_nombre'],
            'asiento' => (int) $f['nro_asiento'],
            'cuenta' => (int) $f['cuenta'],
            'cuenta_codigo' => $f['cuenta_codigo'],
            'tipo' => $f['tipo_comp'],
            'comp' => $f['comprobante'],
            'desc' => mb_substr($f['descripcion'], 0, 50),
            'pagos' => (float) ($f['pagos'] ?? 0),
            'cobros' => (float) ($f['cobros'] ?? 0),
            'neto' => $neto($f),
            'origen' => $f['origen'] ?? '',
        ];
    }
}

$porOrigen = [];
foreach ($movidas as $m) {
    $k = $m['de'];
    if (! isset($porOrigen[$k])) {
        $porOrigen[$k] = ['n' => 0, 'neto' => 0.0, 'nombre' => $m['de_nombre']];
    }
    $porOrigen[$k]['n']++;
    $porOrigen[$k]['neto'] += $m['neto'];
}

echo "\n=== RECLASIFICADAS HACIA C24 (".count($movidas)." líneas) ===\n";
foreach ($porOrigen as $cid => $info) {
    echo sprintf(
        "Desde C%d %s: %d líneas · neto movido %s\n",
        $cid,
        $info['nombre'],
        $info['n'],
        number_format($info['neto'], 2, '.', ','),
    );
}

usort($movidas, fn ($a, $b) => abs($b['neto']) <=> abs($a['neto']));
echo "\nTop 20 asientos/líneas movidas a C24:\n";
foreach (array_slice($movidas, 0, 20) as $m) {
    echo sprintf(
        "  asi %d · C%d→24 · cta %s · %s %s · neto %s · %s\n",
        $m['asiento'],
        $m['de'],
        $m['cuenta_codigo'] !== '' ? $m['cuenta_codigo'] : (string) $m['cuenta'],
        $m['tipo'],
        $m['comp'],
        number_format($m['neto'], 2, '.', ','),
        $m['desc'],
    );
}

// Cuentas en C24 después
$porCta = [];
foreach ($despues24 as $f) {
    $c = (int) $f['cuenta'];
    if (! isset($porCta[$c])) {
        $porCta[$c] = ['n' => 0, 'neto' => 0.0, 'codigo' => $f['cuenta_codigo']];
    }
    $porCta[$c]['n']++;
    $porCta[$c]['neto'] += $neto($f);
}
uasort($porCta, fn ($a, $b) => abs($b['neto']) <=> abs($a['neto']));
echo "\n=== C24 DESPUÉS por cuenta ===\n";
foreach (array_slice($porCta, 0, 15, true) as $c => $info) {
    echo sprintf(
        "  %s (%d): n=%d neto=%s\n",
        $info['codigo'] !== '' ? $info['codigo'] : (string) $c,
        $c,
        $info['n'],
        number_format($info['neto'], 2, '.', ','),
    );
}

echo "\nListo.\n";
