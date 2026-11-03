<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: Sup-contaduria (Lana / Julieta) puede ingresar y guardar facturas de venta.
 * Ya tenían listar/editar; faltaban crear-factura y actualizar-factura.
 */
return new class extends Migration
{
    private const ROL = 'Sup-contaduria';

    /** @var list<string> */
    private const SLUGS = [
        'crear-factura',
        'actualizar-factura',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $menuFacturaId = (int) (DB::table('menu')->where('url', 'ventas/factura')->value('id') ?? 0);
        if ($menuFacturaId > 0 && ! DB::table('menu_rol')->where('rol_id', $rolId)->where('menu_id', $menuFacturaId)->exists()) {
            DB::table('menu_rol')->insert([
                'rol_id' => $rolId,
                'menu_id' => $menuFacturaId,
            ]);
        }

        foreach (self::SLUGS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            if (DB::table('permiso_rol')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->exists()) {
                continue;
            }
            DB::table('permiso_rol')->insert([
                'rol_id' => $rolId,
                'permiso_id' => $permisoId,
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::SLUGS)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($permisoIds === []) {
            return;
        }

        DB::table('permiso_rol')
            ->where('rol_id', $rolId)
            ->whereIn('permiso_id', $permisoIds)
            ->delete();

        SuitecrmPermiso::flushCachePermisos();
    }
};
