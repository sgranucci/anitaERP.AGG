<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'contable/cuentas-automaticas';

    private const MENU_PADRE_NOMBRE = 'Módulo Contable';

    private const PERMISO_EDITAR_SLUG = 'editar-cuentas-automaticas-contables';

    private const PERMISO_EDITAR_NOMBRE = 'Editar cuentas automáticas del sistema';

    private const PERMISO_ACTUALIZAR_SLUG = 'actualizar-cuentas-automaticas-contables';

    private const PERMISO_ACTUALIZAR_NOMBRE = 'Actualizar cuentas automáticas del sistema';

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-contaduría'];

    public function up(): void
    {
        $menuId = $this->upsertMenu();
        $permisoEditarId = $this->upsertPermiso(self::PERMISO_EDITAR_SLUG, self::PERMISO_EDITAR_NOMBRE, $menuId);
        $permisoActualizarId = $this->upsertPermiso(self::PERMISO_ACTUALIZAR_SLUG, self::PERMISO_ACTUALIZAR_NOMBRE, $menuId);

        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoEditarId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoEditarId, 'rol_id' => $rolId]);
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoActualizarId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoActualizarId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenu(): int
    {
        $padreId = $this->resolverMenuContableId();
        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;

        $id = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => 'Cuentas automáticas',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-magic',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padreId,
            'nombre' => 'Cuentas automáticas',
            'orden' => $orden,
            'icono' => 'fa-magic',
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function upsertPermiso(string $slug, string $nombre, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update([
                'nombre' => $nombre,
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function resolverMenuContableId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        return $id > 0 ? $id : 43;
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $slugs = [self::PERMISO_EDITAR_SLUG, self::PERMISO_ACTUALIZAR_SLUG];
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();
        if ($permisoIds !== []) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
