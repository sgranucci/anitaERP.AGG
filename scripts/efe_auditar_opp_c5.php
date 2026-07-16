<?php

/**
 * Lista líneas que OPP→COM mueve hacia C5 y compara con Excel Datos (si se pasa ruta).
 * Uso: php scripts/efe_auditar_opp_c5.php [empresa] [mes] [anio] [excel?]
 */

declare(strict_types=1);

use App\Services\Contable\MayorConceptoReporteService;
use App\Support\Contable\Efe\EfeClasificacionConceptoSupport;
use App\Support\Contable\Efe\EfeDatosGamingSuppliesSupport;
use App\Support\Contable\Efe\EfeDatosMantenimientoEdificioSupport;
use App\Support\Contable\Efe\EfeDatosOppGastoComSupport;
use App\Support\Contable\Efe\EfeDatosPagosCobrosSupport;
use App\Support\Contable\Efe\EfeDatosVariosSupport;
use App\Support\Contable\Efe\EfeOppComGastoResolverSupport;
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
$rutaExcel = $argv[4] ?? '/home/sergio/tmp/Efe Anita BSA 31.05.26.xlsx';

$mayorSvc = app(MayorConceptoReporteService::class);
$clasif = app(EfeClasificacionConceptoSupport::class);
$pagosCobros = app(EfeDatosPagosCobrosSupport::class);
$resolver = app(EfeOppComGastoResolverSupport::class);

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
        'cuenta_codigo' => (string) ($ln['cuenta_codigo'] ?? ''),
        'cuenta_nombre' => (string) ($ln['cuenta_nombre'] ?? ''),
        'nro_asiento' => (int) ($ln['nro_asiento'] ?? 0),
        'tipo_comp' => (string) ($ln['tipo_comp'] ?? ''),
        'comprobante' => (string) ($ln['comprobante'] ?? ''),
        'descripcion' => (string) ($ln['descripcion'] ?? ''),
        'pagos' => $importes['pagos'],
        'cobros' => $importes['cobros'],
        'empresa_id' => $empresaId,
        'concepto_id' => $conceptoId,
        'concepto_nombre' => $nombres[$conceptoId] ?? '',
        '_uid' => count($filas),
        '_cid0' => $conceptoId,
        '_cta0' => (int) ($ln['cuenta'] ?? 0),
    ];
}

$filas = app(EfeDatosGamingSuppliesSupport::class)->aplicar($filas, $filtros, $nombres);
$filas = app(EfeDatosMantenimientoEdificioSupport::class)->aplicar($filas, $filtros, $nombres);
$filas = app(EfeDatosVariosSupport::class)->aplicar($filas, $filtros, $nombres);

$antes = [];
foreach ($filas as $i => $f) {
    $antes[$i] = [
        'cid' => (int) $f['concepto_id'],
        'cta' => (int) $f['cuenta'],
        'pagos' => (float) ($f['pagos'] ?? 0),
        'cobros' => (float) ($f['cobros'] ?? 0),
    ];
}

$filas = app(EfeDatosOppGastoComSupport::class)->aplicar($filas, $filtros, $nombres);
$resolver->preparar($filtros);

$moves = [];
$sum = 0.0;
foreach ($filas as $i => $f) {
    $cid = (int) $f['concepto_id'];
    $prev = $antes[$i];
    if ($cid !== 5 || $prev['cid'] === 5) {
        continue;
    }
    $neto = round($prev['cobros'] - $prev['pagos'], 2);
    // neto EFE estilo Excel: cobros - pagos
    $netoFinal = round((float) ($f['cobros'] ?? 0) - (float) ($f['pagos'] ?? 0), 2);
    $rec = '';
    if (preg_match('/-(\d+)\s*$/', trim((string) $f['comprobante']), $m)) {
        $rec = $m[1];
    }
    $gasto = $rec !== '' ? $resolver->resolverPorRec($rec) : null;
    $moves[] = [
        'asiento' => (int) $f['nro_asiento'],
        'rec' => $rec,
        'de' => $prev['cid'],
        'cta0' => $prev['cta'],
        'cta' => (int) $f['cuenta'],
        'cta_cod' => $f['cuenta_codigo'] !== '' ? $f['cuenta_codigo'] : (string) $f['cuenta'],
        'pagos' => (float) ($f['pagos'] ?? 0),
        'neto' => $netoFinal,
        'comp' => $f['comprobante'],
        'desc' => mb_substr((string) $f['descripcion'], 0, 40),
        'gasto_cid' => (int) ($gasto['concepto_id'] ?? 0),
        'gasto_cta' => (int) ($gasto['cuenta'] ?? 0),
    ];
    $sum += $netoFinal;
}

usort($moves, fn ($a, $b) => abs($b['neto']) <=> abs($a['neto']));

echo sprintf("OPP→COM → C5: %d líneas · neto %s\n", count($moves), number_format($sum, 2, '.', ','));
echo "Top movimientos:\n";
foreach ($moves as $m) {
    echo sprintf(
        "  asi %d rec=%s C%d→5 cta %s→%s pagos=%s neto=%s gastoRes={cid:%d cta:%d} %s %s\n",
        $m['asiento'],
        $m['rec'],
        $m['de'],
        $m['cta0'],
        $m['cta_cod'],
        number_format($m['pagos'], 2, '.', ','),
        number_format($m['neto'], 2, '.', ','),
        $m['gasto_cid'],
        $m['gasto_cta'],
        $m['comp'],
        $m['desc'],
    );
}

// Excel: buscar mismos asientos en Datos concepto 5
if (is_file($rutaExcel)) {
    echo "\n=== Excel Datos C5 para esos asientos ===\n";
    $asientos = array_unique(array_column($moves, 'asiento'));
    $zip = new ZipArchive;
    if ($zip->open($rutaExcel) === true) {
        $ss = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false) {
            $root = new SimpleXMLElement($ssXml);
            $root->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($root->xpath('//m:si') as $si) {
                $texts = [];
                foreach ($si->xpath('.//m:t') as $t) {
                    $texts[] = (string) $t;
                }
                $ss[] = implode('', $texts);
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet7.xml');
        $zip->close();
        if ($sheet !== false) {
            foreach ($asientos as $asiento) {
                $found = 0;
                if (! preg_match_all(
                    '/<c r="F(\d+)"[^>]*>\s*<v>'.preg_quote((string) $asiento, '/').'<\/v>/',
                    $sheet,
                    $mm,
                    PREG_SET_ORDER
                )) {
                    echo "  asi $asiento: no en Excel Datos\n";

                    continue;
                }
                foreach ($mm as $hit) {
                    $rn = (int) $hit[1];
                    if (! preg_match('/<row r="'.$rn.'"[^>]*>(.*?)<\/row>/s', $sheet, $rm)) {
                        continue;
                    }
                    $cells = [];
                    if (preg_match_all('/<c r="([A-Z]+)'.$rn.'"([^>]*)>(.*?)<\/c>/s', $rm[1], $cm, PREG_SET_ORDER)) {
                        foreach ($cm as $c) {
                            if (! preg_match('/<v>(.*?)<\/v>/s', $c[3], $vm)) {
                                continue;
                            }
                            $val = $vm[1];
                            if (str_contains($c[2], 't="s"')) {
                                $val = $ss[(int) $val] ?? $val;
                            }
                            $cells[$c[1]] = $val;
                        }
                    }
                    $conc = (string) ($cells['B'] ?? $cells['Y'] ?? '');
                    if (! str_contains($conc, 'Concepto: 5')) {
                        continue;
                    }
                    $found++;
                    echo sprintf(
                        "  asi %d Excel C5 %s pagos=%s %s\n",
                        $asiento,
                        $cells['C'] ?? '',
                        $cells['O'] ?? '',
                        mb_substr((string) ($cells['D'] ?? ''), 0, 35)
                    );
                }
                if ($found === 0) {
                    echo "  asi $asiento: en Excel pero NO en C5\n";
                }
            }
        }
    }
}

echo "\nListo.\n";
