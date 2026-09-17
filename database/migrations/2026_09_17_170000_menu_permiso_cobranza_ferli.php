<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: menú y permisos del módulo de Cobranzas (faltaban en BD).
 *
 * En AGG venían del dump; en Ferli existían tablas y rutas pero no menú/permiso,
 * por eso no aparecía en Módulo de Caja.
 *
 * Solo Ferli: no toca AGG ni otros clientes.
 */
return new class extends Migration
{
    private const MENU_COBRANZA_URL = 'caja/cobranza';

    private const MENU_COBRANZA_NOMBRE = 'Cobranzas';

    private const MENU_RETENCION_URL = 'configuracion/retencion_cobranza';

    private const MENU_RETENCION_NOMBRE = 'Retenciones de cobranzas';

    private const MODULO_CAJA_NOMBRE = 'Módulo de Caja';

    private const CONFIG_CAJA_URL = '#config-modulo-caja';

    /** Roles operativos (mismo criterio que Ingresos y Egresos). */
    private const ROLES_OPERATIVOS = [
        'administrador',
        'Enc-admin',
        'Enc-contaduría',
        'Oficina',
    ];

    /** Roles para la config de retenciones (rama Configuración por módulo). */
    private const ROLES_CONFIG = [
        'administrador',
        'Enc-admin',
        'Enc-contaduría',
    ];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS_COBRANZA = [
        ['nombre' => 'Listar cobranzas', 'slug' => 'listar-cobranza'],
        ['nombre' => 'Crear cobranzas', 'slug' => 'crear-cobranza'],
        ['nombre' => 'Editar cobranzas', 'slug' => 'editar-cobranza'],
        ['nombre' => 'Actualizar cobranzas', 'slug' => 'actualizar-cobranza'],
        ['nombre' => 'Borrar cobranzas', 'slug' => 'borrar-cobranza'],
        ['nombre' => 'Emitir cobranzas', 'slug' => 'emitir-cobranza'],
        ['nombre' => 'Confirmar cobranzas', 'slug' => 'confirmar-cobranza'],
        ['nombre' => 'Revertir cobranzas', 'slug' => 'revertir-cobranza'],
    ];

    /** @var list<array{nombre: string, slug: string}> */
    private const PERMISOS_RETENCION = [
        ['nombre' => 'Listar retención cobranzas', 'slug' => 'listar-retencion-cobranza'],
        ['nombre' => 'Crear retención cobranzas', 'slug' => 'crear-retencion-cobranza'],
        ['nombre' => 'Editar retención cobranzas', 'slug' => 'editar-retencion-cobranza'],
        ['nombre' => 'Actualizar retención cobranzas', 'slug' => 'actualizar-retencion-cobranza'],
        ['nombre' => 'Borrar retención cobranzas', 'slug' => 'borrar-retencion-cobranza'],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $moduloCajaId = (int) (DB::table('menu')
            ->where('nombre', self::MODULO_CAJA_NOMBRE)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        if ($moduloCajaId <= 0) {
            return;
        }

        $ordenIe = (int) (DB::table('menu')->where('url', 'caja/ingresoegreso')->value('orden') ?? 4);
        $menuCobranzaId = $this->upsertMenu(
            self::MENU_COBRANZA_URL,
            self::MENU_COBRANZA_NOMBRE,
            $moduloCajaId,
            $ordenIe + 1,
            'fa-hand-holding-usd'
        );

        $rolOperativos = $this->resolverRolIds(self::ROLES_OPERATIVOS);
        $this->asignarRolesMenu($menuCobranzaId, $rolOperativos);
        $this->asignarRolesMenu($moduloCajaId, $rolOperativos);

        foreach (self::PERMISOS_COBRANZA as $perm) {
            $permisoId = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuCobranzaId);
            $this->asignarPermisoRoles($permisoId, $rolOperativos);
        }

        $configCajaId = (int) (DB::table('menu')->where('url', self::CONFIG_CAJA_URL)->value('id') ?? 0);
        if ($configCajaId > 0) {
            $ordenConfig = (int) (DB::table('menu')->where('menu_id', $configCajaId)->max('orden') ?? 0) + 1;
            $menuRetencionId = $this->upsertMenu(
                self::MENU_RETENCION_URL,
                self::MENU_RETENCION_NOMBRE,
                $configCajaId,
                $ordenConfig,
                'fa-percent'
            );

            $rolConfig = $this->resolverRolIds(self::ROLES_CONFIG);
            $this->asignarRolesMenu($menuRetencionId, $rolConfig);
            $this->asignarRolesAncestros($menuRetencionId, $rolConfig);

            foreach (self::PERMISOS_RETENCION as $perm) {
                $permisoId = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuRetencionId);
                $this->asignarPermisoRoles($permisoId, $rolConfig);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $slugs = array_merge(
            array_column(self::PERMISOS_COBRANZA, 'slug'),
            array_column(self::PERMISOS_RETENCION, 'slug')
        );
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        foreach ([self::MENU_COBRANZA_URL, self::MENU_RETENCION_URL] as $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuId)->delete();
                DB::table('menu')->where('id', $menuId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        if ($id === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $padre,
                'nombre' => mb_substr($nombre, 0, 50),
                'url' => $url,
                'orden' => $orden,
                'icono' => $icono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $id)->update([
            'menu_id' => $padre,
            'nombre' => mb_substr($nombre, 0, 50),
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = [
            'nombre' => mb_substr($nombre, 0, 50),
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

    /**
     * Propaga menu_rol a padres de la rama Configuración para que el árbol sea visible.
     *
     * @param  list<int>  $rolIds
     */
    private function asignarRolesAncestros(int $menuId, array $rolIds): void
    {
        $actual = $menuId;
        for ($i = 0; $i < 8; $i++) {
            $padre = (int) (DB::table('menu')->where('id', $actual)->value('menu_id') ?? 0);
            if ($padre <= 0) {
                break;
            }
            $this->asignarRolesMenu($padre, $rolIds);
            $actual = $padre;
        }
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarPermisoRoles(int $permisoId, array $rolIds): void
    {
        if ($permisoId <= 0) {
            return;
        }
        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
