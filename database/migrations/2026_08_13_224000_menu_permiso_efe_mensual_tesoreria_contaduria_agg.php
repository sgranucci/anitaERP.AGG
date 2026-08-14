<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: EFE / posición financiera para tesorería y contaduría.
 *
 * - Contaduría: menú Contable → Estado de flujo (EFE)
 * - Tesorería: menú Caja → Posición financiera (misma URL contable/efe-mensual)
 */
return new class extends Migration
{
    private const MENU_URL = 'contable/efe-mensual';

    private const MENU_CAJA_NOMBRE = 'Posición financiera';

    private const MENU_PADRE_CONTABLE = 'Módulo Contable';

    private const MENU_PADRE_CAJA = 'Módulo de Caja';

    private const MENU_REPORTES_CONTABLES = 'Reportes Contables';

    private const PERMISO_NOMBRE = 'Listar posición financiera';

    private const PERMISO_SLUG = 'listar-efe-mensual';

    /** @var list<string> */
    private const ROLES_TESORERIA = [
        'Enc-tesorería',
        'Enc-tesoreria',
        'Ger-Tesoreria',
    ];

    /** @var list<string> */
    private const ROLES_CONTADURIA = [
        'Enc-contaduría',
        'Op-contaduria',
        'Sup-contaduria',
        'Sup-contaduría',
    ];

    private const ROL_SUP_CONTADURIA = 'Sup-contaduria';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $menuContableId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuContableId <= 0) {
            return;
        }

        $this->asegurarRolSupContaduria();

        $reportesContablesId = $this->resolverMenuPorNombre(self::MENU_REPORTES_CONTABLES, '#');
        $moduloContableId = $this->resolverMenuPorNombre(self::MENU_PADRE_CONTABLE, '#');
        $moduloCajaId = $this->resolverMenuPorNombre(self::MENU_PADRE_CAJA, '#');
        $menuCajaId = $this->upsertMenuCajaPosicionFinanciera($moduloCajaId);

        // Permiso cuelga del menú Caja «Posición financiera» (no del EFE de Contable).
        $permisoMenuId = $menuCajaId > 0 ? $menuCajaId : $menuContableId;
        $permisoId = $this->upsertPermiso($permisoMenuId);

        foreach ($this->resolverRolIds(self::ROLES_CONTADURIA) as $rolId) {
            $this->vincularMenuRol($menuContableId, $rolId);
            if ($reportesContablesId > 0) {
                $this->vincularMenuRol($reportesContablesId, $rolId);
            }
            if ($moduloContableId > 0) {
                $this->vincularMenuRol($moduloContableId, $rolId);
            }
            $this->vincularPermisoRol($permisoId, $rolId);
        }

        foreach ($this->resolverRolIds(self::ROLES_TESORERIA) as $rolId) {
            if ($menuCajaId > 0) {
                $this->vincularMenuRol($menuCajaId, $rolId);
            }
            if ($moduloCajaId > 0) {
                $this->vincularMenuRol($moduloCajaId, $rolId);
            }
            // Acceso también desde Contable por si ya navegan ahí.
            $this->vincularMenuRol($menuContableId, $rolId);
            if ($reportesContablesId > 0) {
                $this->vincularMenuRol($reportesContablesId, $rolId);
            }
            if ($moduloContableId > 0) {
                $this->vincularMenuRol($moduloContableId, $rolId);
            }
            $this->vincularPermisoRol($permisoId, $rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $menuContableId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $menuCajaId = (int) (DB::table('menu')
            ->where('url', self::MENU_URL)
            ->where('nombre', self::MENU_CAJA_NOMBRE)
            ->value('id') ?? 0);
        // Si el upsert creó un segundo registro con misma URL, localizar por nombre bajo Caja.
        if ($menuCajaId <= 0 || $menuCajaId === $menuContableId) {
            $cajaPadre = $this->resolverMenuPorNombre(self::MENU_PADRE_CAJA, '#');
            $menuCajaId = $cajaPadre > 0
                ? (int) (DB::table('menu')
                    ->where('menu_id', $cajaPadre)
                    ->where('nombre', self::MENU_CAJA_NOMBRE)
                    ->value('id') ?? 0)
                : 0;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        $rolIds = array_values(array_unique(array_merge(
            $this->resolverRolIds(self::ROLES_TESORERIA),
            $this->resolverRolIds(self::ROLES_CONTADURIA),
        )));

        foreach ($rolIds as $rolId) {
            if ($menuCajaId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuCajaId)->where('rol_id', $rolId)->delete();
            }
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->delete();
            }
        }

        // Tesorería: quitar vínculo al EFE de Contable solo a roles de tesorería
        // (contaduría ya lo tenía antes de esta migración).
        foreach ($this->resolverRolIds(self::ROLES_TESORERIA) as $rolId) {
            if ($menuContableId > 0) {
                DB::table('menu_rol')->where('menu_id', $menuContableId)->where('rol_id', $rolId)->delete();
            }
        }

        if ($menuCajaId > 0 && $menuCajaId !== $menuContableId) {
            DB::table('menu_rol')->where('menu_id', $menuCajaId)->delete();
            DB::table('menu')->where('id', $menuCajaId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarRolSupContaduria(): void
    {
        if (DB::table('rol')->where('nombre', self::ROL_SUP_CONTADURIA)->exists()
            || DB::table('rol')->where('nombre', 'Sup-contaduría')->exists()) {
            return;
        }

        DB::table('rol')->insert([
            'nombre' => self::ROL_SUP_CONTADURIA,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertMenuCajaPosicionFinanciera(int $moduloCajaId): int
    {
        if ($moduloCajaId <= 0) {
            return 0;
        }

        $existente = (int) (DB::table('menu')
            ->where('menu_id', $moduloCajaId)
            ->where('nombre', self::MENU_CAJA_NOMBRE)
            ->value('id') ?? 0);

        $orden = (int) (DB::table('menu')->where('menu_id', $moduloCajaId)->max('orden') ?? 0) + 1;

        if ($existente > 0) {
            DB::table('menu')->where('id', $existente)->update([
                'url' => self::MENU_URL,
                'icono' => 'fa-balance-scale',
                'updated_at' => now(),
            ]);

            return $existente;
        }

        return (int) DB::table('menu')->insertGetId([
            'menu_id' => $moduloCajaId,
            'nombre' => self::MENU_CAJA_NOMBRE,
            'url' => self::MENU_URL,
            'orden' => $orden,
            'icono' => 'fa-balance-scale',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resolverMenuPorNombre(string $nombre, string $url): int
    {
        return (int) (DB::table('menu')
            ->where('nombre', $nombre)
            ->where('url', $url)
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function upsertPermiso(int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update([
                'nombre' => self::PERMISO_NOMBRE,
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => self::PERMISO_NOMBRE,
            'slug' => self::PERMISO_SLUG,
            'menu_id' => $menuId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function resolverRolIds(array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $nombre) {
            $rolId = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($rolId > 0) {
                $ids[$rolId] = $rolId;
            }
        }

        return array_values($ids);
    }

    private function vincularMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
        }
    }

    private function vincularPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
    }
};
