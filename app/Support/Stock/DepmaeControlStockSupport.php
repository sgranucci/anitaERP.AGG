<?php

namespace App\Support\Stock;

use App\Models\Stock\Depmae;

/**
 * Reglas de control de stock según tipo de depósito (depmae.tipodeposito)
 * y flag global de stock negativo (config stock.permite_saldos_negativos).
 *
 * Centro de consumo: no mantiene inventario controlado; salidas y transferencias
 * desde ese depósito no validan saldo (siempre consumo).
 *
 * Resto (Normal, Excedente, Consignacion, Transito, Temporal, Interno, Formulas):
 * aplica validación de saldo en salidas y en transferencias desde depósito origen,
 * salvo que STOCK_PERMITE_SALDOS_NEGATIVOS=true (entonces se muestra saldo pero no bloquea).
 */
final class DepmaeControlStockSupport
{
    public const TIPO_CENTRO_CONSUMO = 'Centro de consumo';

    public static function manejaControlStock(?Depmae $deposito): bool
    {
        if ($deposito === null) {
            return true;
        }

        return ! self::esCentroDeConsumo((string) ($deposito->tipodeposito ?? ''));
    }

    /**
     * true = no bloquear grabado por cantidad > saldo (config global).
     */
    public static function permiteSaldosNegativos(): bool
    {
        return (bool) config('stock.permite_saldos_negativos', false);
    }

    /**
     * true = hay que rechazar salidas si la cantidad supera el saldo disponible.
     * Combina tipo de depósito + STOCK_PERMITE_SALDOS_NEGATIVOS.
     */
    public static function debeValidarSaldoDisponible(?Depmae $deposito): bool
    {
        if (self::permiteSaldosNegativos()) {
            return false;
        }

        return self::manejaControlStock($deposito);
    }

    public static function esCentroDeConsumo(?string $tipodeposito): bool
    {
        $tipo = trim((string) ($tipodeposito ?? ''));
        if ($tipo === '') {
            return false;
        }

        if (strcasecmp($tipo, self::TIPO_CENTRO_CONSUMO) === 0 || strcasecmp($tipo, 'M') === 0) {
            return true;
        }

        return strcasecmp(Depmae::etiquetaTipoDeposito($tipo), self::TIPO_CENTRO_CONSUMO) === 0;
    }
}
