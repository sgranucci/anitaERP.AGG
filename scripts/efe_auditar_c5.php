<?php

/**
 * Audita C5 gastronomía: cadena de posts + líneas movidas hacia C5.
 * Uso: php scripts/efe_auditar_c5.php [empresa] [mes] [anio]
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
    if ($importes === null) {
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
        '_concepto_inicial' => $conceptoId,
        '_cuenta_inicial' => (int) ($ln['cuenta'] ?? 0),
        '_uid' => count($filas),
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

$contar = static function (array $filas, int $cid): int {
    $n = 0;
    foreach ($filas as $f) {
        if ((int) ($f['concepto_id'] ?? 0) === $cid) {
            $n++;
        }
    }

    return $n;
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
    '9_filtra' => fn ($f) => array_values(array_filter(
        $f,
        fn (array $fila): bool => ! in_array((int) ($fila['concepto_id'] ?? -1), [0, 63], true),
    )),
];

echo "Excel C5 ref: -140,870,998.32\n";
$prev = null;
$antesGastro = null;
$despuesGastro = null;
foreach ($pasos as $nombre => $fn) {
    if ($fn !== null) {
        $filas = $fn($filas);
    }
    $neto = $netoC($filas, 5);
    $n = $contar($filas, 5);
    $delta = $prev === null ? null : round($neto - $prev, 2);
    echo sprintf(
        "%-12s n=%-4d neto=%s Δ=%s\n",
        $nombre,
        $n,
        number_format($neto, 2, '.', ','),
        $delta === null ? '—' : number_format($delta, 2, '.', ','),
    );
    if ($nombre === '5_reimputa') {
        $antesGastro = $filas;
    }
    if ($nombre === '6_gastro') {
        $despuesGastro = $filas;
    }
    $prev = $neto;
}

// Diff solo del paso gastro: por uid qué cambió a/desde 5, y filas nuevas
echo "\n=== Impacto solo paso gastronomía ===\n";
$mapAntes = [];
foreach ($antesGastro ?? [] as $f) {
    $mapAntes[(int) $f['_uid']] = $f;
}
$movidas = [];
$agregadas = [];
foreach ($despuesGastro ?? [] as $f) {
    $uid = (int) ($f['_uid'] ?? -1);
    if ($uid < 0 || ! isset($mapAntes[$uid])) {
        if ((int) ($f['concepto_id'] ?? 0) === 5) {
            $agregadas[] = $f;
        }
        continue;
    }
    $antes = $mapAntes[$uid];
    $cAntes = (int) $antes['concepto_id'];
    $cDesp = (int) $f['concepto_id'];
    if ($cAntes !== 5 && $cDesp === 5) {
        $movidas[] = [
            'de' => $cAntes,
            'asiento' => (int) $f['nro_asiento'],
            'cta' => $f['cuenta_codigo'] !== '' ? $f['cuenta_codigo'] : (string) $f['cuenta'],
            'tipo' => $f['tipo_comp'],
            'comp' => $f['comprobante'],
            'neto' => round((float) ($f['cobros'] ?? 0) - (float) ($f['pagos'] ?? 0), 2),
            'desc' => mb_substr((string) $f['descripcion'], 0, 45),
        ];
    }
    if ($cAntes === 5 && $cDesp !== 5) {
        $movidas[] = [
            'de' => 5,
            'a' => $cDesp,
            'asiento' => (int) $f['nro_asiento'],
            'cta' => $f['cuenta_codigo'] !== '' ? $f['cuenta_codigo'] : (string) $f['cuenta'],
            'tipo' => $f['tipo_comp'],
            'comp' => $f['comprobante'],
            'neto' => round((float) ($f['cobros'] ?? 0) - (float) ($f['pagos'] ?? 0), 2),
            'desc' => 'SALE de C5 → C'.$cDesp.' '.mb_substr((string) $f['descripcion'], 0, 30),
        ];
    }
}

$porOrigen = [];
foreach ($movidas as $m) {
    if (isset($m['a'])) {
        $k = 'sale_a_'.$m['a'];
    } else {
        $k = 'de_'.$m['de'];
    }
    if (! isset($porOrigen[$k])) {
        $porOrigen[$k] = ['n' => 0, 'neto' => 0.0];
    }
    $porOrigen[$k]['n']++;
    $porOrigen[$k]['neto'] += $m['neto'];
}
foreach ($porOrigen as $k => $info) {
    echo sprintf("  %s: %d líneas · neto %s\n", $k, $info['n'], number_format($info['neto'], 2, '.', ','));
}
$netoAg = 0.0;
foreach ($agregadas as $f) {
    $netoAg += (float) ($f['cobros'] ?? 0) - (float) ($f['pagos'] ?? 0);
}
echo sprintf("  agregadas nuevas C5: %d líneas · neto %s\n", count($agregadas), number_format($netoAg, 2, '.', ','));

usort($movidas, fn ($a, $b) => abs($b['neto']) <=> abs($a['neto']));
echo "\nTop 20 reclasificaciones gastro:\n";
foreach (array_slice($movidas, 0, 20) as $m) {
    echo sprintf(
        "  asi %d · %s · %s %s · neto %s · %s\n",
        $m['asiento'],
        isset($m['a']) ? ('C5→'.$m['a']) : ('C'.$m['de'].'→5'),
        $m['cta'],
        $m['tipo'].' '.$m['comp'],
        number_format($m['neto'], 2, '.', ','),
        $m['desc'],
    );
}

usort($agregadas, fn ($a, $b) => abs(((float) ($b['cobros'] ?? 0) - (float) ($b['pagos'] ?? 0)))
    <=> abs(((float) ($a['cobros'] ?? 0) - (float) ($a['pagos'] ?? 0))));
echo "\nTop 15 líneas NUEVAS C5 (TMB/fracción etc.):\n";
foreach (array_slice($agregadas, 0, 15) as $f) {
    $neto = round((float) ($f['cobros'] ?? 0) - (float) ($f['pagos'] ?? 0), 2);
    echo sprintf(
        "  asi %d · %s · %s %s · neto %s · %s\n",
        (int) $f['nro_asiento'],
        $f['cuenta_codigo'] !== '' ? $f['cuenta_codigo'] : (string) $f['cuenta'],
        $f['tipo_comp'],
        $f['comprobante'],
        number_format($neto, 2, '.', ','),
        mb_substr((string) $f['descripcion'], 0, 45),
    );
}

echo "\nListo.\n";
