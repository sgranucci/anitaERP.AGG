<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor;

/**
 * Clave Anita COM para recepmae, recepmov y recpunica.
 * tipo=COM, letra=X, sucursal=código empresa Anita, nro=numerorecepcion ERP (por empresa).
 */
class RecepcionProveedorAnitaClaveSupport
{
    /** @return array{tipo: string, letra: string, sucursal: int, nro: int} */
    public static function resolver(Recepcion_Proveedor $recepcion): array
    {
        $recepcion->loadMissing('empresas');
        $cfg = config('recepcion_proveedor.anita');

        return [
            'tipo' => (string) ($recepcion->anita_tipo ?? $cfg['recepcion_tipo']),
            'letra' => (string) ($recepcion->anita_letra ?? $cfg['recepcion_letra']),
            'sucursal' => self::sucursalEmpresa($recepcion),
            'nro' => (int) $recepcion->numerorecepcion,
        ];
    }

    public static function sucursalEmpresa(Recepcion_Proveedor $recepcion): int
    {
        $recepcion->loadMissing('empresas');
        $codigo = (int) ($recepcion->empresas->codigo ?? 0);

        return $codigo > 0 ? $codigo : (int) $recepcion->empresa_id;
    }

    /** @param array{tipo: string, letra: string, sucursal: int, nro: int} $clave */
    public static function asignarEnRecepcion(Recepcion_Proveedor $recepcion, array $clave): void
    {
        $recepcion->update([
            'anita_tipo' => $clave['tipo'],
            'anita_letra' => $clave['letra'],
            'anita_sucursal' => $clave['sucursal'],
            'anita_nro' => $clave['nro'],
        ]);
    }
}
