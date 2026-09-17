<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AGG: copia permiso_rol de Enc-contaduría a Sup-contaduria
 * (la migración de menú no había copiado los permisos de acción).
 */
return new class extends Migration
{
    private const ROL_ENC = 'Enc-contaduría';

    private const ROL_SUP = 'Sup-contaduria';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        if (! Schema::hasTable('permiso_rol') || ! Schema::hasTable('rol')) {
            return;
        }

        $rolEncId = $this->resolverRolEncId();
        $rolSupId = $this->resolverRolSupId();

        if ($rolEncId <= 0 || $rolSupId <= 0) {
            return;
        }

        $this->copiarPermisoRolDesdeEnc($rolEncId, $rolSupId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        if (! Schema::hasTable('permiso_rol') || ! Schema::hasTable('rol')) {
            return;
        }

        $rolEncId = $this->resolverRolEncId();
        $rolSupId = $this->resolverRolSupId();

        if ($rolEncId <= 0 || $rolSupId <= 0) {
            return;
        }

        $permisoIdsEnc = DB::table('permiso_rol')->where('rol_id', $rolEncId)->pluck('permiso_id')->all();
        if ($permisoIdsEnc !== []) {
            DB::table('permiso_rol')
                ->where('rol_id', $rolSupId)
                ->whereIn('permiso_id', $permisoIdsEnc)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverRolEncId(): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL_ENC)->value('id') ?? 0);

        return $id > 0
            ? $id
            : (int) (DB::table('rol')->where('nombre', 'like', 'Enc-contadur%')->orderBy('id')->value('id') ?? 0);
    }

    private function resolverRolSupId(): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL_SUP)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('rol')->where('nombre', 'like', 'Sup-contadur%')->orderBy('id')->value('id') ?? 0);
    }

    private function copiarPermisoRolDesdeEnc(int $rolEncId, int $rolSupId): void
    {
        foreach (DB::table('permiso_rol')->where('rol_id', $rolEncId)->pluck('permiso_id') as $permisoId) {
            $pid = (int) $permisoId;
            if ($pid <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $pid)->where('rol_id', $rolSupId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $pid,
                    'rol_id' => $rolSupId,
                ]);
            }
        }
    }
};
