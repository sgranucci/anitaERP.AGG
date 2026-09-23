<?php

namespace App\Support\Stock;

use App\Models\Stock\Tipotransaccion_Stock;

/**
 * Canje de stock (color/talle u otros): un comprobante con líneas que entran (+) y salen (−).
 */
final class MovimientoStockCanjeSupport
{
    public const OPERACION = 'C';

    public const ABREVIATURA = 'CANJE';

    public const SENTIDO_ENTRA = 'E';

    public const SENTIDO_SALE = 'S';

    public static function esTipoCanje(?Tipotransaccion_Stock $tipo): bool
    {
        if ($tipo === null) {
            return false;
        }

        return strtoupper(trim((string) ($tipo->operacion ?? ''))) === self::OPERACION;
    }

    public static function sentidoDesdeCantidad(float $cantidad): string
    {
        return $cantidad < 0 ? self::SENTIDO_SALE : self::SENTIDO_ENTRA;
    }

    /**
     * Convierte sentidos[] + cantidades[] absolutas en cantidades firmadas.
     * Idempotente si las cantidades ya vienen firmadas y no hay sentidos.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizarPayloadFormulario(array $data, ?Tipotransaccion_Stock $tipo): array
    {
        if (! self::esTipoCanje($tipo)) {
            return $data;
        }

        $cantidades = array_values((array) ($data['cantidades'] ?? []));
        $sentidos = array_values((array) ($data['sentidos'] ?? []));
        $articulos = array_values((array) ($data['articulos_id'] ?? []));
        $n = max(count($cantidades), count($articulos), count($sentidos));

        $firmadas = [];
        for ($i = 0; $i < $n; $i++) {
            $articuloId = (int) ($articulos[$i] ?? 0);
            $raw = (float) str_replace(',', '.', (string) ($cantidades[$i] ?? 0));
            if ($articuloId <= 0 && abs($raw) < 1e-9) {
                $firmadas[$i] = $raw;
                continue;
            }

            $sentido = strtoupper(trim((string) ($sentidos[$i] ?? '')));
            if ($sentido === self::SENTIDO_SALE || $sentido === self::SENTIDO_ENTRA) {
                $abs = abs($raw);
                $firmadas[$i] = $sentido === self::SENTIDO_SALE ? -$abs : $abs;
            } else {
                // Sin sentido: respetar signo cargado (edición / API).
                $firmadas[$i] = $raw;
            }
        }

        $data['cantidades'] = $firmadas;
        $data['cantidad_ya_firmada'] = true;
        $data['signo_cantidad'] = 'S'; // placeholder; no se aplica por línea

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok:bool, mensaje:?string}
     */
    public static function validarLineas(array $data): array
    {
        $cantidades = array_values((array) ($data['cantidades'] ?? []));
        $articulos = array_values((array) ($data['articulos_id'] ?? []));
        $sentidos = array_values((array) ($data['sentidos'] ?? []));

        $tieneEntra = false;
        $tieneSale = false;
        $n = max(count($cantidades), count($articulos));

        for ($i = 0; $i < $n; $i++) {
            $articuloId = (int) ($articulos[$i] ?? 0);
            $raw = (float) str_replace(',', '.', (string) ($cantidades[$i] ?? 0));
            if ($articuloId <= 0 || abs($raw) < 1e-9) {
                continue;
            }

            $sentido = strtoupper(trim((string) ($sentidos[$i] ?? '')));
            if ($sentido === self::SENTIDO_SALE) {
                $tieneSale = true;
            } elseif ($sentido === self::SENTIDO_ENTRA) {
                $tieneEntra = true;
            } elseif ($raw < 0) {
                $tieneSale = true;
            } else {
                $tieneEntra = true;
            }
        }

        if (! $tieneEntra && ! $tieneSale) {
            return ['ok' => false, 'mensaje' => 'Indique al menos una línea con artículo y cantidad.'];
        }
        if (! $tieneEntra || ! $tieneSale) {
            return [
                'ok' => false,
                'mensaje' => 'Un canje debe tener al menos una línea que Sale y otra que Entra (mismo depósito).',
            ];
        }

        return ['ok' => true, 'mensaje' => null];
    }

    /**
     * Cantidades absolutas solo de líneas que restan stock (para validar saldo).
     *
     * @param  list<int|string|null>  $articulosId
     * @param  list<int|float|string|null>  $cantidadesFirmadas
     * @param  list<int|string|null>  $coloresId
     * @param  list<int|string|null>  $tallesId
     * @return array{0: list<int>, 1: list<float>, 2: list<int>, 3: list<int>}
     */
    public static function lineasSalidaParaSaldo(
        array $articulosId,
        array $cantidadesFirmadas,
        array $coloresId = [],
        array $tallesId = [],
    ): array {
        $arts = [];
        $cants = [];
        $cols = [];
        $talls = [];

        foreach ($articulosId as $i => $articuloId) {
            $cantidad = (float) ($cantidadesFirmadas[$i] ?? 0);
            if ((int) $articuloId <= 0 || $cantidad >= 0) {
                continue;
            }
            $arts[] = (int) $articuloId;
            $cants[] = abs($cantidad);
            $cols[] = (int) ($coloresId[$i] ?? 0);
            $talls[] = (int) ($tallesId[$i] ?? 0);
        }

        return [$arts, $cants, $cols, $talls];
    }

    public static function tieneLineasSalida(array $cantidadesFirmadas): bool
    {
        foreach ($cantidadesFirmadas as $c) {
            if ((float) $c < 0) {
                return true;
            }
        }

        return false;
    }
}
