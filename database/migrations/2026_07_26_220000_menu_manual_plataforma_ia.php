<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú Configuración → Manual Plataforma IA.
 */
return new class extends Migration
{
    private const MENU_URL = 'configuracion/manual-ia';

    private const PERMISO_SLUG = 'listar-ai-decisiones';

    public function up(): void
    {
        $padreId = (int) (DB::table('menu')
            ->where('id', 33)
            ->orWhere(function ($q) {
                $q->where('nombre', 'Configuración')->where('url', '#');
            })
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($padreId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'Manual Plataforma IA', $padreId, $orden, 'fa-book');

        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }
        DB::table('menu_rol')->where('menu_id', $menuId)->delete();
        DB::table('menu')->where('id', $menuId)->delete();
        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenu(string $url, string $nombre, int $padreId, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'nombre' => $nombre,
                'menu_id' => $padreId,
                'icono' => $icono,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $padreId,
            'nombre' => $nombre,
            'url' => $url,
            'orden' => $orden,
            'icono' => $icono,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $ids = DB::table('rol')->whereIn('nombre', ['administrador'])->pluck('id')->map(fn ($id) => (int) $id)->all();

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            $extra = DB::table('permiso_rol')->where('permiso_id', $permisoId)->pluck('rol_id')->map(fn ($id) => (int) $id)->all();
            $ids = array_values(array_unique(array_merge($ids, $extra)));
        }

        return $ids;
    }
};
