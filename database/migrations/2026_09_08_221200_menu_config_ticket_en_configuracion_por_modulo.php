<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Configuración de tickets:
 * - Canónica: Configuración → Configuración por módulo → Tickets
 * - Acceso operativo en Módulo de Tickets: solo rol administrador
 *   (convención ERP: configs viven en Configuración; el módulo operativo
 *   puede dejar un acceso solo-admin).
 */
return new class extends Migration
{
    private const MENU_URL = 'ticket/configuracion';

    private const MENU_NOMBRE = 'Configuración de tickets';

    private const GRUPO_URL = '#configuracion-por-modulo';

    private const MODULO_URL = '#config-modulo-tickets';

    private const MODULO_NOMBRE = 'Tickets';

    private const TICKETS_ROOT_URL_FALLBACK = 'ticket/administracion_ticket';

    /** @var list<string> */
    private const ROLES_CONFIG = [
        'administrador',
        'Enc-admin',
        'Enc-sistemas',
        'Tecnico de Tecnología',
        'op-Gerencia de Tecnologia',
        'Ger-administracion',
    ];

    public function up(): void
    {
        $configRootId = $this->resolverMenuConfiguracionId();
        $grupoId = (int) (DB::table('menu')->where('url', self::GRUPO_URL)->value('id') ?? 0);
        if ($configRootId === 0 || $grupoId === 0) {
            return;
        }

        $moduloTicketsId = $this->asegurarNodo(
            $grupoId,
            self::MODULO_NOMBRE,
            self::MODULO_URL,
            $this->siguienteOrden($grupoId),
            'fa-wrench'
        );

        $menuCanonicoId = (int) (DB::table('menu')
            ->where('url', self::MENU_URL)
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($menuCanonicoId === 0) {
            $menuCanonicoId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $moduloTicketsId,
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'orden' => 1,
                'icono' => 'fa-cog',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuCanonicoId)->update([
                'menu_id' => $moduloTicketsId,
                'nombre' => self::MENU_NOMBRE,
                'orden' => 1,
                'icono' => 'fa-cog',
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')
            ->whereIn('slug', [
                'listar-configuracion-ticket',
                'actualizar-configuracion-ticket',
            ])
            ->update([
                'menu_id' => $menuCanonicoId,
                'updated_at' => now(),
            ]);

        $rolIdsConfig = $this->resolverRolIds(self::ROLES_CONFIG);
        $this->reemplazarMenuRoles($menuCanonicoId, $rolIdsConfig);
        $this->asignarRolesMenu($moduloTicketsId, $rolIdsConfig);
        $this->asignarRolesMenu($grupoId, $rolIdsConfig);
        $this->asignarRolesMenu($configRootId, $rolIdsConfig);

        foreach ([
            'listar-configuracion-ticket',
            'actualizar-configuracion-ticket',
        ] as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            foreach ($rolIdsConfig as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        // Acceso espejo en Módulo de Tickets: solo administrador.
        $ticketsModuloId = $this->resolverModuloTicketsId();
        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($ticketsModuloId > 0 && $adminId > 0) {
            $atajoId = (int) (DB::table('menu')
                ->where('menu_id', $ticketsModuloId)
                ->where('url', self::MENU_URL)
                ->where('id', '!=', $menuCanonicoId)
                ->value('id') ?? 0);

            if ($atajoId === 0) {
                $orden = (int) (DB::table('menu')->where('menu_id', $ticketsModuloId)->max('orden') ?? 0) + 1;
                $atajoId = (int) DB::table('menu')->insertGetId([
                    'menu_id' => $ticketsModuloId,
                    'nombre' => self::MENU_NOMBRE,
                    'url' => self::MENU_URL,
                    'orden' => $orden,
                    'icono' => 'fa-cog',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('menu')->where('id', $atajoId)->update([
                    'nombre' => self::MENU_NOMBRE,
                    'icono' => 'fa-cog',
                    'updated_at' => now(),
                ]);
            }

            $this->reemplazarMenuRoles($atajoId, [$adminId]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $ticketsModuloId = $this->resolverModuloTicketsId();
        $menuCanonicoId = (int) (DB::table('menu')
            ->where('url', self::MENU_URL)
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($ticketsModuloId > 0) {
            $atajos = DB::table('menu')
                ->where('menu_id', $ticketsModuloId)
                ->where('url', self::MENU_URL)
                ->when($menuCanonicoId > 0, fn ($q) => $q->where('id', '!=', $menuCanonicoId))
                ->pluck('id');
            foreach ($atajos as $atajoId) {
                DB::table('menu_rol')->where('menu_id', $atajoId)->delete();
                DB::table('menu')->where('id', $atajoId)->delete();
            }
        }

        $moduloTicketsId = (int) (DB::table('menu')->where('url', self::MODULO_URL)->value('id') ?? 0);
        if ($menuCanonicoId > 0 && $ticketsModuloId > 0) {
            DB::table('menu')->where('id', $menuCanonicoId)->update([
                'menu_id' => $ticketsModuloId,
                'updated_at' => now(),
            ]);
        }

        if ($moduloTicketsId > 0) {
            $hijos = (int) DB::table('menu')->where('menu_id', $moduloTicketsId)->count();
            if ($hijos === 0) {
                DB::table('menu_rol')->where('menu_id', $moduloTicketsId)->delete();
                DB::table('menu')->where('id', $moduloTicketsId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuConfiguracionId(): int
    {
        $id = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'Configuración')
            ->value('id') ?? 0);
    }

    private function resolverModuloTicketsId(): int
    {
        $padreAdm = (int) (DB::table('menu')->where('url', self::TICKETS_ROOT_URL_FALLBACK)->value('menu_id') ?? 0);
        if ($padreAdm > 0) {
            return $padreAdm;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Tickets')
                    ->orWhere('nombre', 'like', '%Módulo de Tickets%');
            })
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function asegurarNodo(int $padreId, string $nombre, string $url, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', $padreId)
            ->where(function ($q) use ($nombre, $url) {
                $q->where('url', $url)->orWhere('nombre', $nombre);
            })
            ->value('id') ?? 0);

        if ($id === 0) {
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

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padreId,
            'nombre' => $nombre,
            'url' => $url,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function siguienteOrden(int $padreId): int
    {
        return (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
    }

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
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

    /**
     * @param  list<int>  $rolIds
     */
    private function reemplazarMenuRoles(int $menuId, array $rolIds): void
    {
        DB::table('menu_rol')->where('menu_id', $menuId)->delete();
        $this->asignarRolesMenu($menuId, $rolIds);
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarRolesMenu(int $menuId, array $rolIds): void
    {
        if ($menuId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
