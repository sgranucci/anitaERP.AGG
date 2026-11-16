<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú + permiso Consulta Inteligente bajo Dirección (junto al Tablero).
 */
return new class extends Migration
{
    private const URL = 'direccion/consulta-inteligente';

    private const NOMBRE = 'Consulta Inteligente';

    private const ICONO = 'fa-lightbulb';

    private const URL_PADRE = '#direccion';

    private const URL_HERMANO = 'direccion/tablero';

    private const PERMISO_SLUG = 'listar-consulta-inteligente';

    private const PERMISO_NOMBRE = 'Listar consulta inteligente';

    public function up(): void
    {
        $hermano = DB::table('menu')->where('url', self::URL_HERMANO)->first();
        if ($hermano) {
            $padreId = (int) $hermano->menu_id;
            $orden = (int) $hermano->orden + 1;
            $rolIds = DB::table('menu_rol')
                ->where('menu_id', $hermano->id)
                ->pluck('rol_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        } else {
            $padreId = (int) (DB::table('menu')->where('url', self::URL_PADRE)->value('id') ?? 0);
            if ($padreId === 0) {
                $padreId = (int) (DB::table('menu')
                    ->where('menu_id', 0)
                    ->where('nombre', 'like', '%Dirección%')
                    ->orderBy('id')
                    ->value('id') ?? 0);
            }
            if ($padreId === 0) {
                return;
            }
            $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
            $rolIds = DB::table('rol')
                ->where(function ($q) {
                    $q->where('nombre', 'like', '%administrador%')
                        ->orWhere('nombre', 'like', '%Admin%')
                        ->orWhere('nombre', 'like', '%dirección%')
                        ->orWhere('nombre', 'like', '%Direccion%');
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('orden', '>=', $orden)
            ->where('url', '!=', self::URL)
            ->update(['orden' => DB::raw('orden + 1'), 'updated_at' => now()]);

        $menuId = $this->upsertMenu(self::URL, self::NOMBRE, self::ICONO, $padreId, $orden);

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        $permisoId = $this->upsertPermiso(self::PERMISO_NOMBRE, self::PERMISO_SLUG, $menuId);
        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoIds = DB::table('permiso')->where('slug', self::PERMISO_SLUG)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenu(string $url, string $nombre, string $icono, int $padreId, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'url' => $url,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ];

        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = ['nombre' => $nombre, 'slug' => $slug, 'menu_id' => $menuId, 'updated_at' => now()];

        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }
};
