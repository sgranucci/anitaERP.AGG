<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Contable\AsientoEmpresaCambioSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mover un asiento ya cargado a otra empresa lo saca de una contabilidad y lo mete
 * en otra (renumera en Anita). El permiso existe en todas las instalaciones para
 * poder asignarlo desde el ABM de roles; en AGG queda solo para contaduría
 * (Sup-contaduria y Enc-contaduría), el resto sigue con la empresa bloqueada.
 */
return new class extends Migration
{
    private const MENU_URL = 'contable/asiento';

    private const PERMISO_NOMBRE = 'Cambiar empresa de asiento';

    private const ROLES_AGG = ['Sup-contaduria', 'Enc-contaduría'];

    public function up(): void
    {
        $permisoId = $this->upsertPermiso();
        if ($permisoId <= 0) {
            return;
        }

        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $rolIds = $this->rolIdsAgg();

        foreach ($rolIds as $rolId) {
            $yaTiene = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->exists();

            if (! $yaTiene) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        $this->invalidarCacheRoles($rolIds);
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')
            ->where('slug', AsientoEmpresaCambioSupport::PERMISO)
            ->value('id') ?? 0);

        if ($permisoId <= 0) {
            return;
        }

        $rolIds = EntornoEmpresaSupport::esAgg() ? $this->rolIdsAgg() : [];

        if ($rolIds !== []) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->exists()) {
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $this->invalidarCacheRoles($rolIds);
    }

    /**
     * @return list<int>
     */
    private function rolIdsAgg(): array
    {
        return DB::table('rol')
            ->whereIn('nombre', self::ROLES_AGG)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function upsertPermiso(): int
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return 0;
        }

        $id = (int) (DB::table('permiso')
            ->where('slug', AsientoEmpresaCambioSupport::PERMISO)
            ->value('id') ?? 0);

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
            'slug' => AsientoEmpresaCambioSupport::PERMISO,
            'menu_id' => $menuId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
};
