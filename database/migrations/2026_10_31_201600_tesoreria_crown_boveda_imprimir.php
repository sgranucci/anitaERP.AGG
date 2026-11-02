<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso especial para emitir el comprobante de bóveda.
 * No se asigna a ningún rol: Sistemas lo tilda en Permiso - Rol.
 */
return new class extends Migration
{
    private const PERMISO = 'imprimir-tesoreria-crown-boveda';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('permiso')) {
            return;
        }

        $ahora = now();
        $menuId = DB::table('menu')->where('url', 'caja/tesoreria-crown/boveda')->value('id');
        $id = DB::table('permiso')->where('slug', self::PERMISO)->value('id');
        $fila = [
            'nombre' => 'Imprimir comprobante de bóveda de tesorería Crown',
            'updated_at' => $ahora,
        ];
        if (Schema::hasColumn('permiso', 'ayuda')) {
            $fila['ayuda'] = 'Permiso especial de bóveda. No viene asignado a ningún rol: se tilda solo en quien puede emitir el comprobante. Con este permiso, al grabar un alta se puede marcar Emitir comprobante, y en el listado y en la edición aparece Imprimir. No carga, no confirma y no anula.';
        }
        if (Schema::hasColumn('permiso', 'menu_id')) {
            $fila['menu_id'] = $menuId;
        }
        if (! $id) {
            $fila['slug'] = self::PERMISO;
            $fila['created_at'] = $ahora;
            DB::table('permiso')->insert($fila);
        } else {
            DB::table('permiso')->where('id', $id)->update($fila);
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
};
