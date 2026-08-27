<?php

namespace App\Support\Stock;

use App\Models\Stock\MovimientoStock;

/**
 * Reverso de transferencia: cada movimiento se deshace solo
 * (mismo artículo, depósito y cantidad; signo invertido).
 * No se reconstruye una TRA dest→origen ni se reaplica conversión de fórmula.
 */
final class TransferenciaMercaderiaLineaReversoSupport
{
    /**
     * @return array{
     *     lineas_documento: list<array<string, mixed>>,
     *     payload_devolver_origen: array<string, mixed>,
     *     payload_quitar_destino: array<string, mixed>,
     *     deposito_devolver_id: int,
     *     bien_devolver_id: int,
     *     deposito_quitar_id: int,
     *     bien_quitar_id: int
     * }
     */
    public static function desdeMovimientos(MovimientoStock $salidaOriginal, MovimientoStock $entradaOriginal): array
    {
        $lineasSalida = self::normalizarLineas($salidaOriginal);
        $lineasEntrada = self::normalizarLineas($entradaOriginal);

        if ($lineasSalida === [] || $lineasEntrada === []) {
            throw new \RuntimeException('La transferencia no tiene líneas de movimiento para revertir.');
        }

        return [
            'lineas_documento' => self::lineasDocumento($lineasSalida, $lineasEntrada),
            'payload_devolver_origen' => self::payloadLineas($lineasSalida),
            'payload_quitar_destino' => self::payloadLineas($lineasEntrada),
            'deposito_devolver_id' => (int) ($lineasSalida[0]['deposito_id'] ?? 0),
            'bien_devolver_id' => (int) ($lineasSalida[0]['bien_uso_id'] ?? 0),
            'deposito_quitar_id' => (int) ($lineasEntrada[0]['deposito_id'] ?? 0),
            'bien_quitar_id' => (int) ($lineasEntrada[0]['bien_uso_id'] ?? 0),
        ];
    }

    /**
     * Documento compensatorio: saca lo que entró en destino y devuelve lo que salió de origen.
     *
     * @param  list<array<string, mixed>>  $lineasSalida
     * @param  list<array<string, mixed>>  $lineasEntrada
     * @return list<array<string, mixed>>
     */
    public static function lineasDocumento(array $lineasSalida, array $lineasEntrada): array
    {
        $n = max(count($lineasSalida), count($lineasEntrada));
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $salida = $lineasSalida[$i] ?? null;
            $entrada = $lineasEntrada[$i] ?? null;
            $cantSalida = $salida !== null ? abs((float) $salida['cantidad']) : 0.0;
            $cantEntrada = $entrada !== null ? abs((float) $entrada['cantidad']) : 0.0;
            $artSalida = $salida !== null ? (int) $salida['articulo_id'] : 0;
            $artEntrada = $entrada !== null ? (int) $entrada['articulo_id'] : 0;

            $out[] = [
                'item' => $i + 1,
                'articulo_origen_id' => $artEntrada,
                'articulo_destino_id' => $artSalida,
                'cantidad_origen' => $cantEntrada,
                'cantidad_destino' => $cantSalida,
                'precio_costo_origen' => $entrada !== null ? (float) ($entrada['precio'] ?? 0) : 0.0,
                'precio_costo_destino' => $salida !== null ? (float) ($salida['precio'] ?? 0) : 0.0,
                'coeficienteconversion' => $cantSalida > 0.000001 ? round($cantEntrada / $cantSalida, 6) : 1.0,
                'fl_conversion_formula' => $artSalida !== $artEntrada || abs($cantSalida - $cantEntrada) > 0.000001,
                'numeroparte' => $entrada['numeroparte'] ?? $salida['numeroparte'] ?? null,
                'caja' => $entrada !== null ? abs((float) ($entrada['caja'] ?? 0)) : 0.0,
                'pieza' => $entrada !== null ? abs((float) ($entrada['pieza'] ?? 0)) : 0.0,
            ];
        }

        return $out;
    }

    /**
     * Payload de movimiento compensatorio: mismas líneas, cantidad absoluta (el signo lo pone el grabado).
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return array<string, mixed>
     */
    public static function payloadLineas(array $lineas): array
    {
        $articulosId = [];
        $cantidades = [];
        $cajas = [];
        $piezas = [];
        $precios = [];
        $items = [];
        $numeropartes = [];
        $colores = [];
        $talles = [];

        foreach ($lineas as $i => $linea) {
            $articulosId[] = (int) ($linea['articulo_id'] ?? 0);
            $cantidades[] = abs((float) ($linea['cantidad'] ?? 0));
            $cajas[] = abs((float) ($linea['caja'] ?? 0));
            $piezas[] = abs((float) ($linea['pieza'] ?? 0));
            $precios[] = (float) ($linea['precio'] ?? 0);
            $items[] = $i;
            $numeropartes[] = (string) ($linea['numeroparte'] ?? '');
            $colores[] = (int) ($linea['color_id'] ?? 0) ?: null;
            $talles[] = (int) ($linea['talle_id'] ?? 0) ?: null;
        }

        $n = count($articulosId);

        return [
            'articulos_id' => $articulosId,
            'skus' => array_fill(0, $n, ''),
            'combinaciones_id' => array_fill(0, $n, null),
            'modulos_id' => array_fill(0, $n, null),
            'items' => $items,
            'cantidades' => $cantidades,
            'cajas' => $cajas,
            'piezas' => $piezas,
            'precios' => $precios,
            'listasprecios_id' => array_fill(0, $n, null),
            'incluyeimpuestos' => array_fill(0, $n, '0'),
            'monedas_id' => array_fill(0, $n, null),
            'descuentos' => array_fill(0, $n, 0),
            'loteids' => array_fill(0, $n, 0),
            'medidas' => [],
            'numeropartes' => $numeropartes,
            'colores_id' => $colores,
            'talles_id' => $talles,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function normalizarLineas(MovimientoStock $movimiento): array
    {
        $out = [];
        foreach ($movimiento->articulos_movimiento as $linea) {
            $cantidad = (float) ($linea->cantidad ?? 0);
            if (abs($cantidad) < 1e-9) {
                continue;
            }
            $out[] = [
                'articulo_id' => (int) $linea->articulo_id,
                'deposito_id' => (int) ($linea->deposito_id ?? 0),
                'bien_uso_id' => (int) ($linea->bien_uso_id ?? 0),
                'cantidad' => $cantidad,
                'precio' => (float) ($linea->precio ?? $linea->costo ?? 0),
                'numeroparte' => $linea->numeroparte,
                'caja' => (float) ($linea->caja ?? 0),
                'pieza' => (float) ($linea->pieza ?? 0),
                'color_id' => (int) ($linea->color_id ?? 0),
                'talle_id' => (int) ($linea->talle_id ?? 0),
            ];
        }

        return $out;
    }
}
