<?php

namespace App\Support\Stock;

use App\Models\Stock\Depmae;

/**
 * Reglas de control de stock según tipo de depósito (depmae.tipodeposito).
 *
 * Centro de consumo: no mantiene inventario controlado; salidas y transferencias
 * desde ese depósito no validan saldo (siempre consumo).
 *
 * Resto (Normal, Excedente, Consignacion, Transito, Temporal, Interno, Formulas):
 * aplica validación de saldo en salidas y en transferencias desde depósito origen.
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
