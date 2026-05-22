<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_CLIENTE_URL = 'ventas/cliente';

    private const PERMISO_LISTAR = 'listar-notas-suitecrm-cliente';

    private const PERMISO_GESTIONAR = 'gestionar-notas-suitecrm-cliente';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_CLIENTE_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $permisoListarId = $this->upsertPermiso(
            self::PERMISO_LISTAR,
            'Listar notas SuiteCRM en clientes',
            $menuId
        );
        $permisoGestionarId = $this->upsertPermiso(
            self::PERMISO_GESTIONAR,
            'Gestionar notas SuiteCRM en clientes (alta, edición y baja)',
            $menuId
        );

        $this->copiarRolesDesdePermiso('editar-clientes', $permisoListarId);
        $this->copiarRolesDesdePermiso('actualizar-clientes', $permisoGestionarId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        foreach ([self::PERMISO_LISTAR, self::PERMISO_GESTIONAR] as $slug) {
            $permisoId = DB::table('permiso')->where('slug', $slug)->value('id');
            if ($permisoId) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }
    }

    private function upsertPermiso(string $slug, string $nombre, int $menuId): int
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('id', $permisoId)->update([
            'nombre' => $nombre,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ]);

        return $permisoId;
    }

    private function copiarRolesDesdePermiso(string $slugReferencia, int $permisoId): void
    {
        $refPermisoId = (int) (DB::table('permiso')->where('slug', $slugReferencia)->value('id') ?? 0);
        if ($refPermisoId === 0) {
            return;
        }

        $rolIds = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all();
        if ($rolIds === []) {
            return;
        }

        $rolIds = DB::table('rol')->whereIn('id', $rolIds)->pluck('id')->all();
        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rid,
                ]);
            }
        }
    }
};
