<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Extiende permisos Importar remitos Anita a roles operativos (El Bierzo).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISOS = [
        'listar-importar-remito-anita',
        'ejecutar-importar-remito-anita',
    ];

    /** Roles con crear-remitos / listar-remitos operativos en El Bierzo. */
    private const ROLES = [
        'administrador',
        'Despacho',
        'Vendedor',
        'Enc-admin',
        'Adm-pedidos-senasa',
        'Facturacion',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', 'ventas/importar-remito-anita')->value('id') ?? 0);

        foreach (self::ROLES as $rolNombre) {
            $rolId = (int) (DB::table('rol')->where('nombre', $rolNombre)->value('id') ?? 0);
            if ($rolId <= 0) {
                continue;
            }

            if ($menuId > 0
                && ! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }

            foreach (self::PERMISOS as $slug) {
                $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
                if ($permisoId <= 0) {
                    continue;
                }
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $rolesOperativos = array_values(array_filter(
            self::ROLES,
            static fn ($n) => $n !== 'administrador'
        ));

        foreach ($rolesOperativos as $rolNombre) {
            $rolId = (int) (DB::table('rol')->where('nombre', $rolNombre)->value('id') ?? 0);
            if ($rolId <= 0) {
                continue;
            }

            foreach (self::PERMISOS as $slug) {
                $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
                if ($permisoId > 0) {
                    DB::table('permiso_rol')
                        ->where('permiso_id', $permisoId)
                        ->where('rol_id', $rolId)
                        ->delete();
                }
            }

            $menuId = (int) (DB::table('menu')->where('url', 'ventas/importar-remito-anita')->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')
                    ->where('menu_id', $menuId)
                    ->where('rol_id', $rolId)
                    ->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
