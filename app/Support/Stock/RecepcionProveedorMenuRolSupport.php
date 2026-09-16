<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\DB;

/**
 * Permisos de configuración de recepción cuelgan de Configuración → por módulo → Stock,
 * pero en Menú–Rol deben poder gestionarse también desde Stock → Recepción proveedores
 * (mismo patrón que OrdencompraSectorVisibilidadSupport).
 */
final class RecepcionProveedorMenuRolSupport
{
    public const URL_PROCESO = 'stock/recepcion-proveedor';

    /** @var list<string> */
    public const SLUGS_CONFIG = [
        'editar-configuracion-recepcion-proveedor',
        'actualizar-configuracion-recepcion-proveedor',
    ];

    /**
     * @param  list<int>  $menuIds
     * @return list<string>
     */
    public static function slugsExtraParaMenuIds(array $menuIds): array
    {
        if ($menuIds === []) {
            return [];
        }

        $hayProceso = DB::table('menu')
            ->whereIn('id', $menuIds)
            ->where('url', self::URL_PROCESO)
            ->exists();

        return $hayProceso ? self::SLUGS_CONFIG : [];
    }
}
