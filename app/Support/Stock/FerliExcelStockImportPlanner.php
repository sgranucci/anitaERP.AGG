<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Depmae;
use App\Models\Stock\Talle;
use App\Models\Ventas\Ordentrabajo;
use App\Services\Stock\Articulo_MovimientoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Plan de reemplazo de stock Ferli desde Excel. Solo ERP (anitaERP_l12), sin Anita.
 */
final class FerliExcelStockImportPlanner
{
    public const DB_PERMITIDA = 'anitaERP_l12';

    private static ?int $proximoLoteInventado = null;

    /**
     * @return array<string, mixed>
     */
    public static function planificar(string $dir): array
    {
        self::assertEntorno();
        self::$proximoLoteInventado = null;

        $filas = FerliExcelStockImportParser::parseDirectory($dir);
        self::$proximoLoteInventado = self::calcularProximoLote($filas);
        $depositos = Depmae::query()
            ->where('empresa_id', 1)
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy(fn ($d) => strtolower((string) $d->codigo));
        $tallesPorNombre = Talle::query()->get(['id', 'nombre'])->keyBy(fn ($t) => (string) $t->nombre);

        $altap = [];
        $omitidas = ['en_produccion' => 0, 'sin_deposito' => 0, 'errores' => 0];
        $errores = [];
        $paresAltap = 0.0;
        $paresProduccion = 0.0;

        foreach ($filas as $fila) {
            if ($fila['en_produccion'] || $fila['omitir'] === 'en_produccion') {
                $omitidas['en_produccion']++;
                $paresProduccion += (float) $fila['pares'];
                continue;
            }
            if ($fila['omitir'] === 'sin_deposito' || empty($fila['deposito_codigo'])) {
                $omitidas['sin_deposito']++;
                continue;
            }
            $resuelto = self::resolverFila($fila, $depositos, $tallesPorNombre);
            if ($resuelto['errores'] !== []) {
                $omitidas['errores']++;
                $errores[] = [
                    'archivo' => $fila['archivo'],
                    'fila' => $fila['fila'],
                    'sku' => $fila['sku_excel'],
                    'errores' => $resuelto['errores'],
                ];
                continue;
            }
            $altap[] = $resuelto;
            $paresAltap += (float) $resuelto['pares'];
        }

        $lotesInventados = array_values(array_filter($altap, fn ($a) => ! empty($a['lote_inventado'])));
        // Solo anular saldos de artículos que vienen en el Excel (evita borrar Boaonda/Colegial
        // cuando la planilla es Ferli botas/sandalias/zapatillas adultas).
        $articuloIdsAltap = array_values(array_unique(array_filter(array_map(
            static fn (array $a) => (int) ($a['articulo_id'] ?? 0),
            $altap
        ))));
        $mventaIdsAltap = $articuloIdsAltap === []
            ? []
            : Articulo::query()
                ->whereIn('id', $articuloIdsAltap)
                ->whereNotNull('mventa_id')
                ->pluck('mventa_id')
                ->map(static fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        $conot = self::planConot($articuloIdsAltap);

        return [
            'filas_excel' => count($filas),
            'omitidas' => $omitidas,
            'pares_en_produccion' => $paresProduccion,
            'altap_filas' => count($altap),
            'altap_pares' => $paresAltap,
            'altap_por_deposito' => self::sumarPor($altap, 'deposito_codigo', 'pares'),
            'altap_lotes' => count(array_unique(array_filter(array_column($altap, 'lote')))),
            'altap_ots' => count(array_unique(array_filter(array_column($altap, 'ordentrabajo_codigo')))),
            'altap_muestra' => array_slice($altap, 0, 20),
            'altap_mventa_ids' => $mventaIdsAltap,
            'altap_articulo_ids' => $articuloIdsAltap,
            'errores' => $errores,
            'conot' => $conot,
            'altap' => $altap,
            'lotes_inventados' => $lotesInventados,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{conot: int, altap: int}
     */
    public static function ejecutar(array $plan): array
    {
        self::assertEntorno();

        $movService = app(Articulo_MovimientoService::class);
        $tipoAlta = (int) config('consprod.TIPOTRANSACCION_ALTA_PRODUCCION', 3);
        $tipoConot = (int) config('consprod.TIPOTRANSACCION_CONSUME_OT', 4);
        $ahora = Carbon::now();
        $conotHechos = 0;
        $altapHechos = 0;

        DB::transaction(function () use ($plan, $movService, $tipoAlta, $tipoConot, $ahora, &$conotHechos, &$altapHechos) {
            foreach ($plan['conot']['grupos'] as $grupo) {
                $talles = [];
                $paresTalle = 0.0;
                foreach ($grupo['talles'] as $t) {
                    $cant = abs((float) $t['cantidad']);
                    if ($cant < 0.0001) {
                        continue;
                    }
                    $talles[] = [
                        'id' => null,
                        'talle_id' => $t['talle_id'],
                        'cantidad' => $cant,
                        'precio' => $grupo['precio'] ?? 0,
                    ];
                    $paresTalle += $cant;
                }
                $paresCab = (float) $grupo['pares'];
                $esAjustePositivo = $paresCab < -0.0001;
                $pares = max(abs($paresCab), $paresTalle);
                if ($pares <= 0.0001) {
                    continue;
                }
                $movService->guardaArticuloMovimiento('create', [
                    'fecha' => $ahora,
                    'fechajornada' => $ahora,
                    'tipotransaccion_id' => $esAjustePositivo ? $tipoAlta : $tipoConot,
                    'pedido_combinacion_id' => null,
                    'ordentrabajo_id' => $grupo['ordentrabajo_id'] ?: null,
                    'lote' => $grupo['lote'],
                    'articulo_id' => $grupo['articulo_id'],
                    'combinacion_id' => $grupo['combinacion_id'],
                    'modulo_id' => $grupo['modulo_id'] ?: null,
                    'concepto' => $esAjustePositivo
                        ? 'Reemplazo stock Excel Ferli L12 (ALTAP ajuste saldo lote negativo)'
                        : 'Reemplazo stock Excel Ferli L12 (CONOT)',
                    'cantidad' => $pares,
                    'precio' => $grupo['precio'] ?? 0,
                    'costo' => 0,
                    'descuento' => null,
                    'descuentointegrado' => null,
                    'moneda_id' => null,
                    'incluyeimpuesto' => null,
                    'listaprecio_id' => null,
                    'deposito_id' => $grupo['deposito_id'],
                ], $talles);
                if ($esAjustePositivo) {
                    $altapHechos++;
                } else {
                    $conotHechos++;
                }
            }

            foreach ($plan['altap'] as $alta) {
                $talles = [];
                foreach ($alta['talles'] as $medida => $cant) {
                    $cant = (float) $cant;
                    if ($cant <= 0) {
                        continue;
                    }
                    $talles[] = [
                        'id' => null,
                        'talle_id' => $alta['talle_ids'][$medida],
                        'cantidad' => $cant,
                        'precio' => $alta['precio'],
                    ];
                }
                if ($talles === []) {
                    continue;
                }
                $movService->guardaArticuloMovimiento('create', [
                    'fecha' => $ahora,
                    'fechajornada' => $ahora,
                    'tipotransaccion_id' => $tipoAlta,
                    'pedido_combinacion_id' => null,
                    'ordentrabajo_id' => $alta['ordentrabajo_id'] ?: null,
                    'lote' => $alta['lote'] ?: 0,
                    'articulo_id' => $alta['articulo_id'],
                    'combinacion_id' => $alta['combinacion_id'],
                    'modulo_id' => null,
                    'concepto' => 'Reemplazo stock Excel Ferli L12 (ALTAP)',
                    'cantidad' => $alta['pares'],
                    'precio' => $alta['precio'],
                    'costo' => 0,
                    'descuento' => null,
                    'descuentointegrado' => null,
                    'moneda_id' => null,
                    'incluyeimpuesto' => null,
                    'listaprecio_id' => null,
                    'deposito_id' => $alta['deposito_id'],
                ], $talles);
                $altapHechos++;
            }
        });

        return ['conot' => $conotHechos, 'altap' => $altapHechos];
    }

    public static function assertEntorno(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            throw new RuntimeException('Solo Calzados Ferli. EMPRESA='.config('app.empresa'));
        }
        $db = (string) config('database.connections.'.config('database.default').'.database');
        if ($db !== self::DB_PERMITIDA) {
            throw new RuntimeException('Solo anitaERP_l12. DB actual='.$db);
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  \Illuminate\Support\Collection<string, Depmae>  $depositos
     * @param  \Illuminate\Support\Collection<string, Talle>  $tallesPorNombre
     * @return array<string, mixed>
     */
    private static function resolverFila(array $fila, $depositos, $tallesPorNombre): array
    {
        $errores = [];
        $sku = (string) $fila['sku'];
        $articulo = self::buscarArticuloPorSku($sku);
        if (! $articulo) {
            $errores[] = 'SKU no encontrado: '.$fila['sku_excel'];
        }
        $combinacion = null;
        if ($articulo) {
            $sku = (string) $articulo->sku;
            $cod = (string) $fila['codigo_combinacion'];
            $combinacion = self::resolverCombinacion(
                (int) $articulo->id,
                $cod,
                (string) ($fila['descripcion'] ?? '')
            );
            if (! $combinacion) {
                $errores[] = 'Combinación '.$cod.' no encontrada para SKU '.$sku;
            }
        }
        $depKey = strtolower((string) $fila['deposito_codigo']);
        $deposito = $depositos->get($depKey);
        if (! $deposito) {
            $errores[] = 'Depósito inexistente: '.(string) $fila['deposito_codigo'];
        }

        $talleIds = [];
        foreach ($fila['talles'] as $medida => $cant) {
            $talle = $tallesPorNombre->get((string) $medida);
            if (! $talle) {
                $errores[] = 'Talle inexistente: '.$medida;
                continue;
            }
            $talleIds[(int) $medida] = (int) $talle->id;
        }

        $lote = 0;
        $otId = 0;
        $otCodigo = '';
        $loteInventado = false;
        $ident = trim((string) $fila['identificador']);
        if ($ident !== '' && preg_match('/^\d+$/', $ident)) {
            $ot = Ordentrabajo::query()->where('codigo', $ident)->first();
            if ($ot) {
                $otId = (int) $ot->id;
                $otCodigo = (string) $ot->codigo;
            } else {
                $lote = (int) $ident;
            }
        } elseif ($ident !== '') {
            $errores[] = 'Identificador no numérico: '.$ident;
        } elseif (self::esDepositoLugano((string) $fila['deposito_codigo'])) {
            $lote = self::siguienteLoteInventado();
            $loteInventado = true;
        } else {
            $errores[] = 'Sin lote ni OT';
        }

        return [
            'archivo' => $fila['archivo'],
            'fila' => $fila['fila'],
            'sku_excel' => $fila['sku_excel'],
            'sku' => $sku,
            'articulo_id' => $articulo?->id,
            'combinacion_id' => $combinacion?->id,
            'codigo_combinacion' => $combinacion?->codigo ?? $fila['codigo_combinacion'],
            'deposito_codigo' => $fila['deposito_codigo'],
            'deposito_id' => $deposito?->id,
            'lote' => $lote,
            'lote_inventado' => $loteInventado,
            'ordentrabajo_id' => $otId,
            'ordentrabajo_codigo' => $otCodigo,
            'modulos' => $fila['modulos'],
            'pares' => $fila['pares'],
            'precio' => $fila['precio'],
            'talles' => $fila['talles'],
            'talle_ids' => $talleIds,
            'errores' => $errores,
        ];
    }

    private static function buscarArticuloPorSku(string $sku): ?Articulo
    {
        $articulo = Articulo::query()->where('sku', $sku)->first();
        if ($articulo) {
            return $articulo;
        }
        if (preg_match('/^(\d{2})(\d{3})(\d{2})$/', $sku, $m)) {
            return Articulo::query()->where('sku', $m[1].'0'.$m[2].$m[3])->first();
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private static function calcularProximoLote(array $filas): int
    {
        $max = (int) DB::table('articulo_movimiento')->max('lote');
        foreach ($filas as $fila) {
            $ident = trim((string) ($fila['identificador'] ?? ''));
            if (preg_match('/^\d+$/', $ident)) {
                $n = (int) $ident;
                if ($n > $max) {
                    $max = $n;
                }
            }
        }

        return $max + 1;
    }

    private static function siguienteLoteInventado(): int
    {
        if (self::$proximoLoteInventado === null) {
            self::$proximoLoteInventado = self::calcularProximoLote([]);
        }
        $lote = self::$proximoLoteInventado;
        self::$proximoLoteInventado++;

        return $lote;
    }

    private static function esDepositoLugano(string $codigo): bool
    {
        $k = strtolower(trim($codigo));

        return $k === '10' || $k === 'lugano';
    }

    private static function resolverCombinacion(int $articuloId, string $cod, string $desc): ?Combinacion
    {
        if ($cod !== '') {
            $combinacion = Combinacion::query()
                ->where('articulo_id', $articuloId)
                ->where(function ($q) use ($cod) {
                    $q->where('codigo', $cod)
                        ->orWhere('codigo', str_pad($cod, 2, '0', STR_PAD_LEFT));
                })
                ->first();
            if ($combinacion) {
                return $combinacion;
            }
        }

        $normDesc = self::normColor($desc);
        $normDesc = preg_replace('/^\d+-/', '', $normDesc) ?? $normDesc;
        if ($normDesc === '') {
            return null;
        }

        $combos = Combinacion::query()->where('articulo_id', $articuloId)->get();
        $exactos = [];
        $cerca = [];
        foreach ($combos as $combo) {
            $normCombo = self::normColor((string) $combo->nombre);
            if ($normCombo === '' ) {
                continue;
            }
            if ($normCombo === $normDesc || str_contains($normCombo, $normDesc) || str_contains($normDesc, $normCombo)) {
                $exactos[] = $combo;
                continue;
            }
            if (strlen($normDesc) >= 4 && levenshtein($normCombo, $normDesc) <= 1) {
                $cerca[] = $combo;
            }
        }
        if (count($exactos) === 1) {
            return $exactos[0];
        }
        if (count($cerca) === 1) {
            return $cerca[0];
        }

        return null;
    }

    private static function normColor(string $s): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        $t = strtoupper(trim($t !== false ? $t : $s));

        return preg_replace('/[^A-Z0-9]/', '', $t) ?? '';
    }

    /**
     * @param  list<int>  $articuloIds  Obligatorio: solo anula saldos de esos artículos (los del Excel). Vacío = no tocar nada.
     * @return array<string, mixed>
     */
    private static function planConot(array $articuloIds = []): array
    {
        if ($articuloIds === []) {
            return [
                'grupos' => [],
                'grupos_count' => 0,
                'pares' => 0.0,
                'por_deposito' => [],
                'muestra' => [],
                'pares_lote_cero' => 0.0,
                'pares_positivos' => 0.0,
                'pares_negativos' => 0.0,
                'grupos_conot' => 0,
                'grupos_ajuste' => 0,
            ];
        }

        $rows = DB::table('articulo_movimiento as am')
            ->where('am.lote', '>', 0)
            ->whereIn('am.articulo_id', $articuloIds)
            ->groupBy('am.lote', 'am.articulo_id', 'am.combinacion_id', 'am.deposito_id')
            ->havingRaw('ABS(SUM(am.cantidad)) > 0.0001')
            ->get([
                'am.lote',
                'am.articulo_id',
                'am.combinacion_id',
                'am.deposito_id',
                DB::raw('SUM(am.cantidad) as cantidad'),
                DB::raw('MAX(am.precio) as precio'),
                DB::raw('MAX(am.ordentrabajo_id) as ordentrabajo_id'),
                DB::raw('MAX(am.modulo_id) as modulo_id'),
            ]);

        $talleRows = DB::table('articulo_movimiento as am')
            ->join('articulo_movimiento_talle as amt', 'amt.articulo_movimiento_id', '=', 'am.id')
            ->where('am.lote', '>', 0)
            ->whereIn('am.articulo_id', $articuloIds)
            ->groupBy('am.lote', 'am.articulo_id', 'am.combinacion_id', 'am.deposito_id', 'amt.talle_id')
            ->havingRaw('ABS(SUM(amt.cantidad)) > 0.0001')
            ->get([
                'am.lote',
                'am.articulo_id',
                'am.combinacion_id',
                'am.deposito_id',
                'amt.talle_id',
                DB::raw('SUM(amt.cantidad) as cantidad'),
            ]);

        $tallesPorGrupo = [];
        foreach ($talleRows as $t) {
            $key = implode('|', [$t->lote, $t->articulo_id, $t->combinacion_id, $t->deposito_id]);
            $tallesPorGrupo[$key][] = [
                'talle_id' => (int) $t->talle_id,
                'cantidad' => (float) $t->cantidad,
            ];
        }

        $depNombres = Depmae::query()->pluck('codigo', 'id');
        $grupos = [];
        $pares = 0.0;
        $paresPos = 0.0;
        $paresNeg = 0.0;
        $porDep = [];
        foreach ($rows as $row) {
            $cant = (float) $row->cantidad;
            if (abs($cant) <= 0.0001) {
                continue;
            }
            $key = implode('|', [$row->lote, $row->articulo_id, $row->combinacion_id, $row->deposito_id]);
            $depCod = (string) ($depNombres[(int) $row->deposito_id] ?? $row->deposito_id);
            $tallesGrupo = $tallesPorGrupo[$key] ?? [];
            $grupos[] = [
                'lote' => $row->lote,
                'articulo_id' => (int) $row->articulo_id,
                'combinacion_id' => (int) $row->combinacion_id,
                'deposito_id' => (int) $row->deposito_id,
                'deposito_codigo' => $depCod,
                'modulo_id' => (int) $row->modulo_id,
                'ordentrabajo_id' => (int) $row->ordentrabajo_id,
                'precio' => (float) $row->precio,
                'talles' => $tallesGrupo,
                'pares' => $cant,
                'tipo' => $cant > 0 ? 'CONOT' : 'ALTAP_AJUSTE',
            ];
            $pares += $cant;
            if ($cant > 0) {
                $paresPos += $cant;
            } else {
                $paresNeg += $cant;
            }
            $porDep[$depCod] = ($porDep[$depCod] ?? 0) + $cant;
        }

        $paresLoteCero = (float) (DB::table('articulo_movimiento')
            ->where(function ($q) {
                $q->whereNull('lote')->orWhere('lote', '<=', 0);
            })
            ->sum('cantidad') ?? 0);

        return [
            'grupos' => $grupos,
            'grupos_count' => count($grupos),
            'pares' => $pares,
            'por_deposito' => $porDep,
            'muestra' => array_slice($grupos, 0, 15),
            'pares_lote_cero' => $paresLoteCero,
            'pares_positivos' => $paresPos,
            'pares_negativos' => $paresNeg,
            'grupos_conot' => count(array_filter($grupos, fn ($g) => ($g['pares'] ?? 0) > 0)),
            'grupos_ajuste' => count(array_filter($grupos, fn ($g) => ($g['pares'] ?? 0) < 0)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, float>
     */
    private static function sumarPor(array $filas, string $campo, string $valor): array
    {
        $out = [];
        foreach ($filas as $f) {
            $k = (string) ($f[$campo] ?? '');
            $out[$k] = ($out[$k] ?? 0) + (float) ($f[$valor] ?? 0);
        }
        arsort($out);

        return $out;
    }
}
