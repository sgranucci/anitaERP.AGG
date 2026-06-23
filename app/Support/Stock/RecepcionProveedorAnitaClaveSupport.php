<?php

namespace App\Support\Stock;

use App\Models\Stock\Recepcion_Proveedor;

/**
 * Clave Anita COM para recepmae, recepmov y recpunica.
 * tipo=COM, letra=X, sucursal=código Anita de empresa, nro=numerorecepcion ERP (secuencia COM global).
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
            'letra' => self::letraCom(),
            'sucursal' => self::sucursalEmpresa($recepcion),
            'nro' => (int) $recepcion->numerorecepcion,
        ];
    }

    public static function letraCom(): string
    {
        return AnitaStkmovClaveErpSupport::letra();
    }

    public static function sucursalEmpresa(Recepcion_Proveedor $recepcion): int
    {
        $recepcion->loadMissing('empresas');
        $codigo = (int) ($recepcion->empresas->codigo ?? 0);

        return self::sucursalDesdeEmpresaCodigo($codigo > 0 ? $codigo : (int) $recepcion->empresa_id);
    }

    public static function sucursalDesdeEmpresaCodigo(int $empresaCodigo): int
    {
        return $empresaCodigo > 0 ? $empresaCodigo : 0;
    }

    /**
     * Sucursal virtual 99x usada por error en transferencias/COM (991, 992…).
     */
    public static function esSucursalVirtualLegacy(int $sucursal): bool
    {
        return $sucursal >= 90;
    }

    /** @return array{tipo: string, letra: string, sucursal: int, nro: int} */
    public static function claveDesdeAtributosAlmacenados(Recepcion_Proveedor $recepcion): array
    {
        $cfg = config('recepcion_proveedor.anita');

        return [
            'tipo' => (string) ($recepcion->anita_tipo ?? $cfg['recepcion_tipo']),
            'letra' => (string) ($recepcion->anita_letra ?? self::letraCom()),
            'sucursal' => (int) ($recepcion->anita_sucursal ?? 0),
            'nro' => (int) ($recepcion->anita_nro ?? $recepcion->numerorecepcion),
        ];
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
