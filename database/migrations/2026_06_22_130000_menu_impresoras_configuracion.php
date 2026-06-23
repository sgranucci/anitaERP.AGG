<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_PADRE_NOMBRE = 'Impresoras';

    private const MENU_PADRE_ICONO = 'fa-print';

    private const MENU_PADRE_ORDEN = 18;

    /** @var list<array{url: string, nombre: string, icono: string|null, orden: int}> */
    private const HIJOS = [
        [
            'url' => 'configuracion/salida',
            'nombre' => 'Salidas de impresión',
            'icono' => null,
            'orden' => 1,
        ],
        [
            'url' => 'configuracion/ubicacion-impresora',
            'nombre' => 'Ubicación de impresoras',
            'icono' => 'fa-map-marker',
            'orden' => 2,
        ],
        [
            'url' => 'configuracion/uso-salida-impresora',
            'nombre' => 'Usos de impresoras',
            'icono' => 'fa-tags',
            'orden' => 3,
        ],
    ];

    public function up(): void
    {
        $configMenuId = $this->resolverModuloConfiguracionId();
        if ($configMenuId === 0) {
            return;
        }

        $padreId = $this->upsertMenuPadre($configMenuId);
        $rolIds = $this->rolesDesdeHijos();

        foreach (self::HIJOS as $hijo) {
            $menuId = (int) (DB::table('menu')->where('url', $hijo['url'])->value('id') ?? 0);
            if ($menuId === 0) {
                continue;
            }

            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $padreId,
                'nombre' => $hijo['nombre'],
                'orden' => $hijo['orden'],
                'icono' => $hijo['icono'],
                'updated_at' => now(),
            ]);
        }

        $this->vincularRolesMenu($padreId, $rolIds);
        $this->compactarOrdenConfiguracion($configMenuId, $padreId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $configMenuId = $this->resolverModuloConfiguracionId();
        if ($configMenuId === 0) {
            return;
        }

        $padreId = (int) (DB::table('menu')
            ->where('menu_id', $configMenuId)
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        if ($padreId === 0) {
            return;
        }

        $ordenSalida = self::MENU_PADRE_ORDEN;
        $ordenUbicacion = $ordenSalida + 3;
        $ordenUsos = $ordenSalida + 4;

        $revertir = [
            'configuracion/salida' => ['nombre' => 'Salidas de Impresión', 'icono' => 'fa-print', 'orden' => $ordenSalida],
            'configuracion/ubicacion-impresora' => ['nombre' => 'Ubic. impresoras', 'icono' => 'fa-map-marker', 'orden' => $ordenUbicacion],
            'configuracion/uso-salida-impresora' => ['nombre' => 'Usos impresora', 'icono' => 'fa-tags', 'orden' => $ordenUsos],
        ];

        foreach ($revertir as $url => $meta) {
            DB::table('menu')->where('url', $url)->update([
                'menu_id' => $configMenuId,
                'nombre' => $meta['nombre'],
                'orden' => $meta['orden'],
                'icono' => $meta['icono'],
                'updated_at' => now(),
            ]);
        }

        DB::table('menu_rol')->where('menu_id', $padreId)->delete();
        DB::table('menu')->where('id', $padreId)->delete();

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverModuloConfiguracionId(): int
    {
        $id = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')
            ->where('nombre', 'like', '%Configuraci%')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function upsertMenuPadre(int $configMenuId): int
    {
        $padreId = (int) (DB::table('menu')
            ->where('menu_id', $configMenuId)
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        if ($padreId === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $configMenuId,
                'nombre' => self::MENU_PADRE_NOMBRE,
                'url' => '#',
                'orden' => self::MENU_PADRE_ORDEN,
                'icono' => self::MENU_PADRE_ICONO,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $padreId)->update([
            'menu_id' => $configMenuId,
            'nombre' => self::MENU_PADRE_NOMBRE,
            'orden' => self::MENU_PADRE_ORDEN,
            'icono' => self::MENU_PADRE_ICONO,
            'updated_at' => now(),
        ]);

        return $padreId;
    }

    /** @return list<int> */
    private function rolesDesdeHijos(): array
    {
        $urls = array_column(self::HIJOS, 'url');
        $menuIds = DB::table('menu')->whereIn('url', $urls)->pluck('id')->all();

        if ($menuIds === []) {
            return [];
        }

        return DB::table('menu_rol')
            ->whereIn('menu_id', $menuIds)
            ->pluck('rol_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /** @param list<int> $rolIds */
    private function vincularRolesMenu(int $menuId, array $rolIds): void
    {
        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }

    private function compactarOrdenConfiguracion(int $configMenuId, int $padreId): void
    {
        DB::table('menu')
            ->where('menu_id', $configMenuId)
            ->where('orden', '>', 20)
            ->where('id', '!=', $padreId)
            ->decrement('orden', 2);
    }
};
