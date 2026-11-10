<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de cajas Crown separada de tesorería.
 * El permiso no se asigna a ningún rol: lo tilda Sistemas en Permiso - Rol.
 */
return new class extends Migration
{
    private const PERMISO = 'auditar-tesoreria-crown-caja';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('permiso')) {
            return;
        }
        $ahora = now();
        $fila = [
            'nombre' => 'Auditar cajas de tesorería Crown',
            'updated_at' => $ahora,
        ];
        if (Schema::hasColumn('permiso', 'ayuda')) {
            $fila['ayuda'] = self::AYUDA;
        }
        if (DB::table('permiso')->where('slug', self::PERMISO)->exists()) {
            DB::table('permiso')->where('slug', self::PERMISO)->update($fila);
        } else {
            DB::table('permiso')->insert($fila + [
                'slug' => self::PERMISO,
                'created_at' => $ahora,
            ]);
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('permiso')) {
            return;
        }
        $permisoId = DB::table('permiso')->where('slug', self::PERMISO)->value('id');
        if ($permisoId) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    private const AYUDA = 'Permiso especial de auditoría, aparte de tesorería. No viene asignado a ningún rol: se tilda solo en el rol de auditoría. '
        .'Firma el cierre de auditoría de una caja (no puede ser quien la cerró ni quien la mandó a auditoría), '
        .'resuelve los comprobantes no conformes (los devuelve a borrador o los anula) '
        .'y es obligatorio, junto con el de reabrir, para reabrir una caja que está en auditoría. '
        .'El rol administrador entra igual, porque ese rol no consulta los permisos.';
};
