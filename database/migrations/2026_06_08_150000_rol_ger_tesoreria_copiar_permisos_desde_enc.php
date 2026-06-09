<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROL_ENC = 'Enc-tesorería';

    private const ROL_GER = 'Ger-Tesoreria';

    public function up(): void
    {
        $rolEncId = $this->resolverRolEncId();
        $rolGerId = $this->resolverRolGerId();

        if ($rolEncId <= 0 || $rolGerId <= 0) {
            return;
        }

        $this->copiarMenusYPermisosDesdeEnc($rolEncId, $rolGerId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $rolEncId = $this->resolverRolEncId();
        $rolGerId = $this->resolverRolGerId();

        if ($rolEncId <= 0 || $rolGerId <= 0) {
            return;
        }

        $permisoIdsEnc = DB::table('permiso_rol')->where('rol_id', $rolEncId)->pluck('permiso_id')->all();
        if ($permisoIdsEnc !== []) {
            DB::table('permiso_rol')
                ->where('rol_id', $rolGerId)
                ->whereIn('permiso_id', $permisoIdsEnc)
                ->delete();
        }

        $menuIdsEnc = DB::table('menu_rol')->where('rol_id', $rolEncId)->pluck('menu_id')->all();
        if ($menuIdsEnc !== []) {
            DB::table('menu_rol')
                ->where('rol_id', $rolGerId)
                ->whereIn('menu_id', $menuIdsEnc)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverRolEncId(): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL_ENC)->value('id') ?? 0);

        return $id > 0 ? $id : (int) (DB::table('rol')->where('nombre', 'like', 'Enc-tesorer%')->orderBy('id')->value('id') ?? 0);
    }

    private function resolverRolGerId(): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL_GER)->value('id') ?? 0);

        return $id > 0 ? $id : (int) (DB::table('rol')->where('nombre', 'like', 'Ger-Tesorer%')->orderBy('id')->value('id') ?? 0);
    }

    private function copiarMenusYPermisosDesdeEnc(int $rolEncId, int $rolGerId): void
    {
        foreach (DB::table('menu_rol')->where('rol_id', $rolEncId)->pluck('menu_id') as $menuId) {
            $mid = (int) $menuId;
            if ($mid > 0 && ! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolGerId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $mid, 'rol_id' => $rolGerId]);
            }
        }

        foreach (DB::table('permiso_rol')->where('rol_id', $rolEncId)->pluck('permiso_id') as $permisoId) {
            $pid = (int) $permisoId;
            if ($pid > 0 && ! DB::table('permiso_rol')->where('permiso_id', $pid)->where('rol_id', $rolGerId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $pid, 'rol_id' => $rolGerId]);
            }
        }
    }
};
