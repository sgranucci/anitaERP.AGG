<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supervisor tesorería: ver Informe Z (gastro) y Totales Z jornada (estacionamiento) desde rendiciones caja.
 */
return new class extends Migration
{
    private const MENU_RENDICION_GASTRO = 'caja/rendiciongastronomia';

    private const MENU_RENDICION_ESTACIONAMIENTO = 'caja/rendicionestacionamiento';

    /** Perfil supervisor tesorería (variantes históricas del ERP). */
    private const ROLES_SUPERVISOR_TESORERIA = [
        'Enc-tesorería',
        'Enc-tesoreria',
        'enc-Tesoreria Operativa',
        'Sup-tesoreria',
        'Sup-Tesoreria',
        'Sup-tesorería',
    ];

    private const SLUG_PDF_WAITRY_GASTRO = 'ver-pdf-waitry-gastronomia-caja';

    private const SLUG_PDF_Z_ESTACIONAMIENTO = 'ver-pdf-z-jornada-estacionamiento-caja';

    public function up(): void
    {
        $menuGastroId = (int) (DB::table('menu')->where('url', self::MENU_RENDICION_GASTRO)->value('id') ?? 0);
        $menuEstId = (int) (DB::table('menu')->where('url', self::MENU_RENDICION_ESTACIONAMIENTO)->value('id') ?? 0);

        $permisoWaitryId = $this->resolverPermisoId(self::SLUG_PDF_WAITRY_GASTRO);
        $permisoEstZId = $this->upsertPermiso(
            'Ver PDF Totales Z jornada estacionamiento caja',
            self::SLUG_PDF_Z_ESTACIONAMIENTO,
            $menuEstId > 0 ? $menuEstId : $menuGastroId,
        );

        $rolIds = $this->rolIdsPorNombre(self::ROLES_SUPERVISOR_TESORERIA);

        if ($permisoWaitryId > 0) {
            $this->asignarPermisoARoles($permisoWaitryId, $rolIds);
        }

        $this->asignarPermisoARoles($permisoEstZId, $rolIds);

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
     * @param  list<string>  $nombres
     * @return list<int>
     */
    private function rolIdsPorNombre(array $nombres): array
    {
        return DB::table('rol')
            ->whereIn('nombre', $nombres)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
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
        $rolIds = $this->rolIdsPorNombre(self::ROLES_SUPERVISOR_TESORERIA);

        foreach ([self::SLUG_PDF_WAITRY_GASTRO, self::SLUG_PDF_Z_ESTACIONAMIENTO] as $slug) {
            $permisoId = $this->resolverPermisoId($slug);
            if ($permisoId <= 0) {
                continue;
            }
            if ($slug === self::SLUG_PDF_Z_ESTACIONAMIENTO) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();

                continue;
            }
            if ($rolIds !== []) {
                DB::table('permiso_rol')
                    ->where('permiso_id', $permisoId)
                    ->whereIn('rol_id', $rolIds)
                    ->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
