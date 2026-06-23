<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_PADRE_NOMBRE = 'Módulo Contable';

    private const SUBMENU_NOMBRE = 'Aprobaciones y períodos';

    private const MENU_APROBACION_URL = 'contable/aprobacion-asientos';

    private const MENU_CONFIG_URL = 'contable/configuracion-asiento';

    private const MENU_CIERRE_URL = 'contable/cierre-periodo';

    private const MENU_APERTURA_URL = 'contable/apertura-periodo';

    /** @var list<array{nombre: string, slug: string, menu_url: string, roles: list<string>}> */
    private const PERMISOS = [
        [
            'nombre' => 'Listar asientos pendientes de aprobación',
            'slug' => 'listar-aprobacion-asiento',
            'menu_url' => self::MENU_APROBACION_URL,
            'roles' => ['administrador', 'Enc-contaduría', 'Enc-impuestos', 'Enc-admin', 'Ger-administracion'],
        ],
        [
            'nombre' => 'Aprobar asientos contables pendientes',
            'slug' => 'aprobar-asiento-pendiente',
            'menu_url' => self::MENU_APROBACION_URL,
            'roles' => ['administrador', 'Enc-contaduría'],
        ],
        [
            'nombre' => 'Rechazar asientos contables pendientes',
            'slug' => 'rechazar-asiento-pendiente',
            'menu_url' => self::MENU_APROBACION_URL,
            'roles' => ['administrador', 'Enc-contaduría'],
        ],
        [
            'nombre' => 'Editar configuración de aprobación de asientos',
            'slug' => 'editar-configuracion-asiento-contable',
            'menu_url' => self::MENU_CONFIG_URL,
            'roles' => ['administrador', 'Enc-contaduría'],
        ],
        [
            'nombre' => 'Actualizar configuración de aprobación de asientos',
            'slug' => 'actualizar-configuracion-asiento-contable',
            'menu_url' => self::MENU_CONFIG_URL,
            'roles' => ['administrador', 'Enc-contaduría'],
        ],
    ];

    public function up(): void
    {
        $padreId = $this->resolverMenuContableId();
        if ($padreId === 0) {
            return;
        }

        $submenuId = $this->upsertSubmenu($padreId);

        $this->moverMenuBajoSubmenu(self::MENU_CIERRE_URL, $submenuId, 1);
        $this->moverMenuBajoSubmenu(self::MENU_APERTURA_URL, $submenuId, 2);

        $menuAprobacionId = $this->upsertMenu(
            self::MENU_APROBACION_URL,
            'Asientos pendientes',
            $submenuId,
            3,
            'fa-check-circle'
        );
        $menuConfigId = $this->upsertMenu(
            self::MENU_CONFIG_URL,
            'Configuración asientos',
            $submenuId,
            4,
            'fa-cog'
        );

        $roles = $this->resolverRolIds([
            'administrador',
            'Enc-contaduría',
            'Enc-impuestos',
            'Enc-admin',
            'Ger-administracion',
        ]);

        foreach ([$submenuId, $menuAprobacionId, $menuConfigId] as $menuId) {
            foreach ($roles as $rolId) {
                DB::table('menu_rol')->updateOrInsert(
                    ['menu_id' => $menuId, 'rol_id' => $rolId],
                    []
                );
            }
        }

        foreach (self::PERMISOS as $permiso) {
            $menuId = (int) (DB::table('menu')->where('url', $permiso['menu_url'])->value('id') ?? 0);
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
            foreach ($this->resolverRolIds($permiso['roles']) as $rolId) {
                DB::table('permiso_rol')->updateOrInsert(
                    ['permiso_id' => $permisoId, 'rol_id' => $rolId],
                    []
                );
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $padreId = $this->resolverMenuContableId();

        foreach ([self::MENU_APROBACION_URL, self::MENU_CONFIG_URL] as $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        if ($padreId > 0) {
            foreach ([self::MENU_CIERRE_URL, self::MENU_APERTURA_URL] as $i => $url) {
                $this->moverMenuBajoSubmenu($url, $padreId, 6 + $i);
            }
        }

        $submenuId = (int) (DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('nombre', self::SUBMENU_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);
        if ($submenuId > 0) {
            DB::table('menu_rol')->where('menu_id', $submenuId)->delete();
            DB::table('menu')->where('id', $submenuId)->delete();
        }

        foreach (self::PERMISOS as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertSubmenu(int $padreId): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('nombre', self::SUBMENU_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;

        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => self::SUBMENU_NOMBRE,
                'url' => '#',
                'orden' => $orden,
                'icono' => 'fa-tasks',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padreId,
            'nombre' => self::SUBMENU_NOMBRE,
            'orden' => $orden,
            'icono' => 'fa-tasks',
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function moverMenuBajoSubmenu(string $url, int $padreId, int $orden): void
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'menu_id' => $padreId,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
        }
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'menu_id' => $menuId > 0 ? $menuId : null,
            'updated_at' => now(),
        ];

        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'slug' => $slug,
            'created_at' => now(),
        ]));
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

    /** @param list<string> $nombres @return list<int> */
    private function resolverRolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
};
