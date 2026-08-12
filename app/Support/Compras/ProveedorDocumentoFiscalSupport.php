<?php

namespace App\Support\Compras;

/**
 * Tipos y reglas de documentos fiscales CUIT / CM05 del padrón proveedor (portal + ABM).
 */
final class ProveedorDocumentoFiscalSupport
{
    public const TIPO_CUIT = 'CUIT';

    public const TIPO_CM05 = 'CM05';

    public const ORIGEN_ABM = 'ABM';

    public const ORIGEN_PORTAL = 'PORTAL';

    /** Días de anticipación para avisar próximo vencimiento. */
    public const DIAS_AVISO_PROXIMO = 30;

    /**
     * @return list<string>
     */
    public static function tipos(): array
    {
        return [self::TIPO_CUIT, self::TIPO_CM05];
    }

    public static function esTipoValido(string $tipo): bool
    {
        return in_array(strtoupper($tipo), self::tipos(), true);
    }

    public static function etiquetaTipo(string $tipo): string
    {
        return match (strtoupper($tipo)) {
            self::TIPO_CUIT => 'Constancia CUIT',
            self::TIPO_CM05 => 'CM05 anual',
            default => $tipo,
        };
    }

    public static function directorioProveedor(int $proveedorId): string
    {
        return public_path('storage/archivos/proveedores/'.$proveedorId.'/fiscal');
    }

    public static function urlArchivo(int $proveedorId, string $nombrearchivo): string
    {
        return asset('storage/archivos/proveedores/'.$proveedorId.'/fiscal/'.$nombrearchivo);
    }

    /**
     * Estado relativo a hoy: vigente | proximo | vencido | sin_fecha.
     */
    public static function estadoVigencia(?string $fechaVencimiento): string
    {
        if ($fechaVencimiento === null || trim($fechaVencimiento) === '') {
            return 'sin_fecha';
        }
        try {
            $venc = \Illuminate\Support\Carbon::parse($fechaVencimiento)->startOfDay();
        } catch (\Throwable) {
            return 'sin_fecha';
        }
        $hoy = now()->startOfDay();
        if ($venc->lt($hoy)) {
            return 'vencido';
        }
        if ($venc->lte($hoy->copy()->addDays(self::DIAS_AVISO_PROXIMO))) {
            return 'proximo';
        }

        return 'vigente';
    }
}
