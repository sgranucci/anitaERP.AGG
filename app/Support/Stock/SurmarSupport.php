<?php

namespace App\Support\Stock;

/**
 * Gate y constantes del módulo stock/producción Surmar (El Bierzo).
 * Empresa operativa fija id=3 en Bierzo. En AGG el id 3 es REBISCO: no tratarlo como Surmar.
 */
final class SurmarSupport
{
    public const ORIGEN_CARGA_RECEPCION = 'SURMAR';

    /** El Bierzo: Surmar. AGG: ese id es Rebisco — usar esEmpresaSurmar(). */
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
        if ((int) $empresaId !== self::EMPRESA_ID) {
            return false;
        }

        $nombre = trim((string) \Illuminate\Support\Facades\DB::table('empresa')
            ->where('id', self::EMPRESA_ID)
            ->value('nombre'));

        return $nombre !== '' && stripos($nombre, 'surmar') !== false;
    }

    public static function abortSiNoSurmar(?int $empresaId = null): void
    {
        if ($empresaId === null || ! self::esEmpresaSurmar($empresaId)) {
            abort(404);
        }
    }
}
