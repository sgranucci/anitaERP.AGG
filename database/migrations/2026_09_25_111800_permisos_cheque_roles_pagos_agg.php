<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: Enc-pagos / Op-Pagos ven el menú Cheques pero no pueden entrar —
 * faltan los slugs que ChequeController/ChequeraController exigen
 * (listar-cheque, crear-cheque, … / listar-chequera, …).
 * En AGG solo existían legacies de chequera (lista-chequera, …) y ND cheque.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ROLES_PAGOS = [
        'Enc-pagos',
        'Op-Pagos',
    ];

    /**
     * @var array<string, list<array{nombre: string, slug: string, legacy?: string}>>
     */
    private const PERMISOS_POR_MENU = [
        'caja/cheque' => [
            ['nombre' => 'Listar cheque', 'slug' => 'listar-cheque'],
            ['nombre' => 'Ingresar cheque', 'slug' => 'crear-cheque'],
            ['nombre' => 'Editar cheque', 'slug' => 'editar-cheque'],
            ['nombre' => 'Actualizar cheque', 'slug' => 'actualizar-cheque'],
            ['nombre' => 'Borrar cheque', 'slug' => 'borrar-cheque'],
        ],
        'caja/chequera' => [
            ['nombre' => 'Listar chequera', 'slug' => 'listar-chequera', 'legacy' => 'lista-chequera'],
            ['nombre' => 'Ingresar chequera', 'slug' => 'crear-chequera', 'legacy' => 'crea-chequera'],
            ['nombre' => 'Editar chequera', 'slug' => 'editar-chequera', 'legacy' => 'edita-chequera'],
            ['nombre' => 'Actualizar chequera', 'slug' => 'actualizar-chequera', 'legacy' => 'actualiza-chequera'],
            ['nombre' => 'Borrar chequera', 'slug' => 'borrar-chequera', 'legacy' => 'borra-chequera'],
        ],
    ];

    /** @var list<string> */
    private array $slugsCreados = [];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolIds = $this->resolverRolIds();
        if ($rolIds === []) {
            return;
        }

        foreach (self::PERMISOS_POR_MENU as $menuUrl => $permisos) {
            $menuId = (int) (DB::table('menu')->where('url', $menuUrl)->value('id') ?? 0);
            if ($menuId <= 0) {
                continue;
            }

            foreach ($rolIds as $rolId) {
                $this->asegurarMenuRol($menuId, $rolId);
            }

            foreach ($permisos as $perm) {
                $permisoId = $this->upsertPermiso(
                    $perm['slug'],
                    $perm['nombre'],
                    $menuId,
                    $perm['legacy'] ?? null
                );
                foreach ($rolIds as $rolId) {
                    $this->asegurarPermisoRol($permisoId, $rolId);
                }
            }
        }

        $this->invalidarCacheRoles($rolIds);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolIds = $this->resolverRolIds();
        $slugs = [];
        foreach (self::PERMISOS_POR_MENU as $permisos) {
            foreach ($permisos as $perm) {
                $slugs[] = $perm['slug'];
            }
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', $slugs)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        if ($permisoIds !== [] && $rolIds !== []) {
            DB::table('permiso_rol')
                ->whereIn('permiso_id', $permisoIds)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        // Solo borra slugs que esta migración habría creado (no existían en AGG).
        $slugsSoloAgg = [
            'listar-cheque', 'crear-cheque', 'editar-cheque', 'actualizar-cheque', 'borrar-cheque',
            'listar-chequera', 'crear-chequera', 'editar-chequera', 'actualizar-chequera', 'borrar-chequera',
        ];
        $borrarIds = DB::table('permiso')->whereIn('slug', $slugsSoloAgg)->pluck('id');
        if ($borrarIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $borrarIds)->delete();
            DB::table('permiso')->whereIn('id', $borrarIds)->delete();
        }

        $this->invalidarCacheRoles($rolIds);
    }

    private function upsertPermiso(string $slug, string $nombre, int $menuId, ?string $legacySlug): int
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->slugsCreados[] = $slug;
            $this->copiarRolesDesdeLegacy($legacySlug, $permisoId);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => $nombre,
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        return $permisoId;
    }

    private function copiarRolesDesdeLegacy(?string $legacySlug, int $permisoId): void
    {
        if ($legacySlug === null || $legacySlug === '') {
            return;
        }

        $legacyId = (int) (DB::table('permiso')->where('slug', $legacySlug)->value('id') ?? 0);
        if ($legacyId <= 0) {
            return;
        }

        foreach (DB::table('permiso_rol')->where('permiso_id', $legacyId)->pluck('rol_id') as $rolId) {
            $this->asegurarPermisoRol($permisoId, (int) $rolId);
        }
    }

    private function asegurarPermisoRol(int $permisoId, int $rolId): void
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

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
        }
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function invalidarCacheRoles(array $rolIds): void
    {
        SuitecrmPermiso::flushCachePermisos();
        foreach ($rolIds as $rolId) {
            PermisoCacheSupport::forgetRol($rolId);
        }
    }

    /**
     * Enc-pagos + Op-Pagos + administrador.
     *
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES_PAGOS as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($adminId > 0) {
            $ids[] = $adminId;
        }

        return array_values(array_unique($ids));
    }
};
