<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/rendicionmaquinavending';

    private const MENU_PADRE_RENDICIONES_URL = '#';

    private const MENU_REF_ROLES_URL = 'caja/rendicionreceptivo';

    public function up(): void
    {
        $padreId = $this->resolverMenuRendicionesId();
        $refMenuId = (int) (DB::table('menu')->where('url', self::MENU_REF_ROLES_URL)->value('id') ?? 0);

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(self::MENU_URL, 'Rendiciones vending', $padreId, $orden, 'fa-cube');

        $slugs = [
            ['nombre' => 'Listar rendiciones vending caja', 'slug' => 'listar-rendicion-maquinavending-caja'],
            ['nombre' => 'Ingresar rendición vending caja', 'slug' => 'crear-rendicion-maquinavending-caja'],
            ['nombre' => 'Editar rendición vending caja', 'slug' => 'editar-rendicion-maquinavending-caja'],
            ['nombre' => 'Actualizar rendición vending caja', 'slug' => 'actualizar-rendicion-maquinavending-caja'],
            ['nombre' => 'Borrar rendición vending caja', 'slug' => 'borrar-rendicion-maquinavending-caja'],
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

        return $id > 0 ? $id : 262;
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
            'listar-rendicion-maquinavending-caja',
            'crear-rendicion-maquinavending-caja',
            'editar-rendicion-maquinavending-caja',
            'actualizar-rendicion-maquinavending-caja',
            'borrar-rendicion-maquinavending-caja',
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
