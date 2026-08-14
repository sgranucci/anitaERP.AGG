<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: dos permisos distintos
 * - Caja «Posición financiera» → listar-posicion-financiera (tesorería)
 * - Contable «Estado de flujo (EFE)» → listar-efe-mensual (contaduría)
 */
return new class extends Migration
{
    private const SLUG_POSICION = 'listar-posicion-financiera';

    private const SLUG_EFE = 'listar-efe-mensual';

    private const SLUG_LEGACY = 'listar-efe-mensual';

    private const MENU_CAJA_NOMBRE = 'Posición financiera';

    private const MENU_PADRE_CAJA = 'Módulo de Caja';

    private const MENU_EFE_URL = 'contable/efe-mensual';

    private const MENU_EFE_NOMBRE = 'Estado de flujo (EFE)';

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

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $menuCajaId = $this->resolverMenuCajaPosicionId();
        $menuEfeId = $this->resolverMenuEfeContableId();
        if ($menuCajaId <= 0 || $menuEfeId <= 0) {
            return;
        }

        // El permiso actual (slug listar-efe-mensual en menú Caja) pasa a listar-posicion-financiera.
        $permisoPosicionId = (int) (DB::table('permiso')->where('slug', self::SLUG_LEGACY)->value('id') ?? 0);
        if ($permisoPosicionId > 0) {
            DB::table('permiso')->where('id', $permisoPosicionId)->update([
                'nombre' => 'Listar posición financiera',
                'slug' => self::SLUG_POSICION,
                'menu_id' => $menuCajaId,
                'updated_at' => now(),
            ]);
        } else {
            $permisoPosicionId = (int) (DB::table('permiso')->where('slug', self::SLUG_POSICION)->value('id') ?? 0);
            if ($permisoPosicionId <= 0) {
                $permisoPosicionId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => 'Listar posición financiera',
                    'slug' => self::SLUG_POSICION,
                    'menu_id' => $menuCajaId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Permiso Contable EFE (nuevo o existente).
        $permisoEfeId = (int) (DB::table('permiso')->where('slug', self::SLUG_EFE)->value('id') ?? 0);
        if ($permisoEfeId <= 0) {
            $permisoEfeId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Listar EFE mensual',
                'slug' => self::SLUG_EFE,
                'menu_id' => $menuEfeId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoEfeId)->update([
                'nombre' => 'Listar EFE mensual',
                'menu_id' => $menuEfeId,
                'updated_at' => now(),
            ]);
        }

        $rolesTesoreria = $this->resolverRolIds(self::ROLES_TESORERIA);
        $rolesContaduria = $this->resolverRolIds(self::ROLES_CONTADURIA);

        // Contaduría: EFE sí, posición financiera no.
        foreach ($rolesContaduria as $rolId) {
            $this->desvincularPermisoRol($permisoPosicionId, $rolId);
            $this->vincularPermisoRol($permisoEfeId, $rolId);
        }

        // Tesorería: posición financiera sí, EFE Contable no (salvo que lo pidan después).
        foreach ($rolesTesoreria as $rolId) {
            $this->vincularPermisoRol($permisoPosicionId, $rolId);
            $this->desvincularPermisoRol($permisoEfeId, $rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $menuCajaId = $this->resolverMenuCajaPosicionId();
        $permisoPosicionId = (int) (DB::table('permiso')->where('slug', self::SLUG_POSICION)->value('id') ?? 0);
        $permisoEfeId = (int) (DB::table('permiso')->where('slug', self::SLUG_EFE)->value('id') ?? 0);

        // Volver a un solo permiso listar-efe-mensual en menú Caja (estado post-225000).
        if ($permisoPosicionId > 0 && $menuCajaId > 0) {
            DB::table('permiso')->where('id', $permisoPosicionId)->update([
                'nombre' => 'Listar posición financiera',
                'slug' => self::SLUG_LEGACY,
                'menu_id' => $menuCajaId,
                'updated_at' => now(),
            ]);
            $permisoUnificadoId = $permisoPosicionId;
        } else {
            $permisoUnificadoId = $permisoEfeId;
        }

        if ($permisoEfeId > 0 && $permisoEfeId !== $permisoUnificadoId) {
            DB::table('permiso_rol')->where('permiso_id', $permisoEfeId)->delete();
            DB::table('permiso')->where('id', $permisoEfeId)->delete();
        }

        if ($permisoUnificadoId > 0) {
            foreach (array_merge(
                $this->resolverRolIds(self::ROLES_TESORERIA),
                $this->resolverRolIds(self::ROLES_CONTADURIA),
            ) as $rolId) {
                $this->vincularPermisoRol($permisoUnificadoId, $rolId);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuCajaPosicionId(): int
    {
        $cajaPadreId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_CAJA)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($cajaPadreId <= 0) {
            return 0;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', $cajaPadreId)
            ->where('nombre', self::MENU_CAJA_NOMBRE)
            ->value('id') ?? 0);
    }

    private function resolverMenuEfeContableId(): int
    {
        return (int) (DB::table('menu')
            ->where('url', self::MENU_EFE_URL)
            ->where('nombre', self::MENU_EFE_NOMBRE)
            ->value('id') ?? 0);
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

    private function desvincularPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->delete();
    }
};
