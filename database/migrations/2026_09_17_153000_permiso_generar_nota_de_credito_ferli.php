<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ferli: faltaba el permiso generar-nota-de-credito (can() en factura/cobranza).
 * Se asigna a los mismos roles que ya tienen crear-factura / listar-factura.
 */
return new class extends Migration
{
    private const SLUG = 'generar-nota-de-credito';

    private const NOMBRE = 'Generar nota de crédito';

    private const MENU_URL = 'ventas/factura';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        $now = now();

        if ($permisoId <= 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => self::NOMBRE,
                'slug' => self::SLUG,
                'menu_id' => $menuId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => self::NOMBRE,
                'menu_id' => $menuId,
                'updated_at' => $now,
            ]);
        }

        $rolIds = DB::table('permiso_rol as pr')
            ->join('permiso as p', 'p.id', '=', 'pr.permiso_id')
            ->whereIn('p.slug', ['crear-factura', 'listar-factura'])
            ->pluck('pr.rol_id')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        foreach ($rolIds as $rolId) {
            if ($rolId <= 0) {
                continue;
            }
            $existe = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->exists();
            if ($existe) {
                continue;
            }
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
        DB::table('permiso')->where('id', $permisoId)->delete();
        SuitecrmPermiso::flushCachePermisos();
    }
};
