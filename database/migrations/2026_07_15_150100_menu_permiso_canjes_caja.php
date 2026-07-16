<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú Canjes bajo Módulo de Caja: generación de tickets + reubica Clientes VIP.
 * Permisos a administrador y roles de tesorería.
 */
return new class extends Migration
{
    private const MENU_PADRE_URL = '#';

    private const MENU_PADRE_NOMBRE = 'Canjes';

    private const MENU_GENERACION_URL = 'caja/canjes/generacion';

    private const MENU_VIP_URL_NUEVA = 'caja/canjes/cliente-vip';

    private const MENU_VIP_URL_VIEJA = 'caja/cliente-vip';

    /** @var list<string> */
    private const PERMISO_GENERACION_SLUGS = [
        'listar-ticket-canje-caja',
        'crear-ticket-canje-caja',
        'reimprimir-ticket-canje-caja',
    ];

    /** @var list<string> */
    private const PERMISO_VIP_SLUGS = [
        'listar-cliente-vip-caja',
        'crear-cliente-vip-caja',
        'editar-cliente-vip-caja',
        'actualizar-cliente-vip-caja',
        'borrar-cliente-vip-caja',
    ];

    /** @var list<array{nombre: string, like?: string}> */
    private const ROLES = [
        ['nombre' => 'administrador'],
        ['nombre' => 'Enc-tesorería', 'like' => 'Enc-tesorer%'],
        ['nombre' => 'enc-Tesoreria Operativa', 'like' => 'enc-Tesoreria Operativa%'],
        ['nombre' => 'Ger-Tesoreria', 'like' => 'Ger-Tesorer%'],
        ['nombre' => 'Op-tesoreria', 'like' => 'Op-tesorer%'],
        ['nombre' => 'op-Tesoreria Operativa', 'like' => 'op-Tesoreria Operativa%'],
        ['nombre' => 'Sup-tesoreria', 'like' => 'Sup-tesorer%'],
    ];

    public function up(): void
    {
        $moduloCajaId = $this->resolverModuloCajaId();
        if ($moduloCajaId === 0) {
            return;
        }

        $ordenPadre = (int) (DB::table('menu')->where('menu_id', $moduloCajaId)->max('orden') ?? 0) + 1;
        $menuCanjesId = (int) (DB::table('menu')
            ->where('menu_id', $moduloCajaId)
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', self::MENU_PADRE_URL)
            ->value('id') ?? 0);

        if ($menuCanjesId === 0) {
            $menuCanjesId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $moduloCajaId,
                'nombre' => self::MENU_PADRE_NOMBRE,
                'url' => self::MENU_PADRE_URL,
                'orden' => $ordenPadre,
                'icono' => 'fa-ticket-alt',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuCanjesId)->update([
                'orden' => $ordenPadre,
                'icono' => 'fa-ticket-alt',
                'updated_at' => now(),
            ]);
        }

        $ordenHijo = 1;
        $menuGeneracionId = $this->upsertMenuHijo(
            $menuCanjesId,
            self::MENU_GENERACION_URL,
            'Generación de tickets',
            $ordenHijo++,
            'fa-print'
        );

        $menuVipId = (int) (DB::table('menu')->where('url', self::MENU_VIP_URL_VIEJA)->value('id') ?? 0);
        if ($menuVipId === 0) {
            $menuVipId = (int) (DB::table('menu')->where('url', self::MENU_VIP_URL_NUEVA)->value('id') ?? 0);
        }

        if ($menuVipId === 0) {
            $menuVipId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $menuCanjesId,
                'nombre' => 'Clientes VIP',
                'url' => self::MENU_VIP_URL_NUEVA,
                'orden' => $ordenHijo,
                'icono' => 'fa-star',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuVipId)->update([
                'menu_id' => $menuCanjesId,
                'nombre' => 'Clientes VIP',
                'url' => self::MENU_VIP_URL_NUEVA,
                'orden' => $ordenHijo,
                'icono' => 'fa-star',
                'updated_at' => now(),
            ]);
        }

        $permisoGeneracionDefs = [
            ['nombre' => 'Listar tickets canje caja', 'slug' => 'listar-ticket-canje-caja'],
            ['nombre' => 'Emitir tickets canje caja', 'slug' => 'crear-ticket-canje-caja'],
            ['nombre' => 'Reimprimir tickets canje caja', 'slug' => 'reimprimir-ticket-canje-caja'],
        ];

        $permisoIds = [];
        foreach ($permisoGeneracionDefs as $row) {
            $permisoIds[] = $this->upsertPermiso($row['nombre'], $row['slug'], $menuGeneracionId);
        }

        foreach (self::PERMISO_VIP_SLUGS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuVipId,
                    'updated_at' => now(),
                ]);
                $permisoIds[] = $permisoId;
            }
        }

        $menuIdsCadena = array_values(array_unique(array_filter([
            $moduloCajaId,
            $menuCanjesId,
            $menuGeneracionId,
            $menuVipId,
        ])));

        foreach ($this->resolverRolIds() as $rolId) {
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
            foreach ($menuIdsCadena as $mid) {
                if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $mid,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoIds = DB::table('permiso')->whereIn('slug', self::PERMISO_GENERACION_SLUGS)->pluck('id')->all();
        foreach ($permisoIds as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }

        $menuGeneracionId = (int) (DB::table('menu')->where('url', self::MENU_GENERACION_URL)->value('id') ?? 0);
        if ($menuGeneracionId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuGeneracionId)->delete();
            DB::table('menu')->where('id', $menuGeneracionId)->delete();
        }

        $menuVipId = (int) (DB::table('menu')->where('url', self::MENU_VIP_URL_NUEVA)->value('id') ?? 0);
        if ($menuVipId > 0) {
            $tablasCajaId = $this->resolverTablasCajaId($this->resolverModuloCajaId());
            DB::table('menu')->where('id', $menuVipId)->update([
                'menu_id' => $tablasCajaId > 0 ? $tablasCajaId : DB::raw('menu_id'),
                'url' => self::MENU_VIP_URL_VIEJA,
                'updated_at' => now(),
            ]);
        }

        $moduloCajaId = $this->resolverModuloCajaId();
        $menuCanjesId = (int) (DB::table('menu')
            ->where('menu_id', $moduloCajaId)
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', self::MENU_PADRE_URL)
            ->value('id') ?? 0);
        if ($menuCanjesId > 0 && ! DB::table('menu')->where('menu_id', $menuCanjesId)->exists()) {
            DB::table('menu_rol')->where('menu_id', $menuCanjesId)->delete();
            DB::table('menu')->where('id', $menuCanjesId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenuHijo(int $padreId, string $url, string $nombre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
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
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('id', $permisoId)->update([
            'menu_id' => $menuId,
            'nombre' => $nombre,
            'updated_at' => now(),
        ]);

        return $permisoId;
    }

    private function resolverModuloCajaId(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Caja')
                    ->orWhere('nombre', 'like', '%Módulo de Caja%');
            })
            ->orderBy('id')
            ->value('id') ?? 104);
    }

    private function resolverTablasCajaId(int $moduloCajaId): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', $moduloCajaId)
            ->where('nombre', 'Tablas de caja')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $def) {
            $id = $this->resolverRolId($def);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array{nombre: string, like?: string}  $def
     */
    private function resolverRolId(array $def): int
    {
        $nombre = (string) ($def['nombre'] ?? '');
        $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $like = trim((string) ($def['like'] ?? ''));
        if ($like !== '') {
            return (int) (DB::table('rol')->where('nombre', 'like', $like)->orderBy('id')->value('id') ?? 0);
        }

        return 0;
    }
};
