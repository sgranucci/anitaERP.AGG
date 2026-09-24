<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Articulo_Movimiento_Talle;
use App\Models\Stock\Depmae;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Restaura saldos anulados por CONOT del import Excel cuando el lote/OT seguía
 * EN PRODUCCION en la planilla (omitido de ALTAP) y el ERP quedó en cero.
 *
 * Solo anitaERP_l12 / Ferli. No escribe en Anita.
 */
final class FerliExcelStockRestaurarConotOmitidasSupport
{
    public const CONCEPTO_CONOT = 'Reemplazo stock Excel Ferli L12 (CONOT)';

    public const CONCEPTO_RESTAURA_PREFIJO = 'Restaura CONOT omitido EN PRODUCCION #';

    /**
     * @return array{
     *   lotes_excel: list<int>,
     *   candidatos: list<array<string, mixed>>,
     *   pares: float,
     *   movimientos: int
     * }
     */
    public static function planificar(string $dir): array
    {
        self::assertEntorno();

        $filas = FerliExcelStockImportParser::parseDirectory($dir);
        $lotesExcel = [];
        foreach ($filas as $fila) {
            if (! ($fila['en_produccion'] || ($fila['omitir'] ?? null) === 'en_produccion')) {
                continue;
            }
            foreach (FerliExcelStockImportPlanner::identificadoresNumericosFila($fila) as $ident) {
                $lotesExcel[$ident] = true;
            }
        }
        $lotes = array_keys($lotesExcel);
        sort($lotes);

        if ($lotes === []) {
            return [
                'lotes_excel' => [],
                'candidatos' => [],
                'pares' => 0.0,
                'movimientos' => 0,
            ];
        }

        $conots = Articulo_Movimiento::query()
            ->with(['articulo_movimiento_talles', 'articulos:id,sku,descripcion'])
            ->where('concepto', self::CONCEPTO_CONOT)
            ->where('cantidad', '<', 0)
            ->where('lote', '>', 0)
            ->whereIn('lote', $lotes)
            ->orderBy('id')
            ->get();

        $depNombres = Depmae::query()->pluck('codigo', 'id');
        $candidatos = [];
        $pares = 0.0;

        foreach ($conots as $mov) {
            if (self::yaRestaurado((int) $mov->id)) {
                continue;
            }

            $lote = (int) $mov->lote;
            $articuloId = (int) $mov->articulo_id;
            $combinacionId = (int) $mov->combinacion_id;
            $depositoId = (int) $mov->deposito_id;

            $saldo = (float) Articulo_Movimiento::query()
                ->where('lote', $lote)
                ->where('articulo_id', $articuloId)
                ->where('combinacion_id', $combinacionId)
                ->where('deposito_id', $depositoId)
                ->sum('cantidad');

            // Solo reponer si el CONOT dejó el bucket en cero (no hubo ALTAP de reemplazo).
            if (abs($saldo) > 0.0001) {
                continue;
            }

            $paresMov = abs((float) $mov->cantidad);
            $talles = [];
            foreach ($mov->articulo_movimiento_talles as $t) {
                $cant = abs((float) $t->cantidad);
                if ($cant < 0.0001) {
                    continue;
                }
                $talles[] = [
                    'talle_id' => (int) $t->talle_id,
                    'cantidad' => $cant,
                    'precio' => (float) $t->precio,
                ];
            }

            $sku = (string) ($mov->articulos->sku ?? '');
            $desc = (string) ($mov->articulos->descripcion ?? '');
            $candidatos[] = [
                'conot_id' => (int) $mov->id,
                'lote' => $lote,
                'ordentrabajo_id' => (int) ($mov->ordentrabajo_id ?? 0),
                'articulo_id' => $articuloId,
                'combinacion_id' => $combinacionId,
                'deposito_id' => $depositoId,
                'deposito_codigo' => (string) ($depNombres[$depositoId] ?? $depositoId),
                'sku' => $sku,
                'descripcion' => $desc,
                'pares' => $paresMov,
                'precio' => (float) ($mov->precio ?? 0),
                'modulo_id' => (int) ($mov->modulo_id ?? 0),
                'talles' => $talles,
                'saldo_actual' => $saldo,
            ];
            $pares += $paresMov;
        }

        return [
            'lotes_excel' => $lotes,
            'candidatos' => $candidatos,
            'pares' => $pares,
            'movimientos' => count($candidatos),
        ];
    }

    /**
     * @param  array{candidatos: list<array<string, mixed>>}  $plan
     * @return array{restaurados: int, pares: float}
     */
    public static function ejecutar(array $plan): array
    {
        self::assertEntorno();

        $tipoAlta = (int) config('consprod.TIPOTRANSACCION_ALTA_PRODUCCION', 3);
        $ahora = Carbon::now();
        $restaurados = 0;
        $pares = 0.0;

        DB::transaction(function () use ($plan, $tipoAlta, $ahora, &$restaurados, &$pares) {
            foreach ($plan['candidatos'] as $c) {
                $conotId = (int) $c['conot_id'];
                if ($conotId <= 0 || self::yaRestaurado($conotId)) {
                    continue;
                }
                $tallesPayload = [];
                foreach ($c['talles'] as $t) {
                    $tallesPayload[] = [
                        'talle_id' => $t['talle_id'],
                        'cantidad' => $t['cantidad'],
                        'precio' => $t['precio'],
                        'pedido_combinacion_talle_id' => null,
                    ];
                }
                if ($tallesPayload === [] && (float) $c['pares'] <= 0.0001) {
                    continue;
                }

                $mov = Articulo_Movimiento::query()->create([
                    'fecha' => $ahora->toDateString(),
                    'fechajornada' => $ahora->toDateString(),
                    'tipotransaccion_id' => $tipoAlta,
                    'pedido_combinacion_id' => null,
                    'ordentrabajo_id' => ($c['ordentrabajo_id'] ?? 0) > 0 ? $c['ordentrabajo_id'] : null,
                    'lote' => $c['lote'],
                    'articulo_id' => $c['articulo_id'],
                    'combinacion_id' => $c['combinacion_id'],
                    'modulo_id' => ($c['modulo_id'] ?? 0) > 0 ? $c['modulo_id'] : null,
                    'concepto' => self::CONCEPTO_RESTAURA_PREFIJO.$conotId,
                    'cantidad' => (float) $c['pares'],
                    'precio' => $c['precio'] ?? 0,
                    'costo' => 0,
                    'descuento' => null,
                    'descuentointegrado' => null,
                    'moneda_id' => null,
                    'incluyeimpuesto' => null,
                    'listaprecio_id' => null,
                    'deposito_id' => $c['deposito_id'],
                ]);

                foreach ($tallesPayload as $t) {
                    Articulo_Movimiento_Talle::query()->create([
                        'articulo_movimiento_id' => $mov->id,
                        'pedido_combinacion_talle_id' => null,
                        'talle_id' => $t['talle_id'],
                        'cantidad' => $t['cantidad'],
                        'precio' => $t['precio'],
                    ]);
                }

                $restaurados++;
                $pares += (float) $c['pares'];
            }
        });

        return ['restaurados' => $restaurados, 'pares' => $pares];
    }

    public static function yaRestaurado(int $conotId): bool
    {
        if ($conotId <= 0) {
            return false;
        }

        return Articulo_Movimiento::query()
            ->where('concepto', self::CONCEPTO_RESTAURA_PREFIJO.$conotId)
            ->exists();
    }

    public static function assertEntorno(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            throw new RuntimeException('Solo Calzados Ferli. EMPRESA='.config('app.empresa'));
        }
        $db = (string) config('database.connections.'.config('database.default').'.database');
        if ($db !== FerliExcelStockImportPlanner::DB_PERMITIDA) {
            throw new RuntimeException('Solo anitaERP_l12. DB actual='.$db);
        }
    }
}
