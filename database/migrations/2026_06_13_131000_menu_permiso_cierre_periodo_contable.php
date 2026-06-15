<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_PADRE_NOMBRE = 'Módulo Contable';

    private const MENU_CIERRE_URL = 'contable/cierre-periodo';

    private const MENU_APERTURA_URL = 'contable/apertura-periodo';

    /** Roles con gestión completa de cierres y aprobación de aperturas. */
    private const ROLES_GESTION = [
        'administrador',
        'Enc-contaduría',
    ];

    /** Roles con acceso a listados y solicitud de aperturas. */
    private const ROLES_CONSULTA_SOLICITUD = [
        'Enc-impuestos',
        'Enc-admin',
        'Ger-administracion',
    ];

    /** @var list<array{nombre: string, slug: string, menu_url: string, roles: list<string>}> */
    private const PERMISOS = [
        [
            'nombre' => 'Listar cierres de período contable',
            'slug' => 'listar-cierre-periodo-contable',
            'menu_url' => self::MENU_CIERRE_URL,
            'roles' => ['administrador', 'Enc-contaduría', 'Enc-impuestos', 'Enc-admin', 'Ger-administracion'],
        ],
        [
            'nombre' => 'Ejecutar cierre de período contable',
            'slug' => 'ejecutar-cierre-periodo-contable',
            'menu_url' => self::MENU_CIERRE_URL,
            'roles' => self::ROLES_GESTION,
        ],
        [
            'nombre' => 'Operar en período contable cerrado (sin apertura)',
            'slug' => 'operar-periodo-cerrado-contable',
            'menu_url' => self::MENU_CIERRE_URL,
            'roles' => self::ROLES_GESTION,
        ],
        [
            'nombre' => 'Listar aperturas de período contable',
            'slug' => 'listar-apertura-periodo-contable',
            'menu_url' => self::MENU_APERTURA_URL,
            'roles' => ['administrador', 'Enc-contaduría', 'Enc-impuestos', 'Enc-admin', 'Ger-administracion'],
        ],
        [
            'nombre' => 'Solicitar apertura de período contable',
            'slug' => 'solicitar-apertura-periodo-contable',
            'menu_url' => self::MENU_APERTURA_URL,
            'roles' => ['administrador', 'Enc-contaduría', 'Enc-impuestos', 'Enc-admin', 'Ger-administracion'],
        ],
        [
            'nombre' => 'Aprobar apertura de período contable',
            'slug' => 'aprobar-apertura-periodo-contable',
            'menu_url' => self::MENU_APERTURA_URL,
            'roles' => self::ROLES_GESTION,
        ],
        [
            'nombre' => 'Revocar apertura de período contable',
            'slug' => 'revocar-apertura-periodo-contable',
            'menu_url' => self::MENU_APERTURA_URL,
            'roles' => self::ROLES_GESTION,
        ],
    ];

    public function up(): void
    {
        $padreId = $this->resolverMenuContableId();
        if ($padreId === 0) {
            return;
        }

        $ordenBase = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0);

        $menuCierreId = $this->upsertMenu(
            self::MENU_CIERRE_URL,
            'Cierre de período',
            $padreId,
            $ordenBase + 1,
            'fa-lock'
        );
        $menuAperturaId = $this->upsertMenu(
            self::MENU_APERTURA_URL,
            'Aperturas programadas',
            $padreId,
            $ordenBase + 2,
            'fa-unlock-alt'
        );

        $menuIds = [$menuCierreId, $menuAperturaId, $padreId];
        $todosRolesMenu = $this->resolverRolIds([
            'administrador',
            'Enc-contaduría',
            'Enc-impuestos',
            'Enc-admin',
            'Ger-administracion',
        ]);

        foreach ($menuIds as $menuId) {
            foreach ($todosRolesMenu as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                }
            }
        }

        foreach (self::PERMISOS as $permiso) {
            $menuId = (int) (DB::table('menu')->where('url', $permiso['menu_url'])->value('id') ?? 0);
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuId);
            foreach ($this->resolverRolIds($permiso['roles']) as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
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

    public function down(): void
    {
        foreach ([self::MENU_CIERRE_URL, self::MENU_APERTURA_URL] as $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
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
};
