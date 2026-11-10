<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: el botón Activar/Inactivar del ABM artículo exige cambiar-estado-articulos.
 * administrador ya bypasea can(); Enc-admin y roles de stock/compras suelen editar sin ese slug.
 */
return new class extends Migration
{
    private const SLUG = 'cambiar-estado-articulos';

    /** @var string[] */
    private const ROLES = [
        'administrador',
        'Enc-admin',
        'Enc-stock',
        'Enc-compras',
        'Enc-gastronomía',
        'Ger-gastronomía',
        'Sup-Gastronomia',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        foreach (self::ROLES as $nombreRol) {
            $rolId = (int) (DB::table('rol')->where('nombre', $nombreRol)->value('id') ?? 0);
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
            PermisoCacheSupport::forgetRol($rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        // Solo quitamos los que esta migración pudo haber agregado a roles que
        // históricamente no lo tenían (administrador / Enc-admin / Enc-stock).
        $rolesRevertir = ['administrador', 'Enc-admin', 'Enc-stock'];
        foreach ($rolesRevertir as $nombreRol) {
            $rolId = (int) (DB::table('rol')->where('nombre', $nombreRol)->value('id') ?? 0);
            if ($rolId <= 0) {
                continue;
            }
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->delete();
            PermisoCacheSupport::forgetRol($rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
