<?php

namespace App\Support\Compras;

final class ComprobanteProveedorAnitaSyncEstado
{
    public const PENDIENTE = 'PENDIENTE';

    public const SYNC_OK = 'SYNC_OK';

    /** Fallo al escribir / sincronizar Anita (el ERP pudo llegar a contabilizar). */
    public const ERROR = 'ERROR';

    /**
     * Rechazo de validación o asiento en el ERP (CC, cuentas, etc.).
     * Se guarda en anita_sync_* para el aviso persistente, pero no es un error de Anita
     * ni entra al barrido del bridge.
     */
    public const ERROR_ERP = 'ERROR_ERP';

    /** Importado desde Anita; no reenviar al bridge. */
    public const IMPORTADO = 'IMPORTADO';

    /** Instalación con bridge en solo lectura: el asiento queda en el ERP y no se graba Anita. */
    public const OMITIDO = 'OMITIDO';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::PENDIENTE,
            self::SYNC_OK,
            self::ERROR,
            self::ERROR_ERP,
            self::IMPORTADO,
            self::OMITIDO,
        ];
    }

    public static function esErrorAnita(?string $estado): bool
    {
        return strtoupper(trim((string) $estado)) === self::ERROR;
    }

    public static function esErrorErp(?string $estado): bool
    {
        return strtoupper(trim((string) $estado)) === self::ERROR_ERP;
    }

    public static function esErrorPersistente(?string $estado): bool
    {
        return self::esErrorAnita($estado) || self::esErrorErp($estado);
    }

    public static function etiquetaBadge(?string $estado, ?string $estadoComprobante = null): string
    {
        if (self::esErrorErp($estado)) {
            return 'Rechazo ERP';
        }
        if (self::esErrorAnita($estado)) {
            $contabilizado = strtoupper(trim((string) $estadoComprobante)) === ComprobanteProveedorEstados::CONTABILIZADO;

            return $contabilizado ? 'Error Anita' : 'No se contabilizó';
        }

        return '';
    }
}
