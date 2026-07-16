<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_MODULO = 'Módulo Solicitudes de Pago';

    private const MENU_MODULO_URL = '#';

    /** @var list<string> */
    private const ROLES = ['administrador', 'Enc-impuestos', 'Op-impuestos'];

    /** @var list<array{url: string, nombre: string, icono: string|null, permisos: list<array{nombre: string, slug: string}>}> */
    private const HIJOS = [
        [
            'url' => 'solicitudpago/sector_solicitudpago',
            'nombre' => 'Sectores',
            'icono' => null,
            'permisos' => [
                ['nombre' => 'Listar sector solicitud de pago', 'slug' => 'listar-sector-solicitud-pago'],
                ['nombre' => 'Crear sector solicitud de pago', 'slug' => 'crear-sector-solicitud-pago'],
                ['nombre' => 'Editar sector solicitud de pago', 'slug' => 'editar-sector-solicitud-pago'],
                ['nombre' => 'Actualizar sector solicitud de pago', 'slug' => 'actualizar-sector-solicitud-pago'],
                ['nombre' => 'Borrar sector solicitud de pago', 'slug' => 'borrar-sector-solicitud-pago'],
            ],
        ],
        [
            'url' => 'solicitudpago/formapagosol',
            'nombre' => 'Formas de pago',
            'icono' => null,
            'permisos' => [
                ['nombre' => 'Listar forma de pago solicitud', 'slug' => 'listar-forma-pago-solicitud'],
                ['nombre' => 'Crear forma de pago solicitud', 'slug' => 'crear-forma-pago-solicitud'],
                ['nombre' => 'Editar forma de pago solicitud', 'slug' => 'editar-forma-pago-solicitud'],
                ['nombre' => 'Actualizar forma de pago solicitud', 'slug' => 'actualizar-forma-pago-solicitud'],
                ['nombre' => 'Borrar forma de pago solicitud', 'slug' => 'borrar-forma-pago-solicitud'],
            ],
        ],
    ];

    public function up(): void
    {
        $moduloExistenteId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_MODULO)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        $ordenModulo = $moduloExistenteId > 0
            ? (int) (DB::table('menu')->where('id', $moduloExistenteId)->value('orden') ?? 0)
            : (int) (DB::table('menu')->where('menu_id', 0)->max('orden') ?? 0) + 1;
        $moduloId = $this->upsertMenu(self::MENU_MODULO_URL, self::MENU_MODULO, 0, $ordenModulo, 'fa-money', true);

        $rolIds = $this->resolverRolIds();
        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($moduloId, $rolId);
        }

        $ordenHijo = (int) (DB::table('menu')->where('menu_id', $moduloId)->max('orden') ?? 0);
        foreach (self::HIJOS as $hijo) {
            $existenteHijoId = (int) (DB::table('menu')->where('url', $hijo['url'])->value('id') ?? 0);
            if ($existenteHijoId > 0) {
                $orden = (int) (DB::table('menu')->where('id', $existenteHijoId)->value('orden') ?? 0);
            } else {
                $ordenHijo++;
                $orden = $ordenHijo;
            }
            $menuId = $this->upsertMenu($hijo['url'], $hijo['nombre'], $moduloId, $orden, $hijo['icono'], false);

            $permisoIds = [];
            foreach ($hijo['permisos'] as $perm) {
                $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
            }

            foreach ($rolIds as $rolId) {
                $this->asegurarMenuRol($menuId, $rolId);
                foreach ($permisoIds as $permisoId) {
                    if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                        DB::table('permiso_rol')->insert([
                            'permiso_id' => $permisoId,
                            'rol_id' => $rolId,
                        ]);
                    }
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = [];
        $urls = [];
        foreach (self::HIJOS as $hijo) {
            $urls[] = $hijo['url'];
            foreach ($hijo['permisos'] as $perm) {
                $slugs[] = $perm['slug'];
            }
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        $menuIds = DB::table('menu')->whereIn('url', $urls)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }

        $moduloId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_MODULO)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        if ($moduloId > 0) {
            DB::table('menu_rol')->where('menu_id', $moduloId)->delete();
            DB::table('menu')->where('id', $moduloId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * Upsert por nombre cuando es módulo raíz (url=#), o por url para hijos.
     */
    private function upsertMenu(
        string $url,
        string $nombre,
        int $padreId,
        int $orden,
        ?string $icono,
        bool $esModulo
    ): int {
        $query = DB::table('menu');
        if ($esModulo) {
            $id = (int) ($query->where('nombre', $nombre)->where('menu_id', 0)->value('id') ?? 0);
        } else {
            $id = (int) ($query->where('url', $url)->value('id') ?? 0);
        }

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

        return (int) DB::table('menu')->insertGetId(array_merge($payload, [
            'created_at' => now(),
        ]));
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

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'created_at' => now(),
        ]));
    }

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        return DB::table('rol')
            ->whereIn('nombre', self::ROLES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
};
