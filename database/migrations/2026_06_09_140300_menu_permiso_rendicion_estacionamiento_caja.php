<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/rendicionestacionamiento';

    private const MENU_PADRE_RENDICIONES_URL = '#';

    private const MENU_REF_ROLES_URL = 'caja/rendicionreceptivo';

    public function up(): void
    {
        $padreId = $this->resolverMenuRendicionesId();
        $refMenuId = (int) (DB::table('menu')->where('url', self::MENU_REF_ROLES_URL)->value('id') ?? 0);

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'Rendiciones estacionamiento', $padreId, $orden, 'fa-car');

        $slugs = [
            ['nombre' => 'Listar rendiciones estacionamiento caja', 'slug' => 'listar-rendicion-estacionamiento-caja'],
            ['nombre' => 'Ingresar rendición estacionamiento caja', 'slug' => 'crear-rendicion-estacionamiento-caja'],
            ['nombre' => 'Editar rendición estacionamiento caja', 'slug' => 'editar-rendicion-estacionamiento-caja'],
            ['nombre' => 'Actualizar rendición estacionamiento caja', 'slug' => 'actualizar-rendicion-estacionamiento-caja'],
            ['nombre' => 'Borrar rendición estacionamiento caja', 'slug' => 'borrar-rendicion-estacionamiento-caja'],
        ];

        $this->upsertPermisos($slugs, $menuId, $refMenuId);
    }

    private function resolverMenuRendicionesId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('nombre', 'Rendiciones')
            ->where('url', self::MENU_PADRE_RENDICIONES_URL)
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return 262;
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);

        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padre,
                'nombre' => $nombre,
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padre,
            'nombre' => $nombre,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * @param  array<int, array{nombre:string, slug:string}>  $slugs
     */
    private function upsertPermisos(array $slugs, int $menuId, int $refMenuId): void
    {
        $rolIdsMenuRef = $refMenuId > 0
            ? DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all()
            : [];

        foreach ($slugs as $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);

            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $row['nombre'],
                    'slug' => $row['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $row['nombre'],
                    'updated_at' => now(),
                ]);
            }

            foreach ($rolIdsMenuRef as $rolId) {
                $rid = (int) $rolId;
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
                }
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
                }
            }
        }
    }

    public function down(): void
    {
        $slugs = [
            'listar-rendicion-estacionamiento-caja',
            'crear-rendicion-estacionamiento-caja',
            'editar-rendicion-estacionamiento-caja',
            'actualizar-rendicion-estacionamiento-caja',
            'borrar-rendicion-estacionamiento-caja',
        ];

        foreach (DB::table('permiso')->whereIn('slug', $slugs)->pluck('id') as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        $menuId = DB::table('menu')->where('url', self::MENU_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }
};
