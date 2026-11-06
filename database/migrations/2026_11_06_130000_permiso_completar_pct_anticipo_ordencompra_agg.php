<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Compras\ComprobanteProveedorAnticipoPctSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AGG: completar el % de anticipo en una OC ya aprobada (legado sin %)
 * queda solo para roles de Compras.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/ordencompra';

    private const PERMISO_NOMBRE = 'Completar % anticipo en OC aprobada';

    /** @var list<string> */
    private const ROLES_COMPRAS = [
        'Enc-compras',
        'Op-Compras',
        'Op-Compras-arbol',
        'Op-Compras-SP',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }
        if (! Schema::hasTable('permiso') || ! Schema::hasTable('permiso_rol') || ! Schema::hasTable('rol')) {
            return;
        }

        $permisoId = $this->upsertPermiso();
        if ($permisoId <= 0) {
            return;
        }

        $rolIds = $this->rolIdsCompras();
        foreach ($rolIds as $rolId) {
            $ya = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->exists();
            if ($ya) {
                continue;
            }
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }

        $this->invalidar($rolIds);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }
        if (! Schema::hasTable('permiso')) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')
            ->where('slug', ComprobanteProveedorAnticipoPctSupport::PERMISO_COMPLETAR_PCT_OC_APROBADA)
            ->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        $rolIds = $this->rolIdsCompras();
        if (Schema::hasTable('permiso_rol')) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
        }
        DB::table('permiso')->where('id', $permisoId)->delete();

        $this->invalidar($rolIds);
    }

    private function upsertPermiso(): int
    {
        $slug = ComprobanteProveedorAnticipoPctSupport::PERMISO_COMPLETAR_PCT_OC_APROBADA;
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $existenteId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $ahora = now();

        if ($existenteId > 0) {
            DB::table('permiso')->where('id', $existenteId)->update([
                'nombre' => self::PERMISO_NOMBRE,
                'menu_id' => $menuId > 0 ? $menuId : null,
                'updated_at' => $ahora,
            ]);

            return $existenteId;
        }

        return (int) DB::table('permiso')->insertGetId([
            'nombre' => self::PERMISO_NOMBRE,
            'slug' => $slug,
            'menu_id' => $menuId > 0 ? $menuId : null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    /**
     * @return list<int>
     */
    private function rolIdsCompras(): array
    {
        return DB::table('rol')
            ->whereIn('nombre', self::ROLES_COMPRAS)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $rolIds
     */
    private function invalidar(array $rolIds): void
    {
        SuitecrmPermiso::flushCachePermisos();
        foreach ($rolIds as $rolId) {
            PermisoCacheSupport::forgetRol($rolId);
        }
    }
};
