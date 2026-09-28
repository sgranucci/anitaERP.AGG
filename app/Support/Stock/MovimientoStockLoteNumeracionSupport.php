<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Articulo_Movimiento_Talle;
use Illuminate\Support\Facades\Log;

/**
 * Si se corrige la numeración (curva de talles) de un lote en un movimiento,
 * los traslados de ese lote arrastraban la curva vieja y el stock del depósito
 * destino seguía mostrándola. Este soporte mueve el mismo cambio de talles
 * por los pares que ya habían salido, sin tocar consumos.
 */
final class MovimientoStockLoteNumeracionSupport
{
    private const EPSILON = 0.0001;

    /**
     * Curvas de talles del movimiento, por lote + artículo + combinación + depósito.
     *
     * @return array<string, array{lote: string, articulo_id: int, combinacion_id: int, deposito_id: int, talles: array<int, float>}>
     */
    public static function leerCurvasMovimiento(int $movimientoStockId): array
    {
        if ($movimientoStockId <= 0) {
            return [];
        }

        $movimientos = Articulo_Movimiento::query()
            ->with('articulo_movimiento_talles')
            ->where('movimientostock_id', $movimientoStockId)
            ->get();

        $out = [];
        foreach ($movimientos as $movimiento) {
            $lote = trim((string) ($movimiento->lote ?? ''));
            if ($lote === '' || $lote === '0') {
                continue;
            }
            $clave = implode('|', [
                $lote,
                (int) $movimiento->articulo_id,
                (int) ($movimiento->combinacion_id ?? 0),
                (int) ($movimiento->deposito_id ?? 0),
            ]);
            if (! isset($out[$clave])) {
                $out[$clave] = [
                    'lote' => $lote,
                    'articulo_id' => (int) $movimiento->articulo_id,
                    'combinacion_id' => (int) ($movimiento->combinacion_id ?? 0),
                    'deposito_id' => (int) ($movimiento->deposito_id ?? 0),
                    'talles' => [],
                ];
            }
            foreach ($movimiento->articulo_movimiento_talles as $talle) {
                $talleId = (int) $talle->talle_id;
                $out[$clave]['talles'][$talleId] = (float) ($out[$clave]['talles'][$talleId] ?? 0)
                    + (float) $talle->cantidad;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, array{lote: string, articulo_id: int, combinacion_id: int, deposito_id: int, talles: array<int, float>}>  $curvasAntes
     */
    public static function propagarTrasGuardar(int $movimientoStockId, array $curvasAntes): void
    {
        if (! MovimientoStockFerliSupport::esCalzadosFerli() || $curvasAntes === [] || $movimientoStockId <= 0) {
            return;
        }

        $curvasDespues = self::leerCurvasMovimiento($movimientoStockId);
        foreach ($curvasAntes as $clave => $grupoAntes) {
            $grupoDespues = $curvasDespues[$clave] ?? null;
            if ($grupoDespues === null) {
                continue;
            }
            $tallesNuevos = $grupoDespues['talles'] ?? [];
            if (array_sum($tallesNuevos) < -self::EPSILON) {
                continue;
            }
            $delta = self::deltaTalles($grupoAntes['talles'] ?? [], $tallesNuevos);
            if ($delta === [] || abs(array_sum($delta)) > 0.05) {
                continue;
            }
            $ajustados = self::aplicarDeltaEnTraslados(
                (string) $grupoAntes['lote'],
                (int) $grupoAntes['articulo_id'],
                (int) $grupoAntes['combinacion_id'],
                (int) $grupoAntes['deposito_id'],
                $delta,
                $movimientoStockId
            );
            if ($ajustados > 0) {
                Log::info('MovimientoStock: numeración propagada a traslados del lote', [
                    'movimientostock_id' => $movimientoStockId,
                    'lote' => $grupoAntes['lote'],
                    'articulo_id' => $grupoAntes['articulo_id'],
                    'traslados' => $ajustados,
                ]);
            }
        }
    }

    /**
     * Cambio de la curva absoluta del traslado (positivo = más pares de ese talle).
     * Solo reasigna pares: lo que se saca de un talle se suma en otro, y nunca más
     * de lo que el traslado realmente movió.
     *
     * @param  array<int, float>  $deltaPorTalle  nuevo − anterior en el movimiento editado
     * @param  array<int, float>  $tallesTraslado cantidades absolutas del traslado
     * @return array<int, float>
     */
    public static function ajusteTrasladoPorDelta(array $deltaPorTalle, array $tallesTraslado): array
    {
        $reducciones = [];
        $totalReduccion = 0.0;
        foreach ($deltaPorTalle as $talleId => $delta) {
            $delta = (float) $delta;
            if ($delta >= -self::EPSILON) {
                continue;
            }
            $disponible = (float) ($tallesTraslado[(int) $talleId] ?? 0);
            $mover = min($disponible, abs($delta));
            if ($mover <= self::EPSILON) {
                continue;
            }
            $reducciones[(int) $talleId] = $mover;
            $totalReduccion += $mover;
        }

        $aumentos = [];
        $totalAumento = 0.0;
        foreach ($deltaPorTalle as $talleId => $delta) {
            $delta = (float) $delta;
            if ($delta <= self::EPSILON) {
                continue;
            }
            $cupo = $totalReduccion - $totalAumento;
            if ($cupo <= self::EPSILON) {
                break;
            }
            $mover = min($delta, $cupo);
            if ($mover <= self::EPSILON) {
                continue;
            }
            $aumentos[(int) $talleId] = $mover;
            $totalAumento += $mover;
        }

        if ($totalAumento + self::EPSILON < $totalReduccion) {
            $cupo = $totalAumento;
            $ajustadas = [];
            foreach ($reducciones as $talleId => $mover) {
                if ($cupo <= self::EPSILON) {
                    break;
                }
                $tomar = min($mover, $cupo);
                if ($tomar > self::EPSILON) {
                    $ajustadas[$talleId] = $tomar;
                }
                $cupo -= $tomar;
            }
            $reducciones = $ajustadas;
        }

        $aplicar = [];
        foreach ($reducciones as $talleId => $mover) {
            $aplicar[$talleId] = -$mover;
        }
        foreach ($aumentos as $talleId => $mover) {
            $aplicar[$talleId] = (float) ($aplicar[$talleId] ?? 0) + $mover;
        }

        return array_filter(
            $aplicar,
            static fn ($cambio): bool => abs((float) $cambio) > self::EPSILON
        );
    }

    /**
     * @param  array<int, float>  $deltaPorTalle
     */
    public static function aplicarDeltaEnTraslados(
        string $lote,
        int $articuloId,
        int $combinacionId,
        int $depositoOrigenId,
        array $deltaPorTalle,
        int $excluirMovimientoStockId
    ): int {
        $delta = $deltaPorTalle;
        $pares = self::paresTraslado($lote, $articuloId, $combinacionId, $depositoOrigenId, $excluirMovimientoStockId);
        $ajustados = 0;

        foreach ($pares as $par) {
            $aplicar = self::ajusteTrasladoPorDelta($delta, $par['talles']);
            if ($aplicar === []) {
                continue;
            }
            self::aplicarCambioEnMovimiento((int) $par['salida_id'], $aplicar, -1);
            self::aplicarCambioEnMovimiento((int) $par['entrada_id'], $aplicar, 1);
            foreach ($aplicar as $talleId => $cambio) {
                $delta[$talleId] = (float) ($delta[$talleId] ?? 0) - (float) $cambio;
            }
            $ajustados++;
        }

        return $ajustados;
    }

    /**
     * @param  array<int, float>  $antes
     * @param  array<int, float>  $despues
     * @return array<int, float>
     */
    public static function deltaTalles(array $antes, array $despues): array
    {
        $ids = array_unique(array_merge(array_keys($antes), array_keys($despues)));
        $delta = [];
        foreach ($ids as $talleId) {
            $diff = (float) ($despues[$talleId] ?? 0) - (float) ($antes[$talleId] ?? 0);
            if (abs($diff) > self::EPSILON) {
                $delta[(int) $talleId] = $diff;
            }
        }

        return $delta;
    }

    /**
     * @return list<array{salida_id: int, entrada_id: int, talles: array<int, float>}>
     */
    private static function paresTraslado(
        string $lote,
        int $articuloId,
        int $combinacionId,
        int $depositoOrigenId,
        int $excluirMovimientoStockId
    ): array {
        $query = Articulo_Movimiento::query()
            ->with('articulo_movimiento_talles')
            ->where('lote', $lote)
            ->where('articulo_id', $articuloId)
            ->where(function ($q) use ($excluirMovimientoStockId) {
                $q->whereNull('movimientostock_id');
                if ($excluirMovimientoStockId > 0) {
                    $q->orWhere('movimientostock_id', '<>', $excluirMovimientoStockId);
                }
            });
        if ($combinacionId > 0) {
            $query->where('combinacion_id', $combinacionId);
        }

        $movimientos = $query->orderBy('id')->get();
        $usadas = [];
        $pares = [];

        foreach ($movimientos as $salida) {
            if ((int) ($salida->deposito_id ?? 0) !== $depositoOrigenId) {
                continue;
            }
            if ((float) $salida->cantidad >= -self::EPSILON) {
                continue;
            }
            $tallesSalida = self::curvaAbsoluta($salida);
            if ($tallesSalida === []) {
                continue;
            }
            foreach ($movimientos as $entrada) {
                $entradaId = (int) $entrada->id;
                if (isset($usadas[$entradaId]) || $entradaId === (int) $salida->id) {
                    continue;
                }
                if ((int) ($entrada->deposito_id ?? 0) === $depositoOrigenId) {
                    continue;
                }
                if (abs((float) $entrada->cantidad + (float) $salida->cantidad) > 0.05) {
                    continue;
                }
                $tallesEntrada = self::curvaAbsoluta($entrada);
                if (! self::curvasIguales($tallesSalida, $tallesEntrada)) {
                    continue;
                }
                $usadas[$entradaId] = true;
                $usadas[(int) $salida->id] = true;
                $pares[] = [
                    'salida_id' => (int) $salida->id,
                    'entrada_id' => $entradaId,
                    'talles' => $tallesSalida,
                ];
                break;
            }
        }

        return $pares;
    }

    /**
     * @return array<int, float>
     */
    private static function curvaAbsoluta(Articulo_Movimiento $movimiento): array
    {
        $curva = [];
        foreach ($movimiento->articulo_movimiento_talles as $talle) {
            $cantidad = abs((float) $talle->cantidad);
            if ($cantidad <= self::EPSILON) {
                continue;
            }
            $talleId = (int) $talle->talle_id;
            $curva[$talleId] = (float) ($curva[$talleId] ?? 0) + $cantidad;
        }

        return $curva;
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private static function curvasIguales(array $a, array $b): bool
    {
        $ids = array_unique(array_merge(array_keys($a), array_keys($b)));
        foreach ($ids as $talleId) {
            if (abs((float) ($a[$talleId] ?? 0) - (float) ($b[$talleId] ?? 0)) > 0.05) {
                return false;
            }
        }

        return $ids !== [];
    }

    /**
     * @param  array<int, float>  $aplicarAbs  cambio de la curva absoluta
     */
    private static function aplicarCambioEnMovimiento(int $movimientoId, array $aplicarAbs, int $signo): void
    {
        $precio = (float) (Articulo_Movimiento_Talle::query()
            ->where('articulo_movimiento_id', $movimientoId)
            ->value('precio') ?? 0);

        foreach ($aplicarAbs as $talleId => $cambioAbs) {
            $cambioAbs = (float) $cambioAbs;
            if (abs($cambioAbs) <= self::EPSILON) {
                continue;
            }
            $fila = Articulo_Movimiento_Talle::query()
                ->where('articulo_movimiento_id', $movimientoId)
                ->where('talle_id', (int) $talleId)
                ->first();
            $actual = $fila ? (float) $fila->cantidad : 0.0;
            $nuevo = $actual + ($cambioAbs * $signo);
            if (abs($nuevo) <= self::EPSILON) {
                if ($fila) {
                    $fila->delete();
                }
                continue;
            }
            if ($fila) {
                $fila->cantidad = $nuevo;
                $fila->save();
                continue;
            }
            Articulo_Movimiento_Talle::query()->create([
                'articulo_movimiento_id' => $movimientoId,
                'talle_id' => (int) $talleId,
                'cantidad' => $nuevo,
                'precio' => $precio,
            ]);
        }
    }
}
