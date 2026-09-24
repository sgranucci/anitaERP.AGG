<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ferli: el ítem de menú Stock → Transferencia y el permiso crear-transferencia
 * solo estaban en 5 roles; el resto no veía Transferencia ni el botón Pendientes.
 * Propaga menú + permiso crear a todos los roles, y agrega atajo
 * «Pendientes de transferencia» bajo Stock (mis-aprobaciones?fuente=transferencia).
 */
return new class extends Migration
{
    private const MENU_TRANSFERENCIA_URL = 'stock/transferencia-mercaderia';

    private const MENU_PENDIENTES_URL = 'mis-aprobaciones?fuente=transferencia';

    private const MENU_PENDIENTES_NOMBRE = 'Pendientes de transferencia';

    private const PERMISO_CREAR = 'crear-transferencia-mercaderia';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $now = now();
        $rolIds = DB::table('rol')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $menuTmId = (int) (DB::table('menu')->where('url', self::MENU_TRANSFERENCIA_URL)->value('id') ?? 0);
        if ($menuTmId > 0) {
            $this->asignarMenuARoles($menuTmId, $rolIds);
        }

        $permisoCrearId = (int) (DB::table('permiso')->where('slug', self::PERMISO_CREAR)->value('id') ?? 0);
        if ($permisoCrearId <= 0) {
            $permisoCrearId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Crear transferencia de mercadería',
                'slug' => self::PERMISO_CREAR,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $this->asignarPermisoARoles($permisoCrearId, $rolIds);

        $stockMenuId = (int) (DB::table('menu')->where('id', $menuTmId)->value('menu_id') ?? 0);
        if ($stockMenuId <= 0) {
            $stockMenuId = (int) (DB::table('menu')->where('nombre', 'Módulo de Stock')->where('url', '#')->value('id') ?? 0);
        }

        if ($stockMenuId > 0) {
            $pendientesId = (int) (DB::table('menu')->where('url', self::MENU_PENDIENTES_URL)->value('id') ?? 0);
            if ($pendientesId <= 0) {
                $orden = (int) (DB::table('menu')->where('menu_id', $stockMenuId)->max('orden') ?? 0) + 1;
                $pendientesId = (int) DB::table('menu')->insertGetId([
                    'nombre' => self::MENU_PENDIENTES_NOMBRE,
                    'url' => self::MENU_PENDIENTES_URL,
                    'menu_id' => $stockMenuId,
                    'orden' => $orden,
                    'icono' => 'fa-inbox',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $this->asignarMenuARoles($pendientesId, $rolIds);
            $this->asignarMenuARoles($stockMenuId, $rolIds);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        DB::table('menu')->where('url', self::MENU_PENDIENTES_URL)->delete();
        SuitecrmPermiso::flushCachePermisos();
    }

    /** @param  list<int>  $rolIds */
    private function asignarMenuARoles(int $menuId, array $rolIds): void
    {
        if ($menuId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            $existe = DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    /** @param  list<int>  $rolIds */
    private function asignarPermisoARoles(int $permisoId, array $rolIds): void
    {
        if ($permisoId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            $existe = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
