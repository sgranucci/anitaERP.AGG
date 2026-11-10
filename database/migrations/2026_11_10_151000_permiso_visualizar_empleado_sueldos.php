<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Sueldos\EmpleadoLegajoVisualizacion;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso aparte para abrir el legajo en solo lectura desde el índice.
 * No reemplaza editar ni actualizar. Se asigna a Capital Humano;
 * el resto de los roles lo recibe solo si se lo tildan.
 */
return new class extends Migration
{
    private const MENU_URL = 'sueldos/empleado';

    private const PERMISO_NOMBRE = 'Visualizar legajo empleado sueldos';

    public function up(): void
    {
        if (! Schema::hasTable('permiso') || ! Schema::hasTable('permiso_rol') || ! Schema::hasTable('rol')) {
            return;
        }

        $permisoId = $this->upsertPermiso();
        if ($permisoId <= 0) {
            return;
        }

        $rolIds = $this->rolIdsCapitalHumano();
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
        if (! Schema::hasTable('permiso')) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', EmpleadoLegajoVisualizacion::PERMISO)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        $rolIds = $this->rolIdsCapitalHumano();
        if (Schema::hasTable('permiso_rol')) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
        }
        DB::table('permiso')->where('id', $permisoId)->delete();
        $this->invalidar($rolIds);
    }

    private function upsertPermiso(): int
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $existenteId = (int) (DB::table('permiso')->where('slug', EmpleadoLegajoVisualizacion::PERMISO)->value('id') ?? 0);
        $ahora = now();
        $payload = [
            'nombre' => self::PERMISO_NOMBRE,
            'menu_id' => $menuId > 0 ? $menuId : null,
            'updated_at' => $ahora,
        ];

        if ($existenteId > 0) {
            DB::table('permiso')->where('id', $existenteId)->update($payload);

            return $existenteId;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, [
            'slug' => EmpleadoLegajoVisualizacion::PERMISO,
            'created_at' => $ahora,
        ]));
    }

    /** @return list<int> */
    private function rolIdsCapitalHumano(): array
    {
        return DB::table('rol')
            ->where('nombre', 'like', '%apital%umano%')
            ->orWhere('nombre', 'like', '%capitalhumano%')
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /** @param  list<int>  $rolIds */
    private function invalidar(array $rolIds): void
    {
        SuitecrmPermiso::flushCachePermisos();
        foreach ($rolIds as $rolId) {
            PermisoCacheSupport::forgetRol($rolId);
        }
    }
};
