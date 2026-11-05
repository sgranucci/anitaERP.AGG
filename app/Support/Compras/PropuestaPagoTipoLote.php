<?php

declare(strict_types=1);

namespace App\Support\Compras;

/**
 * Lotes separados al estilo AGG: urgentes por transferencia y cheques de un día.
 * LEGACY conserva propuestas armadas por rango de vencimiento.
 */
final class PropuestaPagoTipoLote
{
    public const URGENTE_TRF = 'URGENTE_TRF';

    public const CHEQUE = 'CHEQUE';

    public const LEGACY = 'LEGACY_VENCIMIENTO';

    /** @return list<string> */
    public static function todos(): array
    {
        return [self::URGENTE_TRF, self::CHEQUE, self::LEGACY];
    }

    public static function normalizar(?string $tipo): string
    {
        $tipo = strtoupper(trim((string) $tipo));

        return in_array($tipo, self::todos(), true) ? $tipo : self::LEGACY;
    }

    public static function etiqueta(?string $tipo): string
    {
        return match (self::normalizar($tipo)) {
            self::URGENTE_TRF => 'Urgentes',
            self::CHEQUE => 'Cheques',
            default => 'Por vencimiento',
        };
    }

    public static function esProgramado(?string $tipo): bool
    {
        return in_array(self::normalizar($tipo), [self::URGENTE_TRF, self::CHEQUE], true);
    }

    public static function esTransferencia(string $medio): bool
    {
        return PropuestaPagoLineaPresentacionSupport::abreviaturaAnita($medio) === 'Transf';
    }

    public static function esCheque(string $medio): bool
    {
        $corto = PropuestaPagoLineaPresentacionSupport::abreviaturaAnita($medio);

        return $corto === 'Cheque' || $corto === 'V.Cheq';
    }

    public static function familia(string $medio): string
    {
        if (self::esTransferencia($medio)) {
            return 'TRF';
        }
        if (self::esCheque($medio)) {
            return 'CHEQUE';
        }

        return 'OTRO';
    }

    public static function admite(string $tipo, string $medio): bool
    {
        return match (self::normalizar($tipo)) {
            self::URGENTE_TRF => self::esTransferencia($medio),
            self::CHEQUE => self::esCheque($medio),
            default => true,
        };
    }
};
