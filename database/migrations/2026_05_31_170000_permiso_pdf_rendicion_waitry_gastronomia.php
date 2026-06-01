<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_RENDICION_URL = 'caja/rendiciongastronomia';

    private const MENU_WAITRY_URL = 'caja/waitry-cierre-jornada';

    /** Encargados de tesorería: únicos con PDF Waitry. */
    private const ROLES_ENCARGADO_TESORERIA = [
        'Enc-tesorería',
        'enc-Tesoreria Operativa',
    ];

    /** Cajeros operativos: PDF de rendición, no Waitry. */
    private const ROLES_OP_TESORERIA = [
        'Op-tesoreria',
        'op-Tesoreria Operativa',
    ];

    private const SLUG_PDF_RENDICION = 'ver-pdf-rendicion-gastronomia-caja';

    private const SLUG_PDF_WAITRY = 'ver-pdf-waitry-gastronomia-caja';

    /** @var list<string> */
    private const SLUGS_RENDICION_REF = [
        'listar-rendicion-gastronomia-caja',
        'crear-rendicion-gastronomia-caja',
        'editar-rendicion-gastronomia-caja',
        'actualizar-rendicion-gastronomia-caja',
    ];

    public function up(): void
    {
        $menuRendicionId = (int) (DB::table('menu')->where('url', self::MENU_RENDICION_URL)->value('id') ?? 0);
        $menuWaitryId = (int) (DB::table('menu')->where('url', self::MENU_WAITRY_URL)->value('id') ?? 0);

        $permisoRendicionId = $this->upsertPermiso(
            'Ver PDF rendición gastronomía caja',
            self::SLUG_PDF_RENDICION,
            $menuRendicionId,
        );
        $permisoWaitryId = $this->upsertPermiso(
            'Ver PDF Waitry gastronomía caja',
            self::SLUG_PDF_WAITRY,
            $menuWaitryId > 0 ? $menuWaitryId : $menuRendicionId,
        );

        $rolIdsEncargado = $this->rolIdsPorNombre(self::ROLES_ENCARGADO_TESORERIA);
        $rolIdsOp = $this->rolIdsPorNombre(self::ROLES_OP_TESORERIA);
        $rolIdsConRendicion = $this->rolIdsConAlgunPermisoRendicion();

        $this->asignarPermisoARoles($permisoRendicionId, array_values(array_unique(array_merge(
            $rolIdsEncargado,
            $rolIdsOp,
            $rolIdsConRendicion,
        ))));

        $this->asignarPermisoARoles($permisoWaitryId, $rolIdsEncargado);

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);

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
     * @return list<int>
     */
    private function rolIdsConAlgunPermisoRendicion(): array
    {
        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::SLUGS_RENDICION_REF)
            ->pluck('id')
            ->all();

        if ($permisoIds === []) {
            return [];
        }

        return DB::table('permiso_rol')
            ->whereIn('permiso_id', $permisoIds)
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
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
        foreach ([self::SLUG_PDF_RENDICION, self::SLUG_PDF_WAITRY] as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
