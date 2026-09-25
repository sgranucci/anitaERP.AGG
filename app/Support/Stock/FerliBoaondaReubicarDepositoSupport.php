<?php

namespace App\Support\Stock;

use App\Models\Stock\Depmae;
use App\Services\Stock\Articulo_MovimientoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Reubica solo el depósito del stock Boaonda según el Excel artesanal
 * (salida del depósito actual + alta en el del Excel; mismas cantidades/lote/talles).
 */
final class FerliBoaondaReubicarDepositoSupport
{
    public const DB_PERMITIDA = 'anitaERP_l12';

    public const CONCEPTO_SALIDA = 'Reubica depósito Excel Boa Onda (salida)';

    public const CONCEPTO_ENTRADA = 'Reubica depósito Excel Boa Onda (entrada)';

    public const MVENTA_BOAONDA = 4;

    public static function assertEntorno(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            throw new RuntimeException('Solo Calzados Ferli. EMPRESA='.config('app.empresa'));
        }
        $db = (string) config('database.connections.'.config('database.default').'.database');
        if ($db !== self::DB_PERMITIDA) {
            throw new RuntimeException('Solo '.self::DB_PERMITIDA.'. DB actual='.$db);
        }
    }

    /**
     * @return array{
     *   excel_filas: int,
     *   targets: int,
     *   buckets_ok: int,
     *   buckets_sin_target: int,
     *   buckets_mover: int,
     *   pares_mover: float,
     *   rutas: array<string, float>,
     *   mover: list<array<string, mixed>>
     * }
     */
    public static function planificar(string $excelPath): array
    {
        self::assertEntorno();
        if (! is_file($excelPath)) {
            throw new RuntimeException('No existe el Excel: '.$excelPath);
        }

        $targets = self::targetsDesdeExcel($excelPath);
        $lotes = [];
        $artIds = [];
        foreach (array_keys($targets) as $key) {
            [$lote, $artId] = array_map('intval', explode('|', $key));
            $lotes[$lote] = $lote;
            $artIds[$artId] = $artId;
        }

        if ($lotes === [] || $artIds === []) {
            return [
                'excel_filas' => 0,
                'targets' => 0,
                'buckets_ok' => 0,
                'buckets_sin_target' => 0,
                'buckets_mover' => 0,
                'pares_mover' => 0.0,
                'rutas' => [],
                'mover' => [],
            ];
        }

        $depByCodigo = Depmae::query()
            ->whereIn('codigo', array_values(array_unique(array_values($targets))))
            ->get()
            ->keyBy(fn (Depmae $d) => strtolower((string) $d->codigo));

        $saldos = DB::table('articulo_movimiento as am')
            ->join('articulo as a', 'a.id', '=', 'am.articulo_id')
            ->leftJoin('depmae as d', 'd.id', '=', 'am.deposito_id')
            ->whereIn('am.lote', array_values($lotes))
            ->whereIn('am.articulo_id', array_values($artIds))
            ->groupBy('am.lote', 'am.articulo_id', 'a.sku', 'am.combinacion_id', 'am.deposito_id', 'd.codigo')
            ->selectRaw('am.lote, am.articulo_id, a.sku, am.combinacion_id, am.deposito_id, d.codigo as dep, sum(am.cantidad) as saldo, max(am.precio) as precio, max(am.ordentrabajo_id) as ordentrabajo_id, max(am.modulo_id) as modulo_id')
            ->havingRaw('sum(am.cantidad) > 0.0001')
            ->get();

        $talleRows = DB::table('articulo_movimiento as am')
            ->join('articulo_movimiento_talle as amt', 'amt.articulo_movimiento_id', '=', 'am.id')
            ->whereIn('am.lote', array_values($lotes))
            ->whereIn('am.articulo_id', array_values($artIds))
            ->groupBy('am.lote', 'am.articulo_id', 'am.combinacion_id', 'am.deposito_id', 'amt.talle_id')
            ->havingRaw('sum(amt.cantidad) > 0.0001')
            ->get([
                'am.lote',
                'am.articulo_id',
                'am.combinacion_id',
                'am.deposito_id',
                'amt.talle_id',
                DB::raw('SUM(amt.cantidad) as cantidad'),
                DB::raw('MAX(amt.precio) as precio'),
            ]);

        $tallesPorGrupo = [];
        foreach ($talleRows as $t) {
            $key = implode('|', [(int) $t->lote, (int) $t->articulo_id, (int) $t->combinacion_id, (int) $t->deposito_id]);
            $tallesPorGrupo[$key][] = [
                'talle_id' => (int) $t->talle_id,
                'cantidad' => (float) $t->cantidad,
                'precio' => (float) ($t->precio ?? 0),
            ];
        }

        $mover = [];
        $ok = 0;
        $sinTarget = 0;
        $rutas = [];
        $pares = 0.0;

        foreach ($saldos as $s) {
            $tKey = ((int) $s->lote).'|'.((int) $s->articulo_id);
            if (! isset($targets[$tKey])) {
                $sinTarget++;
                continue;
            }
            $haciaCod = $targets[$tKey];
            if ((string) $s->dep === $haciaCod) {
                $ok++;
                continue;
            }
            $hacia = $depByCodigo->get(strtolower($haciaCod));
            if (! $hacia) {
                continue;
            }
            $gKey = implode('|', [(int) $s->lote, (int) $s->articulo_id, (int) $s->combinacion_id, (int) $s->deposito_id]);
            $saldo = (float) $s->saldo;
            $item = [
                'lote' => (int) $s->lote,
                'sku' => (string) $s->sku,
                'articulo_id' => (int) $s->articulo_id,
                'combinacion_id' => (int) $s->combinacion_id,
                'desde' => (string) $s->dep,
                'desde_id' => (int) $s->deposito_id,
                'hacia' => $haciaCod,
                'hacia_id' => (int) $hacia->id,
                'saldo' => $saldo,
                'precio' => (float) ($s->precio ?? 0),
                'ordentrabajo_id' => (int) ($s->ordentrabajo_id ?? 0),
                'modulo_id' => (int) ($s->modulo_id ?? 0),
                'talles' => $tallesPorGrupo[$gKey] ?? [],
            ];
            $mover[] = $item;
            $pares += $saldo;
            $ruta = $item['desde'].'→'.$item['hacia'];
            $rutas[$ruta] = ($rutas[$ruta] ?? 0) + $saldo;
        }

        return [
            'excel_filas' => count($targets),
            'targets' => count($targets),
            'buckets_ok' => $ok,
            'buckets_sin_target' => $sinTarget,
            'buckets_mover' => count($mover),
            'pares_mover' => $pares,
            'rutas' => $rutas,
            'mover' => $mover,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{salidas: int, entradas: int, pares: float}
     */
    public static function ejecutar(array $plan): array
    {
        self::assertEntorno();

        $movService = app(Articulo_MovimientoService::class);
        $tipoAlta = (int) config('consprod.TIPOTRANSACCION_ALTA_PRODUCCION', 3);
        $tipoConot = (int) config('consprod.TIPOTRANSACCION_CONSUME_OT', 4);
        $ahora = Carbon::now();
        $salidas = 0;
        $entradas = 0;
        $pares = 0.0;

        DB::transaction(function () use ($plan, $movService, $tipoAlta, $tipoConot, $ahora, &$salidas, &$entradas, &$pares) {
            foreach ($plan['mover'] as $item) {
                $talles = [];
                $paresTalle = 0.0;
                foreach ($item['talles'] as $t) {
                    $cant = abs((float) $t['cantidad']);
                    if ($cant < 0.0001) {
                        continue;
                    }
                    $talles[] = [
                        'id' => null,
                        'talle_id' => (int) $t['talle_id'],
                        'cantidad' => $cant,
                        'precio' => (float) ($t['precio'] ?? $item['precio']),
                    ];
                    $paresTalle += $cant;
                }
                $cant = abs((float) $item['saldo']);
                if ($cant < 0.0001) {
                    continue;
                }
                // Usar talles del saldo; si la suma de talles no cierra con cabecera,
                // prorratear al saldo (no inventar pares).
                $paresTalle = 0.0;
                foreach ($talles as $t) {
                    $paresTalle += (float) $t['cantidad'];
                }
                if ($paresTalle > 0.0001 && abs($paresTalle - $cant) > 0.051) {
                    $factor = $cant / $paresTalle;
                    foreach ($talles as &$t) {
                        $t['cantidad'] = round((float) $t['cantidad'] * $factor, 4);
                    }
                    unset($t);
                }

                $base = [
                    'fecha' => $ahora,
                    'fechajornada' => $ahora,
                    'pedido_combinacion_id' => null,
                    'ordentrabajo_id' => ($item['ordentrabajo_id'] ?? 0) > 0 ? $item['ordentrabajo_id'] : null,
                    'lote' => $item['lote'],
                    'articulo_id' => $item['articulo_id'],
                    'combinacion_id' => $item['combinacion_id'],
                    'modulo_id' => ($item['modulo_id'] ?? 0) > 0 ? $item['modulo_id'] : null,
                    'cantidad' => $cant,
                    'precio' => $item['precio'],
                    'costo' => 0,
                    'descuento' => null,
                    'descuentointegrado' => null,
                    'moneda_id' => null,
                    'incluyeimpuesto' => null,
                    'listaprecio_id' => null,
                ];

                $movService->guardaArticuloMovimiento('create', array_merge($base, [
                    'tipotransaccion_id' => $tipoConot,
                    'concepto' => self::CONCEPTO_SALIDA,
                    'deposito_id' => $item['desde_id'],
                ]), $talles);

                $movService->guardaArticuloMovimiento('create', array_merge($base, [
                    'tipotransaccion_id' => $tipoAlta,
                    'concepto' => self::CONCEPTO_ENTRADA,
                    'deposito_id' => $item['hacia_id'],
                ]), $talles);

                $salidas++;
                $entradas++;
                $pares += $cant;
            }
        });

        return ['salidas' => $salidas, 'entradas' => $entradas, 'pares' => $pares];
    }

    /**
     * @return array<string, string> key "lote|articulo_id" => codigo depósito Excel
     */
    private static function targetsDesdeExcel(string $path): array
    {
        $wb = IOFactory::load($path);
        $targets = [];
        $artCache = [];

        foreach ($wb->getAllSheets() as $ws) {
            $maxC = Coordinate::columnIndexFromString($ws->getHighestDataColumn());
            $maxR = (int) $ws->getHighestDataRow();
            $cArt = 0;
            $cDep = 0;
            $cLote = 0;
            $hdr = 0;
            for ($r = 1; $r <= 3; $r++) {
                for ($c = 1; $c <= $maxC; $c++) {
                    $v = self::norm((string) ($ws->getCellByColumnAndRow($c, $r)->getCalculatedValue() ?? ''));
                    if ($v === 'art' || $v === 'art.') {
                        $cArt = $c;
                        $hdr = $r;
                    }
                    if (str_contains($v, 'deposito')) {
                        $cDep = $c;
                    }
                    if ($v === 'lote' || str_starts_with($v, 'lote ')) {
                        $cLote = $c;
                    }
                }
            }
            if ($cDep === 0) {
                for ($r = 1; $r <= max(1, $hdr); $r++) {
                    for ($c = 1; $c <= $maxC; $c++) {
                        $v = self::norm((string) ($ws->getCellByColumnAndRow($c, $r)->getCalculatedValue() ?? ''));
                        if (str_contains($v, 'deposito')) {
                            $cDep = $c;
                        }
                    }
                }
            }
            if ($cLote === 0 && $hdr > 0) {
                for ($c = 1; $c <= $maxC; $c++) {
                    $sample = trim((string) ($ws->getCellByColumnAndRow($c, $hdr + 1)->getCalculatedValue() ?? ''));
                    if (preg_match('/lote\s*\d{4,}/i', $sample)) {
                        $cLote = $c;
                        break;
                    }
                }
            }
            if ($cArt === 0 || $cDep === 0 || $cLote === 0 || $hdr === 0) {
                continue;
            }

            for ($r = $hdr + 1; $r <= $maxR; $r++) {
                $art = trim((string) ($ws->getCellByColumnAndRow($cArt, $r)->getCalculatedValue() ?? ''));
                if ($art === '' || ! str_contains($art, '-')) {
                    continue;
                }
                $dep = FerliExcelStockImportParser::aliasDeposito(
                    trim((string) ($ws->getCellByColumnAndRow($cDep, $r)->getCalculatedValue() ?? ''))
                );
                $loteTxt = trim((string) ($ws->getCellByColumnAndRow($cLote, $r)->getCalculatedValue() ?? ''));
                if ($dep === null || ! preg_match_all('/(?:lote\s*)?(\d{4,})/i', $loteTxt, $mm)) {
                    continue;
                }
                $skuDig = preg_replace('/\D+/', '', $art) ?? '';
                if ($skuDig === '') {
                    continue;
                }
                if (! array_key_exists($skuDig, $artCache)) {
                    $artCache[$skuDig] = self::resolverArticuloId($skuDig);
                }
                $articuloId = $artCache[$skuDig];
                if ($articuloId === null) {
                    continue;
                }
                foreach ($mm[1] as $loteRaw) {
                    $lote = (int) $loteRaw;
                    if ($lote <= 0) {
                        continue;
                    }
                    $targets[$lote.'|'.$articuloId] = $dep;
                }
            }
        }

        return $targets;
    }

    private static function resolverArticuloId(string $skuDig): ?int
    {
        $cands = DB::table('articulo')
            ->where('sku', 'like', '%'.$skuDig)
            ->get(['id', 'sku', 'mventa_id']);
        if ($cands->isEmpty()) {
            return null;
        }
        $boa = $cands->firstWhere('mventa_id', self::MVENTA_BOAONDA);

        return (int) ($boa->id ?? $cands->first()->id);
    }

    private static function norm(string $s): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);

        return strtolower(trim($t !== false ? $t : $s));
    }
}
