<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Stock\Formula_Articulo;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Support\Ventas\GastronomiaFormulaOpcionalSeleccion;

/**
 * Arma ítems de facturación gastronomía con opcionales e impuesto interno (cigarrillos).
 */
final class GastronomiaFacturaItemsPayloadSupport
{
    /**
     * @return array{
     *   articulo_ids:list<int>,
     *   cantidades:list<float>,
     *   precios:list<float>,
     *   descripcionarticulos:list<string>,
     *   impuesto_ids:list<int>,
     *   incluyeimpuestos:list<string>,
     *   opcionales_por_item:array<int, array<string|int, int|string|null>>,
     *   omitir_stkmov_anita_por_item:list<bool>
     * }
     */
    public static function desdeCuenta(CuentaGastronomia $cuenta): array
    {
        $cuenta->loadMissing(['lineas']);

        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        $descripciones = [];
        $impuestoIds = [];
        $incluyeImpuestos = [];
        $opcionalesPorItem = [];
        $omitirStkmovAnita = [];

        foreach ($cuenta->lineas as $linea) {
            $pct = (float) $linea->descuento_linea_pct;
            $precioNet = (float) $linea->precio_unitario * (1 - $pct / 100);

            $indexPadre = count($articuloIds);
            $articuloIds[] = (int) $linea->articulo_id;
            $cantidades[] = (float) $linea->cantidad;
            $precios[] = $precioNet;
            $descripciones[] = '';
            $impuestoIds[] = 0;
            $incluyeImpuestos[] = '1';
            $omitirStkmovAnita[] = false;

            $opcionalesLinea = is_array($linea->opcionales_json) ? $linea->opcionales_json : [];
            $opcionalesPorItem[$indexPadre] = [];
            foreach ($opcionalesLinea as $orden => $valor) {
                $opcionalesPorItem[$indexPadre][(string) $orden] = GastronomiaFormulaOpcionalSeleccion::estaVacio($valor)
                    ? null
                    : $valor;
            }

            foreach ($opcionalesLinea as $valor) {
                $decoded = GastronomiaFormulaOpcionalSeleccion::decodificar($valor);
                if ($decoded === null) {
                    continue;
                }

                $articuloOpcionalId = self::resolverArticuloOpcionalId($decoded);
                if ($articuloOpcionalId === null || $articuloOpcionalId <= 0) {
                    continue;
                }

                $articuloIds[] = $articuloOpcionalId;
                $cantidades[] = (float) $linea->cantidad;
                $precios[] = 0.;
                $descripciones[] = '';
                $impuestoIds[] = 0;
                $incluyeImpuestos[] = '1';
                $omitirStkmovAnita[] = true;
            }
        }

        return self::empaquetar(
            $articuloIds,
            $cantidades,
            $precios,
            $descripciones,
            $impuestoIds,
            $incluyeImpuestos,
            $opcionalesPorItem,
            $omitirStkmovAnita,
        );
    }

    /**
     * Reconstruye ítems desde venta_emision (p. ej. nota de crédito cuando no hay cuenta).
     * Los renglones $0 posteriores a un ítem con precio se tratan como opcionales de fórmula.
     *
     * @return array{
     *   articulo_ids:list<int>,
     *   cantidades:list<float>,
     *   precios:list<float>,
     *   descripcionarticulos:list<string>,
     *   impuesto_ids:list<int>,
     *   incluyeimpuestos:list<string>,
     *   opcionales_por_item:array<int, array<string|int, int|string|null>>,
     *   omitir_stkmov_anita_por_item:list<bool>
     * }
     */
    public static function desdeVentaEmisiones(Venta $venta): array
    {
        $venta->loadMissing(['venta_emisiones']);

        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        $descripciones = [];
        $impuestoIds = [];
        $incluyeImpuestos = [];
        $opcionalesPorItem = [];
        $omitirStkmovAnita = [];

        $indexPadre = null;
        $ordenOpcional = 0;

        foreach ($venta->venta_emisiones->sortBy('numeroitem') as $emision) {
            if (! $emision instanceof Venta_Emision) {
                continue;
            }

            $articuloId = (int) ($emision->articulo_id ?? 0);
            if ($articuloId <= 0) {
                continue;
            }

            $cantidad = (float) ($emision->cantidad ?? 0);
            $precio = (float) ($emision->precio ?? 0);
            $impuestoId = (int) ($emision->impuesto_id ?? 0);
            $incl = (string) ($emision->incluyeimpuesto ?? '1');
            $incluyeImpuesto = in_array($incl, ['S', '1', 'Y'], true) ? '1' : 'N';

            if ($precio > 0.0001) {
                $indexPadre = count($articuloIds);
                $ordenOpcional = 0;
                $opcionalesPorItem[$indexPadre] = [];
                $omitirStkmovAnita[] = false;
            } elseif ($indexPadre === null) {
                continue;
            } else {
                ++$ordenOpcional;
                $opcionalesPorItem[$indexPadre][(string) $ordenOpcional] = $articuloId;
                $omitirStkmovAnita[] = true;
            }

            $articuloIds[] = $articuloId;
            $cantidades[] = $cantidad;
            $precios[] = $precio;
            $descripciones[] = (string) ($emision->detalle ?? '');
            $impuestoIds[] = $impuestoId;
            $incluyeImpuestos[] = $incluyeImpuesto;
        }

        return self::empaquetar(
            $articuloIds,
            $cantidades,
            $precios,
            $descripciones,
            $impuestoIds,
            $incluyeImpuestos,
            $opcionalesPorItem,
            $omitirStkmovAnita,
        );
    }

    /**
     * @param  list<int>  $articuloIds
     * @param  list<float>  $cantidades
     * @param  list<float>  $precios
     * @param  list<string>  $descripciones
     * @param  list<int>  $impuestoIds
     * @param  list<string>  $incluyeImpuestos
     * @param  array<int, array<string|int, int|string|null>>  $opcionalesPorItem
     * @param  list<bool>  $omitirStkmovAnita
     * @return array{
     *   articulo_ids:list<int>,
     *   cantidades:list<float>,
     *   precios:list<float>,
     *   descripcionarticulos:list<string>,
     *   impuesto_ids:list<int>,
     *   incluyeimpuestos:list<string>,
     *   opcionales_por_item:array<int, array<string|int, int|string|null>>,
     *   omitir_stkmov_anita_por_item:list<bool>
     * }
     */
    public static function empaquetar(
        array $articuloIds,
        array $cantidades,
        array $precios,
        array $descripciones,
        array $impuestoIds,
        array $incluyeImpuestos,
        array $opcionalesPorItem,
        array $omitirStkmovAnita,
    ): array {
        if ($articuloIds === []) {
            throw new \InvalidArgumentException('La factura no tiene ítems con artículo para revertir.');
        }

        return [
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'precios' => $precios,
            'descripcionarticulos' => $descripciones,
            'impuesto_ids' => $impuestoIds,
            'incluyeimpuestos' => $incluyeImpuestos,
            'opcionales_por_item' => $opcionalesPorItem,
            'omitir_stkmov_anita_por_item' => $omitirStkmovAnita,
        ];
    }

    /**
     * @param  array{tipo?:string,id?:int}  $decoded
     */
    private static function resolverArticuloOpcionalId(array $decoded): ?int
    {
        if (($decoded['tipo'] ?? '') === 'articulo') {
            return (int) ($decoded['id'] ?? 0);
        }

        if (($decoded['tipo'] ?? '') === 'formula_hija') {
            $sub = Formula_Articulo::query()->find((int) ($decoded['id'] ?? 0));

            return $sub && $sub->articulo_id ? (int) $sub->articulo_id : null;
        }

        return null;
    }
}
