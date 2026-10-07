<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reaplica el alta de menú: la migración anterior buscaba el submenú
 * «Presentaciones ARCA» y en este entorno se llama «Presentaciones a organismos».
 */
return new class extends Migration
{
    private const SUBMENU_NOMBRES = ['Presentaciones a organismos', 'Presentaciones ARCA'];

    private const MENU_PADRE = 'Módulo Contable';

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-contaduría', 'Enc-impuestos', 'Op-impuestos'];

    public function up(): void
    {
        $submenuId = $this->resolverSubmenuId();
        if ($submenuId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $submenuId)->max('orden') ?? 0) + 1;
        $menuSifere = $this->upsertMenu(
            'contable/sifere',
            'SiFeRe percepciones',
            $submenuId,
            $orden,
            'fa-file-invoice-dollar',
        );
        $menuIva = $this->upsertMenu(
            'contable/percepciones-iva',
            'Percepciones de IVA',
            $submenuId,
            $orden + 1,
            'fa-file-invoice',
        );

        $permisos = [
            $menuSifere => [
                ['Listar SiFeRe percepciones', 'listar-sifere'],
                ['Exportar SiFeRe percepciones', 'exportar-sifere'],
            ],
            $menuIva => [
                ['Listar percepciones de IVA', 'listar-percepcion-iva'],
                ['Exportar percepciones de IVA', 'exportar-percepcion-iva'],
            ],
        ];

        $rolIds = $this->resolverRolIds();
        foreach ($rolIds as $rolId) {
            foreach ([$menuSifere, $menuIva, $submenuId] as $mid) {
                if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $mid, 'rol_id' => $rolId]);
                }
            }
        }

        foreach ($permisos as $menuId => $lista) {
            foreach ($lista as [$nombre, $slug]) {
                $permisoId = $this->upsertPermiso($nombre, $slug, $menuId);
                foreach ($rolIds as $rolId) {
                    if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                        DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                    }
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = [
            'listar-sifere', 'exportar-sifere',
            'listar-percepcion-iva', 'exportar-percepcion-iva',
        ];
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }
        $menuIds = DB::table('menu')
            ->whereIn('url', ['contable/sifere', 'contable/percepciones-iva'])
            ->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverSubmenuId(): int
    {
        $juntoASicore = (int) (DB::table('menu')->where('url', 'contable/sicore')->value('menu_id') ?? 0);
        if ($juntoASicore > 0) {
            return $juntoASicore;
        }

        $padreId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($padreId <= 0) {
            return 0;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', $padreId)
            ->whereIn('nombre', self::SUBMENU_NOMBRES)
            ->where('url', '#')
            ->value('id') ?? 0);
    }

    /** @return list<int> */
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

    private function upsertMenu(string $url, string $nombre, int $padreId, int $orden, string $icono): int
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
        $payload = [
            'nombre' => $nombre,
            'slug' => $slug,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }
};
