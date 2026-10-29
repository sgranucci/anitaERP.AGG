<?php

namespace App\Support\Ventas\Ferli;

use App\Models\Ventas\Pedido_Combinacion;
use App\Support\Stock\ReporteStockOtSituacionSupport;
use App\Support\Ventas\PedidoPickingFerliSupport;
use Illuminate\Support\Facades\DB;

/**
 * En la consulta de la OT, separa los pares que siguen en stock
 * de los que un picking ya se llevó.
 */
final class OtConsultaDivisionPickingSupport
{
    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public static function dividir(array $filas, int $ordentrabajoId, string $codigoOt): array
    {
        if ($filas === [] || $ordentrabajoId <= 0 || ! PedidoPickingFerliSupport::habilitado()) {
            return $filas;
        }

        $tomados = self::paresTomadosPorPicking($filas, $ordentrabajoId, trim($codigoOt));
        if ($tomados === []) {
            return $filas;
        }

        $lineas = Pedido_Combinacion::query()
            ->with([
                'pedidos.clientes.tipossuspensioncliente',
                'articulos',
                'combinaciones',
                'pickingCabecera',
                'pedido_combinacion_talles.talles',
            ])
            ->whereIn('id', array_keys($tomados))
            ->get()
            ->keyBy('id');

        $indices = array_keys($filas);
        usort($indices, static function (int $a, int $b) use ($filas): int {
            $aStock = ReporteStockOtSituacionSupport::esClienteStock($filas[$a]['cliente_id'] ?? 0) ? 0 : 1;
            $bStock = ReporteStockOtSituacionSupport::esClienteStock($filas[$b]['cliente_id'] ?? 0) ? 0 : 1;

            return $aStock <=> $bStock;
        });

        $extras = [];
        foreach ($tomados as $pedidoCombinacionId => $info) {
            $linea = $lineas->get($pedidoCombinacionId);
            if (! $linea) {
                continue;
            }

            $resto = $info['talles'];
            foreach ($indices as $idx) {
                if (self::sumaCurva($resto) <= 0.0001) {
                    break;
                }
                if ((int) ($filas[$idx]['articulo_id'] ?? 0) !== (int) $linea->articulo_id) {
                    continue;
                }
                if ((int) ($filas[$idx]['combinacion_id'] ?? 0) !== (int) $linea->combinacion_id) {
                    continue;
                }
                self::descontarDeFila($filas[$idx], $resto);
            }

            $extras[] = self::filaPicking($linea, $info);
        }

        foreach ($filas as &$fila) {
            unset($fila['medidas_saldo']);
        }
        unset($fila);

        return array_merge($filas, $extras);
    }

    /**
     * Neto todavía afuera de stock, por línea de pedido que no es de esta OT.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return array<int, array{total: float, talles: array<int, float>}>
     */
    private static function paresTomadosPorPicking(array $filas, int $ordentrabajoId, string $codigoOt): array
    {
        $propios = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila['id'] ?? 0);
            if ($id > 0) {
                $propios[$id] = $id;
            }
        }

        $query = DB::table('articulo_movimiento as am')
            ->join('articulo_movimiento_talle as amt', 'amt.articulo_movimiento_id', '=', 'am.id')
            ->where('am.pedido_combinacion_id', '>', 0)
            ->where(function ($q) use ($ordentrabajoId, $codigoOt) {
                $q->where('am.ordentrabajo_id', $ordentrabajoId);
                if ($codigoOt !== '' && $codigoOt !== '0') {
                    $q->orWhere('am.lote', $codigoOt);
                }
            })
            ->where(function ($q) {
                $q->where('am.concepto', PedidoPickingFerliSupport::CONCEPTO_CONSUMO_OT)
                    ->orWhere('am.concepto', 'like', PedidoPickingFerliSupport::CONCEPTO_DEVOLUCION_PICKING_PREFIJO.'%')
                    ->orWhere('am.concepto', 'like', PedidoPickingFerliSupport::CONCEPTO_DEVOLUCION_NC_PICKING_PREFIJO.'%');
            });

        if ($propios !== []) {
            $query->whereNotIn('am.pedido_combinacion_id', array_values($propios));
        }

        $neto = [];
        foreach ($query->get(['am.pedido_combinacion_id', 'amt.talle_id', 'amt.cantidad']) as $row) {
            $pcId = (int) $row->pedido_combinacion_id;
            $talleId = (int) $row->talle_id;
            if ($pcId <= 0 || $talleId <= 0) {
                continue;
            }
            $neto[$pcId][$talleId] = (float) ($neto[$pcId][$talleId] ?? 0) + (float) $row->cantidad;
        }

        $tomados = [];
        foreach ($neto as $pcId => $talles) {
            $curva = [];
            $total = 0.0;
            foreach ($talles as $talleId => $cantidad) {
                if ($cantidad >= -0.0001) {
                    continue;
                }
                $abs = abs((float) $cantidad);
                $curva[(int) $talleId] = $abs;
                $total += $abs;
            }
            if ($total > 0.0001) {
                $tomados[(int) $pcId] = [
                    'total' => $total,
                    'talles' => $curva,
                ];
            }
        }

        return $tomados;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<int, float>  $resto
     */
    private static function descontarDeFila(array &$fila, array &$resto): void
    {
        if (! isset($fila['medidas_saldo']) || ! is_array($fila['medidas_saldo'])) {
            $fila['medidas_saldo'] = is_array($fila['medidas'] ?? null) ? $fila['medidas'] : [];
            $fila['pares_fabricacion'] = (float) ($fila['pares'] ?? 0);
        }

        $restante = 0.0;
        $desconto = false;
        foreach ($fila['medidas_saldo'] as &$medida) {
            $talleId = (int) ($medida['talle'] ?? 0);
            $hay = (float) ($medida['cantidad'] ?? 0);
            $quiere = (float) ($resto[$talleId] ?? 0);
            $toma = min($hay, max(0.0, $quiere));
            if ($toma > 0.0001) {
                $resto[$talleId] = $quiere - $toma;
                $medida['cantidad'] = $hay - $toma;
                $desconto = true;
            }
            $restante += (float) $medida['cantidad'];
        }
        unset($medida);

        if (! $desconto) {
            return;
        }

        $fila['pares'] = $restante;
        $fila['pares_titulo'] = 'Quedan en stock';
    }

    /**
     * @param  array{total: float, talles: array<int, float>}  $info
     * @return array<string, mixed>
     */
    private static function filaPicking(Pedido_Combinacion $linea, array $info): array
    {
        $cliente = $linea->pedidos?->clientes;
        $nombres = [];
        $precios = [];
        foreach ($linea->pedido_combinacion_talles as $pct) {
            $talleId = (int) $pct->talle_id;
            $nombres[$talleId] = (string) ($pct->talles?->nombre ?? '');
            $precios[$talleId] = $pct->precio;
        }

        $medidas = [];
        foreach ($info['talles'] as $talleId => $cantidad) {
            if ($cantidad <= 0.0001) {
                continue;
            }
            $medidas[] = [
                'talle' => (int) $talleId,
                'nombretalle' => $nombres[(int) $talleId] ?? '',
                'cantidad' => $cantidad,
                'precio' => $precios[(int) $talleId] ?? 0,
            ];
        }

        $codigoPicking = (int) ($linea->pickingCabecera?->codigo ?? 0);
        $pickingId = (int) ($linea->picking_id ?? $linea->pickingCabecera?->id ?? 0);

        return [
            'id' => (int) $linea->id,
            'codigo' => $linea->pedido_id,
            'pedidocombinacion_id' => (int) $linea->id,
            'descuentopie' => $linea->pedidos?->descuento ?? 0,
            'cliente' => $cliente?->nombre ?? '',
            'cliente_id' => $cliente?->id ?? '',
            'estadocliente' => $cliente?->estado ?? '',
            'tiposuspensioncliente_id' => $cliente?->tiposuspension_id ?? '',
            'nombretiposuspensioncliente' => $cliente?->tipossuspensioncliente?->nombre ?? '',
            'politica_comercial' => [],
            'articulo' => $linea->articulos?->descripcion ?? '',
            'sku' => $linea->articulos?->sku ?? '',
            'articulo_id' => (int) ($linea->articulos?->id ?? $linea->articulo_id),
            'modulo_id' => $linea->modulo_id,
            'pares' => (float) $info['total'],
            'combinacion_id' => (int) $linea->combinacion_id,
            'nombre_combinacion' => $linea->combinaciones?->nombre ?? '',
            'medidas' => $medidas,
            'es_picking' => true,
            'picking_id' => $pickingId,
            'picking_codigo' => $codigoPicking,
            'pares_titulo' => $codigoPicking > 0
                ? 'Se llevó el picking #'.$codigoPicking
                : 'Se llevó el picking',
        ];
    }

    /**
     * @param  array<int, float>  $curva
     */
    private static function sumaCurva(array $curva): float
    {
        $total = 0.0;
        foreach ($curva as $cantidad) {
            if ($cantidad > 0) {
                $total += (float) $cantidad;
            }
        }

        return $total;
    }
}
