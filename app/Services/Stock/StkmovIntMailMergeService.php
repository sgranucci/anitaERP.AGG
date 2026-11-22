<?php

namespace App\Services\Stock;

use App\Support\Stock\StkmovIntAnitaBridgeReader;
use App\Support\Stock\StkmovIntMailSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Saldos del día G1–G6 como l-stkmovint: Anita Villafranca + Anita Bierzo.
 * ERP Bierzo entra solo en el control DIFF (no manda el PDF: el legacy lee stkmov).
 */
class StkmovIntMailMergeService
{
    private const TOLERANCIA = 0.05;

    public function __construct(
        private readonly StkmovIntAnitaBridgeReader $anitaReader,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generar(?string $fechaIso = null): array
    {
        $fecha = $fechaIso && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaIso)
            ? $fechaIso
            : date('Y-m-d');

        $mapErp = $this->mapearErp($fecha);

        $villaOk = true;
        $villaError = null;
        $mapVilla = [];
        try {
            $ret = $this->anitaReader->agregarSaldosDelDia(
                StkmovIntMailSupport::pathVillafranca(),
                $fecha
            );
            $villaOk = (bool) ($ret['ok'] ?? false);
            $villaError = $ret['error'] ?? null;
            foreach ($ret['filas'] ?? [] as $fila) {
                $mapVilla[(string) $fila['sku']] = $fila;
            }
        } catch (\Throwable $e) {
            $villaOk = false;
            $villaError = $e->getMessage();
            Log::warning('StkmovIntMailMergeService Villafranca', ['error' => $villaError]);
        }

        $bierzoAnitaOk = true;
        $bierzoAnitaError = null;
        $mapBierzoAnita = [];
        try {
            $ret = $this->anitaReader->agregarSaldosDelDia(
                StkmovIntMailSupport::pathBierzo(),
                $fecha
            );
            $bierzoAnitaOk = (bool) ($ret['ok'] ?? false);
            $bierzoAnitaError = $ret['error'] ?? null;
            foreach ($ret['filas'] ?? [] as $fila) {
                $mapBierzoAnita[(string) $fila['sku']] = $fila;
            }
        } catch (\Throwable $e) {
            $bierzoAnitaOk = false;
            $bierzoAnitaError = $e->getMessage();
            Log::warning('StkmovIntMailMergeService Bierzo Anita', ['error' => $bierzoAnitaError]);
        }

        $skus = array_values(array_unique(array_merge(
            array_keys($mapVilla),
            array_keys($mapBierzoAnita),
            array_keys($mapErp),
        )));
        usort($skus, static fn ($a, $b) => strnatcmp($a, $b));

        $descripciones = $this->descripcionesSku($skus);
        $filasMerge = [];
        $diffs = [];
        $totSaldo = 0.0;
        $totUnidades = 0.0;

        foreach ($skus as $sku) {
            $erp = $mapErp[$sku] ?? null;
            $villa = $mapVilla[$sku] ?? null;
            $bierzoAnita = $mapBierzoAnita[$sku] ?? null;

            // Informe = Anita+Anita (paridad con l-stkmovint.c).
            $saldoBierzo = (float) ($bierzoAnita['saldo'] ?? 0);
            $unBierzo = (float) ($bierzoAnita['unidades'] ?? 0);
            $saldoVilla = (float) ($villa['saldo'] ?? 0);
            $unVilla = (float) ($villa['unidades'] ?? 0);

            $saldo = round($saldoBierzo + $saldoVilla, 2);
            $unidades = round($unBierzo + $unVilla, 2);
            if (abs($saldo) < 0.0001 && abs($unidades) < 0.0001) {
                continue;
            }

            $agr = (string) ($villa['agrupacion'] ?? $bierzoAnita['agrupacion'] ?? $erp['agrupacion'] ?? '');
            if ($villa !== null && $bierzoAnita !== null) {
                $fuente = 'anita_ambos';
            } elseif ($villa !== null) {
                $fuente = 'villafranca';
            } else {
                $fuente = 'anita_bierzo';
            }

            $filasMerge[] = [
                'sku' => $sku,
                'descripcion' => $descripciones[$sku] ?? '',
                'agrupacion' => $agr,
                'saldo' => $saldo,
                'unidades' => round($unidades, 0),
                'saldo_bierzo' => round($saldoBierzo, 2),
                'saldo_villafranca' => round($saldoVilla, 2),
                'fuente' => $fuente,
            ];
            $totSaldo += $saldo;
            $totUnidades += round($unidades, 0);

            // Control: solo SKU presentes en ambas fuentes con monto distinto.
            if ($erp !== null && $bierzoAnita !== null) {
                $d = abs((float) $erp['saldo'] - (float) $bierzoAnita['saldo']);
                if ($d > self::TOLERANCIA) {
                    $diffs[] = [
                        'sku' => $sku,
                        'tipo' => 'bierzo_erp_vs_anita',
                        'saldo_erp' => (float) $erp['saldo'],
                        'saldo_anita' => (float) $bierzoAnita['saldo'],
                    ];
                }
            }
        }

        usort($filasMerge, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['agrupacion'] ?? ''), (string) ($b['agrupacion'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strnatcmp((string) $a['sku'], (string) $b['sku']);
        });

        $anitaOk = $villaOk && $bierzoAnitaOk;
        $anitaError = trim(implode(' | ', array_filter([
            $villaOk ? null : ('Villafranca: '.$villaError),
            $bierzoAnitaOk ? null : ('Bierzo: '.$bierzoAnitaError),
        ]))) ?: null;

        return [
            'fecha' => $fecha,
            'filas_merge' => $filasMerge,
            'diffs' => $diffs,
            'totales' => [
                'filas' => count($filasMerge),
                'saldo' => round($totSaldo, 2),
                'unidades' => round($totUnidades, 0),
                'erp' => count($mapErp),
                'villafranca' => count($mapVilla),
                'bierzo_anita' => count($mapBierzoAnita),
            ],
            'cuadran' => $diffs === [] && $anitaOk,
            'anita_ok' => $anitaOk,
            'anita_error' => $anitaError,
            'villa_ok' => $villaOk,
            'bierzo_anita_ok' => $bierzoAnitaOk,
        ];
    }

    /**
     * @return array<string, array{sku: string, agrupacion: string, saldo: float, unidades: float}>
     */
    private function mapearErp(string $fecha): array
    {
        $catIds = DB::table('categoria')
            ->whereIn('codigo', StkmovIntMailSupport::CATEGORIAS_ERP)
            ->pluck('id');
        if ($catIds->isEmpty()) {
            return [];
        }

        // Solo Bierzo (empresa 1): Villafranca (2) no tiene articulo_movimiento G1–G6; se lee por Anita.
        $rows = DB::table('articulo_movimiento as am')
            ->join('articulo as a', 'a.id', '=', 'am.articulo_id')
            ->join('depmae as dep', 'dep.id', '=', 'am.deposito_id')
            ->leftJoin('categoria as cat', 'cat.id', '=', 'a.categoria_id')
            ->where('dep.empresa_id', 1)
            ->whereDate('am.fecha', $fecha)
            ->whereIn('a.categoria_id', $catIds)
            ->groupBy('a.sku', 'cat.codigo')
            ->selectRaw('a.sku, cat.codigo as agrupacion, SUM(am.cantidad) as sum_cant, SUM(COALESCE(am.pieza, 0)) as sum_pieza')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $sku = StkmovIntMailSupport::skuCorto((string) $row->sku);
            if ($sku === '') {
                continue;
            }
            // Informe C: tot_saldo = -sum(cantidad ERP firmada).
            $saldo = -1 * (float) ($row->sum_cant ?? 0);
            $pieza = (float) ($row->sum_pieza ?? 0);
            $unidades = $pieza != 0.0 ? -1 * $pieza : $saldo;
            $out[$sku] = [
                'sku' => $sku,
                'agrupacion' => StkmovIntMailSupport::normalizarAgrupacion((string) ($row->agrupacion ?? '')),
                'saldo' => round($saldo, 2),
                'unidades' => round($unidades, 2),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    private function descripcionesSku(array $skus): array
    {
        if ($skus === []) {
            return [];
        }
        $out = [];
        $arts = DB::table('articulo')
            ->whereIn('sku', $skus)
            ->orWhereIn('sku', array_map(static fn ($s) => str_pad($s, 13, '0', STR_PAD_LEFT), $skus))
            ->get(['sku', 'descripcion']);
        foreach ($arts as $a) {
            $k = StkmovIntMailSupport::skuCorto((string) $a->sku);
            if ($k !== '' && ! isset($out[$k])) {
                $out[$k] = (string) $a->descripcion;
            }
        }

        return $out;
    }
}
