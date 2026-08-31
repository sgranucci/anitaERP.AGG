<?php

/**
 * Simulación solo-lectura: cierre máquinas Biyemas agosto 2026
 * ERP (Totales+AsientoSupport) vs Anita ctamov (asientos originales).
 * No graba nada.
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Caja\RendicionMaquina;
use App\Models\Contable\Asiento;
use App\Models\Contable\Cuentacontable;
use App\Support\Contable\Anita\AnitaAsientoImportBridgeReader;
use App\Support\Contable\CierreRendicionMaquinaAsientoSupport;
use App\Support\Contable\CierreRendicionMaquinaConfigSupport;
use App\Support\Contable\CierreRendicionMaquinaGrupoSupport;
use App\Support\Contable\CierreRendicionMaquinaTotalesSupport;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

const EMPRESA_ID = 1;
const EMPRESA_ANITA = 1;
const DESDE = 20260801;
const HASTA = 20260831;
const TOL = 0.02;

$outPath = '/tmp/sim_cierre_maquina_agosto_biyemas.json';

echo "Cargando ctamov Anita Biyemas agosto…\n";
$reader = new AnitaAsientoImportBridgeReader();
$bloque = $reader->cargarBloque(EMPRESA_ANITA, DESDE, HASTA);
echo 'ctamov filas='.count($bloque['ctamov']).' errores='.json_encode($bloque['errores'])."\n";

/** @var array<int, list<object>> $porFechaAsiento */
$anitaPorDia = [];
foreach ($bloque['ctamov'] as $fila) {
    $fecha = (int) ($fila->ctav_fecha ?? 0);
    $nro = (int) ($fila->ctav_nro_asiento ?? 0);
    $desc = trim((string) ($fila->ctav_desc_mov ?? ''));
    if ($fecha < DESDE || $fecha > HASTA || $nro <= 0) {
        continue;
    }
    $tipo = clasificarDescAnita($desc);
    if ($tipo === null) {
        continue;
    }
    $anitaPorDia[$fecha][$nro]['tipo'] = $tipo;
    $anitaPorDia[$fecha][$nro]['desc'] = $desc;
    $anitaPorDia[$fecha][$nro]['lineas'][] = [
        'cuenta' => (int) ($fila->ctav_cuenta ?? 0),
        'dh' => strtoupper(trim((string) ($fila->ctav_d_h ?? ''))),
        'importe' => round((float) ($fila->ctav_importe ?? 0), 2),
        'linea' => (int) ($fila->ctav_nro_linea ?? 0),
    ];
}

// Excluir asientos ya regrabados desde ERP (mismo número en asiento.numeroasiento del cierre)
$erpCierreNros = Asiento::query()
    ->where('empresa_id', EMPRESA_ID)
    ->whereBetween('fecha', ['2026-08-01', '2026-08-31'])
    ->where(function ($q) {
        $q->where('observacion', 'like', '%Cierre rendición máquinas%')
            ->orWhere('observacion', 'like', '%Cierre rendicion maquinas%');
    })
    ->pluck('numeroasiento')
    ->map(fn ($n) => (int) $n)
    ->filter(fn ($n) => $n > 0)
    ->unique()
    ->values()
    ->all();
$erpCierreSet = array_fill_keys($erpCierreNros, true);
echo 'ERP cierre nros a excluir de Anita gold: '.implode(',', $erpCierreNros)."\n";

foreach ($anitaPorDia as $fecha => $asientos) {
    foreach (array_keys($asientos) as $nro) {
        if (isset($erpCierreSet[$nro])) {
            unset($anitaPorDia[$fecha][$nro]);
        }
    }
}

$config = CierreRendicionMaquinaConfigSupport::paraEmpresa(EMPRESA_ID);
$idToCodigo = Cuentacontable::query()
    ->where('empresa_id', EMPRESA_ID)
    ->pluck('codigo', 'id')
    ->map(fn ($c) => (int) $c)
    ->all();

$rendiciones = RendicionMaquina::query()
    ->with(['valores.cuentacaja', 'empresa'])
    ->where('empresa_id', EMPRESA_ID)
    ->where('turno', CierreRendicionMaquinaGrupoSupport::TURNO_CIERRE)
    ->whereBetween('fecha', ['2026-08-01', '2026-08-31'])
    ->orderBy('fecha')
    ->get();

$porDiaRend = [];
foreach ($rendiciones as $r) {
    $ymd = $r->fecha?->format('Y-m-d');
    if ($ymd) {
        $porDiaRend[$ymd][] = $r;
    }
}

$diasConAnita = [];
foreach (array_keys($anitaPorDia) as $f) {
    $diasConAnita[ymdFromInt($f)] = true;
}
$todosDias = array_unique(array_merge(array_keys($porDiaRend), array_keys($diasConAnita)));
sort($todosDias);

$reporte = [
    'meta' => [
        'empresa' => 'Biyemas',
        'empresa_id' => EMPRESA_ID,
        'periodo' => '2026-08',
        'generado' => now()->toDateTimeString(),
        'tolerancia' => TOL,
        'modo' => 'solo_lectura',
        'anita_ctamov_filas' => count($bloque['ctamov']),
        'erp_cierre_excluidos' => $erpCierreNros,
    ],
    'resumen' => [
        'dias' => 0,
        'ok' => 0,
        'diff' => 0,
        'sin_erp' => 0,
        'sin_anita' => 0,
        'sin_ambos' => 0,
        'error_sim' => 0,
    ],
    'dias' => [],
];

foreach ($todosDias as $ymd) {
    $fechaInt = (int) str_replace('-', '', $ymd);
    $fila = [
        'fecha' => $ymd,
        'estado' => '?',
        'rendicion_ids' => [],
        'anita_asientos' => [],
        'erp_asientos' => [],
        'diffs' => [],
        'mensaje' => '',
    ];

    $items = $porDiaRend[$ymd] ?? [];
    $fila['rendicion_ids'] = array_map(fn ($r) => (int) $r->id, $items);

    $anitaAsientos = $anitaPorDia[$fechaInt] ?? [];
    ksort($anitaAsientos);
    foreach ($anitaAsientos as $nro => $asi) {
        $agg = agregarLineas($asi['lineas'] ?? []);
        $fila['anita_asientos'][] = [
            'nro' => (int) $nro,
            'tipo' => $asi['tipo'],
            'desc' => $asi['desc'],
            'debe' => $agg['debe'],
            'haber' => $agg['haber'],
            'lineas' => $agg['por_clave'],
        ];
    }

    if ($items === [] && $anitaAsientos === []) {
        $fila['estado'] = 'sin_ambos';
        $reporte['resumen']['sin_ambos']++;
        $reporte['dias'][] = $fila;
        continue;
    }

    if ($items === []) {
        $fila['estado'] = 'sin_erp';
        $fila['mensaje'] = 'Hay asientos Anita pero no hay rendición C en ERP.';
        $reporte['resumen']['sin_erp']++;
        $reporte['dias'][] = $fila;
        continue;
    }

    if ($anitaAsientos === []) {
        $fila['estado'] = 'sin_anita';
        $fila['mensaje'] = 'Hay rendición C en ERP pero no se hallaron asientos Anita (Venta maquinas / Canon).';
        $reporte['resumen']['sin_anita']++;
        $reporte['dias'][] = $fila;
        continue;
    }

    try {
        $col = new EloquentCollection($items);
        $tot = CierreRendicionMaquinaTotalesSupport::calcular($col, EMPRESA_ID, $ymd);
        $asientosErp = CierreRendicionMaquinaAsientoSupport::armarAsientos($tot, $config);
        foreach ($asientosErp as $bloqueErp) {
            $lineasCod = [];
            foreach ($bloqueErp['lineas'] as $ln) {
                $cuentaId = (int) ($ln['cuenta_id'] ?? 0);
                $codigo = (int) ($idToCodigo[$cuentaId] ?? 0);
                $debe = round((float) ($ln['debe'] ?? 0), 2);
                $haber = round((float) ($ln['haber'] ?? 0), 2);
                if ($codigo <= 0 || ($debe <= 0 && $haber <= 0)) {
                    continue;
                }
                $lineasCod[] = [
                    'cuenta' => $codigo,
                    'cuenta_id' => $cuentaId,
                    'dh' => $debe > 0 ? 'D' : 'H',
                    'importe' => $debe > 0 ? $debe : $haber,
                    'concepto' => (string) ($ln['concepto'] ?? ''),
                ];
            }
            $agg = agregarLineas($lineasCod);
            $fila['erp_asientos'][] = [
                'leyenda' => (string) ($bloqueErp['leyenda'] ?? ''),
                'tipo' => clasificarLeyendaErp((string) ($bloqueErp['leyenda'] ?? '')),
                'debe' => $agg['debe'],
                'haber' => $agg['haber'],
                'lineas' => $agg['por_clave'],
            ];
        }

        $fila['totales'] = [
            'maquinas_online' => (float) ($tot['maquinas_online'] ?? 0),
            'ruletas_online' => (float) ($tot['ruletas_online'] ?? 0),
            'fsl_monto' => round((float) ($tot['maquinas_online'] ?? 0) + (float) ($tot['ruletas_online'] ?? 0), 2),
            'impuesto_esp' => (float) ($tot['impuesto_esp'] ?? 0),
            'efectivo' => (float) ($tot['efectivo'] ?? 0),
        ];

        $fila['diffs'] = compararDia($fila['erp_asientos'], $fila['anita_asientos']);
        $ok = $fila['diffs'] === [];
        $fila['estado'] = $ok ? 'ok' : 'diff';
        $fila['mensaje'] = $ok
            ? 'Coincide con Anita (cuentas + importes ±'.TOL.').'
            : count($fila['diffs']).' diferencia(s).';
        $reporte['resumen'][$ok ? 'ok' : 'diff']++;
    } catch (Throwable $e) {
        $fila['estado'] = 'error_sim';
        $fila['mensaje'] = $e->getMessage();
        $reporte['resumen']['error_sim']++;
    }

    $reporte['dias'][] = $fila;
    $reporte['resumen']['dias']++;
}

file_put_contents($outPath, json_encode($reporte, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "OK escrito $outPath\n";
echo 'Resumen: '.json_encode($reporte['resumen'], JSON_UNESCAPED_UNICODE)."\n";
foreach ($reporte['dias'] as $d) {
    $nAnita = count($d['anita_asientos']);
    $nErp = count($d['erp_asientos']);
    $nDiff = count($d['diffs'] ?? []);
    echo sprintf(
        "%s %-10s erp=%d anita=%d diff=%d %s\n",
        $d['fecha'],
        $d['estado'],
        $nErp,
        $nAnita,
        $nDiff,
        $d['mensaje'],
    );
}

function clasificarDescAnita(string $desc): ?string
{
    $d = trim($desc);
    if ($d === 'Venta maquinas') {
        return 'venta';
    }
    if ($d === 'Canon loteria y casinos') {
        return 'canon_loteria';
    }
    if ($d === 'Canon e.de bien publico' || $d === 'Canon ent. de bien publico') {
        return 'canon_hospital';
    }

    return null;
}

function clasificarLeyendaErp(string $leyenda): string
{
    $l = mb_strtolower(trim($leyenda));
    if (str_contains($l, 'canon loter')) {
        return 'canon_loteria';
    }
    if (str_contains($l, 'bien publico') || str_contains($l, 'hospital')) {
        return 'canon_hospital';
    }
    if (str_contains($l, 'pago diferido')) {
        return 'pago_diferido';
    }

    return 'venta';
}

function ymdFromInt(int $f): string
{
    $s = (string) $f;

    return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
}

/**
 * @param  list<array{cuenta:int,dh:string,importe:float}>  $lineas
 * @return array{debe:float,haber:float,por_clave:array<string,float>}
 */
function agregarLineas(array $lineas): array
{
    $por = [];
    $debe = 0.0;
    $haber = 0.0;
    foreach ($lineas as $ln) {
        $cuenta = (int) ($ln['cuenta'] ?? 0);
        $dh = strtoupper((string) ($ln['dh'] ?? ''));
        $imp = round((float) ($ln['importe'] ?? 0), 2);
        if ($cuenta <= 0 || $imp <= 0 || ($dh !== 'D' && $dh !== 'H')) {
            continue;
        }
        $k = $cuenta.'|'.$dh;
        $por[$k] = round(($por[$k] ?? 0) + $imp, 2);
        if ($dh === 'D') {
            $debe = round($debe + $imp, 2);
        } else {
            $haber = round($haber + $imp, 2);
        }
    }
    ksort($por);

    return ['debe' => $debe, 'haber' => $haber, 'por_clave' => $por];
}

/**
 * @param  list<array<string,mixed>>  $erp
 * @param  list<array<string,mixed>>  $anita
 * @return list<array<string,mixed>>
 */
function compararDia(array $erp, array $anita): array
{
    $diffs = [];

    $anitaPorTipo = [];
    foreach ($anita as $a) {
        $anitaPorTipo[$a['tipo']][] = $a;
    }
    $erpPorTipo = [];
    foreach ($erp as $e) {
        $erpPorTipo[$e['tipo']][] = $e;
    }

    $tipos = array_unique(array_merge(array_keys($anitaPorTipo), array_keys($erpPorTipo)));
    sort($tipos);

    foreach ($tipos as $tipo) {
        $listaE = $erpPorTipo[$tipo] ?? [];
        $listaA = $anitaPorTipo[$tipo] ?? [];

        // Venta+pago_diferido Anita ambos son tipo venta en desc; ERP separa pago_diferido.
        // Si ERP tiene pago_diferido y Anita solo venta, fusionamos ERP venta+pago_diferido vs suma Anita venta.
        if ($tipo === 'pago_diferido' && $listaA === [] && isset($anitaPorTipo['venta'])) {
            // se compara embebido en venta más abajo
            continue;
        }

        if ($tipo === 'venta') {
            $erpMerge = mergeAsientosTipo($listaE);
            if (! empty($erpPorTipo['pago_diferido'])) {
                $erpMerge = mergeAsientosTipo(array_merge($listaE, $erpPorTipo['pago_diferido']));
            }
            // Si Anita tiene 2 "Venta maquinas" (venta + pago diferido), sumarlos
            $anitaMerge = mergeAsientosTipo($listaA);
            $diffs = array_merge($diffs, diffClaves('venta(+pago_dif)', $erpMerge, $anitaMerge, $listaA));
            continue;
        }

        if ($tipo === 'pago_diferido') {
            continue;
        }

        $n = max(count($listaE), count($listaA));
        for ($i = 0; $i < $n; $i++) {
            $e = $listaE[$i] ?? null;
            $a = $listaA[$i] ?? null;
            if ($e === null) {
                $diffs[] = [
                    'tipo' => $tipo,
                    'detalle' => 'Falta en ERP; Anita nro '.($a['nro'] ?? '?'),
                    'anita_debe' => $a['debe'] ?? 0,
                    'anita_haber' => $a['haber'] ?? 0,
                ];
                continue;
            }
            if ($a === null) {
                $diffs[] = [
                    'tipo' => $tipo,
                    'detalle' => 'Falta en Anita; ERP «'.($e['leyenda'] ?? '').'»',
                    'erp_debe' => $e['debe'] ?? 0,
                    'erp_haber' => $e['haber'] ?? 0,
                ];
                continue;
            }
            $diffs = array_merge(
                $diffs,
                diffClaves($tipo.'#'.($a['nro'] ?? $i), $e['lineas'] ?? [], $a['lineas'] ?? [], [$a]),
            );
        }
    }

    return $diffs;
}

/**
 * @param  list<array<string,mixed>>  $asientos
 * @return array<string,float>
 */
function mergeAsientosTipo(array $asientos): array
{
    $por = [];
    foreach ($asientos as $a) {
        foreach ($a['lineas'] ?? [] as $k => $v) {
            $por[$k] = round(($por[$k] ?? 0) + (float) $v, 2);
        }
    }
    ksort($por);

    return $por;
}

/**
 * @param  array<string,float>  $erp
 * @param  array<string,float>  $anita
 * @param  list<array<string,mixed>>  $anitaMeta
 * @return list<array<string,mixed>>
 */
function diffClaves(string $etiqueta, array $erp, array $anita, array $anitaMeta): array
{
    $diffs = [];
    $keys = array_unique(array_merge(array_keys($erp), array_keys($anita)));
    sort($keys);
    $nros = implode(',', array_map(fn ($a) => (string) ($a['nro'] ?? ''), $anitaMeta));

    foreach ($keys as $k) {
        $ve = round((float) ($erp[$k] ?? 0), 2);
        $va = round((float) ($anita[$k] ?? 0), 2);
        if (abs($ve - $va) <= TOL) {
            continue;
        }
        [$cuenta, $dh] = explode('|', $k);
        $diffs[] = [
            'tipo' => $etiqueta,
            'anita_nros' => $nros,
            'cuenta' => (int) $cuenta,
            'dh' => $dh,
            'erp' => $ve,
            'anita' => $va,
            'delta' => round($ve - $va, 2),
        ];
    }

    return $diffs;
}
