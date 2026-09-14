<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Calzados Ferli: renovación de certificados ARCA + generador de COT electrónico ARBA.
 *
 * Certificados (canónica): Configuración → Configuración por módulo → Ventas
 * Atajo en Módulo de Ventas: solo administrador
 * COT: proceso operativo bajo Módulo de Ventas
 */
return new class extends Migration
{
    private const CERT_URL = 'ventas/certificados-arca';

    private const CERT_NOMBRE = 'Certificados ARCA';

    private const COT_URL = 'ventas/cot-electronico';

    private const COT_NOMBRE = 'COT electrónico ARBA';

    private const COT_PERMISO_SLUG = 'procesar-cot-electronico';

    private const COT_PERMISO_NOMBRE = 'Procesar COT electrónico ARBA';

    private const GRUPO_URL = '#configuracion-por-modulo';

    private const MODULO_URL = '#config-modulo-ventas';

    /** @var list<array{nombre: string, slug: string}> */
    private const CERT_PERMISOS = [
        ['nombre' => 'Listar certificados ARCA', 'slug' => 'listar-certificados-arca'],
        ['nombre' => 'Generar CSR certificados ARCA', 'slug' => 'generar-csr-certificados-arca'],
        ['nombre' => 'Instalar certificados ARCA', 'slug' => 'instalar-certificados-arca'],
    ];

    /** @var list<string> */
    private const ROLES_CERT = [
        'administrador',
        'Enc-admin',
        'Enc-contaduría',
    ];

    /** @var list<string> */
    private const ROLES_COT = [
        'administrador',
        'Enc-contaduría',
        'Enc-admin',
        'Admin-ventas',
        'Ventas',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $this->asegurarCertificadosArca();
        $this->asegurarCotElectronico();

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $this->quitarCotElectronico();
        $this->quitarRolesExtraCertificados();

        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarCertificadosArca(): void
    {
        $configRootId = $this->resolverMenuConfiguracionId();
        $grupoId = (int) (DB::table('menu')->where('url', self::GRUPO_URL)->value('id') ?? 0);
        $moduloVentasId = (int) (DB::table('menu')->where('url', self::MODULO_URL)->value('id') ?? 0);
        if ($configRootId === 0 || $grupoId === 0 || $moduloVentasId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $moduloVentasId)->max('orden') ?? 0) + 1;
        $menuCanonicoId = $this->upsertMenu(
            self::CERT_URL,
            self::CERT_NOMBRE,
            $moduloVentasId,
            $orden,
            'fa-certificate'
        );

        $rolIds = $this->resolverRolIds(self::ROLES_CERT);
        $this->asignarRolesMenu($menuCanonicoId, $rolIds);
        $this->asignarRolesMenu($moduloVentasId, $rolIds);
        $this->asignarRolesMenu($grupoId, $rolIds);
        $this->asignarRolesMenu($configRootId, $rolIds);

        foreach (self::CERT_PERMISOS as $permiso) {
            $permisoId = $this->upsertPermiso($permiso['nombre'], $permiso['slug'], $menuCanonicoId);
            $this->asignarPermisoRoles($permisoId, $rolIds);
        }

        $ventasRootId = $this->resolverModuloVentasId();
        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($ventasRootId > 0 && $adminId > 0) {
            $atajoId = (int) (DB::table('menu')
                ->where('menu_id', $ventasRootId)
                ->where('url', self::CERT_URL)
                ->where('id', '!=', $menuCanonicoId)
                ->value('id') ?? 0);

            if ($atajoId === 0) {
                $ordenAtajo = (int) (DB::table('menu')->where('menu_id', $ventasRootId)->max('orden') ?? 0) + 1;
                $atajoId = (int) DB::table('menu')->insertGetId([
                    'menu_id' => $ventasRootId,
                    'nombre' => self::CERT_NOMBRE,
                    'url' => self::CERT_URL,
                    'orden' => $ordenAtajo,
                    'icono' => 'fa-certificate',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->asignarRolesMenu($atajoId, [$adminId]);
        }
    }

    private function asegurarCotElectronico(): void
    {
        $padreId = $this->resolverModuloVentasId();
        if ($padreId === 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu(
            self::COT_URL,
            self::COT_NOMBRE,
            $padreId,
            $orden,
            'fa-truck'
        );

        $permisoId = $this->upsertPermiso(self::COT_PERMISO_NOMBRE, self::COT_PERMISO_SLUG, $menuId);
        $rolIds = $this->resolverRolIds(self::ROLES_COT);
        $this->asignarRolesMenu($menuId, $rolIds);
        $this->asignarRolesMenu($padreId, $rolIds);
        $this->asignarPermisoRoles($permisoId, $rolIds);
    }

    private function quitarCotElectronico(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::COT_PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $menuIds = DB::table('menu')->where('url', self::COT_URL)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menu')->whereIn('id', $menuIds)->delete();
        }
    }

    private function quitarRolesExtraCertificados(): void
    {
        $extra = $this->resolverRolIds(['Enc-admin', 'Enc-contaduría']);
        if ($extra === []) {
            return;
        }

        $menuIds = DB::table('menu')->where('url', self::CERT_URL)->pluck('id');
        foreach ($menuIds as $menuId) {
            DB::table('menu_rol')
                ->where('menu_id', (int) $menuId)
                ->whereIn('rol_id', $extra)
                ->delete();
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', array_column(self::CERT_PERMISOS, 'slug'))
            ->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')
                ->whereIn('permiso_id', $permisoIds)
                ->whereIn('rol_id', $extra)
                ->delete();
        }
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

    private function resolverModuloVentasId(): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'Módulo de Ventas')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $id = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'Módulo Ventas')
            ->orderBy('id')
            ->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', 'ventas/factura')->value('menu_id') ?? 0);
    }

    private function upsertMenu(string $url, string $nombre, int $padre, int $orden, string $icono): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->where('menu_id', $padre)->value('id') ?? 0);
        if ($id === 0) {
            $id = (int) (DB::table('menu')->where('url', $url)->orderBy('id')->value('id') ?? 0);
        }

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
