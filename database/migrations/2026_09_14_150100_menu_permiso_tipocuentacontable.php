<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Configuración → Configuración por módulo → Contable → Tipos de cuenta contable
 */
return new class extends Migration
{
    private const MENU_URL = 'contable/tipocuentacontable';

    private const MENU_NOMBRE = 'Tipos de cuenta contable';

    private const GRUPO_URL = '#configuracion-por-modulo';

    /** @var list<array{0: string, 1: string}> */
    private const PERMISOS = [
        ['listar-tipo-cuenta-contable', 'Listar tipos de cuenta contable'],
        ['editar-tipo-cuenta-contable', 'Editar tipos de cuenta contable'],
        ['actualizar-tipo-cuenta-contable', 'Actualizar tipos de cuenta contable'],
    ];

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-contaduría'];

    public function up(): void
    {
        $configRootId = $this->resolverMenuConfiguracionId();
        $grupoId = (int) (DB::table('menu')->where('url', self::GRUPO_URL)->value('id') ?? 0);
        $moduloId = $this->resolverModuloContableId($grupoId);
        if ($configRootId <= 0 || $grupoId <= 0 || $moduloId <= 0) {
            return;
        }

        $menuId = $this->upsertMenu($moduloId);
        $rolIds = $this->resolverRolIds();

        foreach (self::PERMISOS as [$slug, $nombre]) {
            $permisoId = $this->upsertPermiso($slug, $nombre, $menuId);
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        foreach ([$menuId, $moduloId, $grupoId, $configRootId] as $mid) {
            foreach ($rolIds as $rolId) {
                DB::table('menu_rol')->updateOrInsert(
                    ['menu_id' => $mid, 'rol_id' => $rolId],
                    []
                );
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $slugs = array_column(self::PERMISOS, 0);
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();
        if ($permisoIds !== []) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenu(int $moduloId): int
    {
        $id = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $orden = (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0) + 1;
        $payload = [
            'menu_id' => $moduloId,
            'nombre' => self::MENU_NOMBRE,
            'url' => self::MENU_URL,
            'orden' => $orden,
            'icono' => 'fa-list-ol',
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
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

    private function resolverModuloContableId(int $grupoId): int
    {
        if ($grupoId <= 0) {
            return 0;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', $grupoId)
            ->where('nombre', 'Contable')
            ->value('id') ?? 0);
    }

    private function resolverMenuConfiguracionId(): int
    {
        $id = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        if ($id > 0) {
            return $id;
        }
        foreach (['Configuración', 'Módulo Configuración', 'Configuracion'] as $nombre) {
            $id = (int) (DB::table('menu')
                ->where('nombre', $nombre)
                ->where('url', '#')
                ->orderBy('id')
                ->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }
};
