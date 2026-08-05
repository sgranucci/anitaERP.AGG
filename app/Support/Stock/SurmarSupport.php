<?php

namespace App\Support\Stock;

/**
 * Gate y constantes del módulo stock/producción Surmar (El Bierzo).
 * Empresa operativa fija; procesos propios (menú/permisos) sin tocar AGG.
 */
final class SurmarSupport
{
    public const ORIGEN_CARGA_RECEPCION = 'SURMAR';

    public const EMPRESA_ID = 3;

    public const ORIGEN_COM = 'COM';
    public const ORIGEN_DES = 'DES';
    public const ORIGEN_AP = 'AP';
    public const ORIGEN_TRA = 'TRA';
    public const ORIGEN_IMPORT_ANITA = 'IMPORT_ANITA';

    public const ESTADO_DISPONIBLE = 'DISPONIBLE';
    public const ESTADO_RESERVADA = 'RESERVADA';
    public const ESTADO_CONSUMIDA = 'CONSUMIDA';
    public const ESTADO_ANULADA = 'ANULADA';

    public static function empresaId(): int
    {
        return self::EMPRESA_ID;
    }

    public static function esEmpresaSurmar(?int $empresaId): bool
    {
        return (int) $empresaId === self::EMPRESA_ID;
    }

    public static function abortSiNoSurmar(?int $empresaId = null): void
    {
        if ($empresaId === null || ! self::esEmpresaSurmar($empresaId)) {
            abort(404);
        }
    }
}
