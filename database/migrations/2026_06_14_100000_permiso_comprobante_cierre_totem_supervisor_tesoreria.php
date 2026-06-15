<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supervisor tesorería: ver comprobante de cierre tótem / Informe Z desde rendiciones gastronomía caja.
 */
return new class extends Migration
{
    private const MENU_RENDICION_GASTRO = 'caja/rendiciongastronomia';

    private const ROL_SUPERVISOR = 'Sup-tesoreria';

    /** Variantes históricas del perfil supervisor tesorería. */
    private const ROLES_SUPERVISOR_TESORERIA = [
        'Sup-tesoreria',
        'Sup-Tesoreria',
        'Sup-tesorería',
    ];

    private const SLUG_COMPROBANTE_TOTEM = 'ver-comprobante-cierre-totem-gastronomia-caja';

    /** Mínimo para acceder al index de rendiciones si el rol supervisor es nuevo. */
    private const SLUGS_ACCESO_RENDICION = [
        'listar-rendicion-gastronomia-caja',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_RENDICION_GASTRO)->value('id') ?? 0);

        $permisoId = $this->upsertPermiso(
            'Ver comprobante cierre tótem gastronomía caja',
            self::SLUG_COMPROBANTE_TOTEM,
            $menuId,
        );

        $rolIds = $this->resolverRolIdsSupervisor();

        $this->asignarPermisoARoles($permisoId, $rolIds);

        foreach (self::SLUGS_ACCESO_RENDICION as $slug) {
            $extraId = $this->resolverPermisoId($slug);
            if ($extraId > 0) {
                $this->asignarPermisoARoles($extraId, $rolIds);
            }
        }

        if ($menuId > 0) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverPermisoId(string $slug): int
    {
        return (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $permisoId = $this->resolverPermisoId($slug);

        if ($permisoId === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId > 0 ? $menuId : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $update = ['nombre' => $nombre, 'updated_at' => now()];
        if ($menuId > 0) {
            $update['menu_id'] = $menuId;
        }
        DB::table('permiso')->where('id', $permisoId)->update($update);

        return $permisoId;
    }

    /**
     * @return list<int>
     */
    private function resolverRolIdsSupervisor(): array
    {
        $ids = DB::table('rol')
            ->whereIn('nombre', self::ROLES_SUPERVISOR_TESORERIA)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $likeId = (int) (DB::table('rol')->where('nombre', 'like', 'Sup-tesorer%')->orderBy('id')->value('id') ?? 0);
        if ($likeId > 0) {
            $ids[] = $likeId;
        }

        if ($ids === []) {
            $nuevoId = $this->crearRolSupervisor();
            if ($nuevoId > 0) {
                $ids[] = $nuevoId;
            }
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
    }

    private function crearRolSupervisor(): int
    {
        $centrocostoId = (int) (DB::table('rol')
            ->whereIn('nombre', ['Enc-tesorería', 'Enc-tesoreria', 'enc-Tesoreria Operativa', 'Ger-Tesoreria'])
            ->whereNotNull('centrocosto_id')
            ->value('centrocosto_id') ?? 0);

        return (int) DB::table('rol')->insertGetId([
            'nombre' => self::ROL_SUPERVISOR,
            'centrocosto_id' => $centrocostoId > 0 ? $centrocostoId : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function asignarPermisoARoles(int $permisoId, array $rolIds): void
    {
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

    public function down(): void
    {
        $permisoId = $this->resolverPermisoId(self::SLUG_COMPROBANTE_TOTEM);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
