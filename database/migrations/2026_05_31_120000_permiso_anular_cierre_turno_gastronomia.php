<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/gastronomia/habilitacion-turno';

    private const ROL_ENC_GASTRONOMIA = 'Enc-gastronomía';

    private const ROL_ADMINISTRADOR = 'administrador';

    private const SLUG = 'anular-cierre-turno-gastronomia';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Anular cierre definitivo turno gastronomía',
                'slug' => self::SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Anular cierre definitivo turno gastronomía',
                'updated_at' => now(),
            ]);
        }

        foreach ($this->rolesAutorizados() as $rolId) {
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
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
    }

    /**
     * @return list<int>
     */
    private function rolesAutorizados(): array
    {
        $ids = [];
        $enc = (int) (DB::table('rol')->where('nombre', self::ROL_ENC_GASTRONOMIA)->value('id') ?? 0);
        if ($enc <= 0) {
            $enc = (int) (DB::table('rol')->where('nombre', 'like', 'Enc-gastronom%')->value('id') ?? 0);
        }
        if ($enc > 0) {
            $ids[] = $enc;
        }

        $admin = (int) (DB::table('rol')->where('nombre', self::ROL_ADMINISTRADOR)->value('id') ?? 0);
        if ($admin > 0) {
            $ids[] = $admin;
        }

        return array_values(array_unique($ids));
    }
};
