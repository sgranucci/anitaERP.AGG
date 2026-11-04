<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Borrar un borrador de mesa es un permiso aparte.
 * No se asigna a ningún rol: se tilda en Permiso - Rol.
 * El borrador no entra al stock y no se imprime, así que se borra sin armar una reversa.
 */
return new class extends Migration
{
    private const PERMISO = 'eliminar-borrador-tesoreria-crown-mesa';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('permiso')) {
            return;
        }

        $ahora = now();
        $menuId = DB::table('menu')->where('url', 'caja/tesoreria-crown/mesa')->value('id');
        $id = DB::table('permiso')->where('slug', self::PERMISO)->value('id');
        if (! $id) {
            DB::table('permiso')->insert([
                'nombre' => 'Eliminar borrador de mesa de tesorería Crown',
                'slug' => self::PERMISO,
                'ayuda' => self::ayuda(),
                'menu_id' => $menuId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        } else {
            DB::table('permiso')->where('id', $id)->update([
                'nombre' => 'Eliminar borrador de mesa de tesorería Crown',
                'ayuda' => self::ayuda(),
                'menu_id' => $menuId,
                'updated_at' => $ahora,
            ]);
        }

        DB::table('permiso')->where('slug', 'crear-tesoreria-crown-mesa')->update([
            'ayuda' => 'Cargar un movimiento de mesa en borrador (habilitación, reposición, crédito, estado final, drop o propina) y guardar las cantidades. El borrador no entra al stock. No lo borra: eso es el permiso de eliminar borrador.',
            'updated_at' => $ahora,
        ]);
        DB::table('permiso')->where('slug', 'editar-tesoreria-crown-mesa')->update([
            'ayuda' => 'Confirmar un movimiento de mesa y anular uno ya confirmado. Al confirmar guarda la vigencia de la banca de esa fecha. Anular lo saca del stock y deja el número. No borra un borrador y no arma una reversa.',
            'updated_at' => $ahora,
        ]);

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

        $ahora = now();
        DB::table('permiso')->where('slug', 'crear-tesoreria-crown-mesa')->update([
            'ayuda' => 'Cargar un movimiento de mesa en borrador (habilitación, reposición, crédito, estado final, drop o propina). El borrador no entra al stock.',
            'updated_at' => $ahora,
        ]);
        DB::table('permiso')->where('slug', 'editar-tesoreria-crown-mesa')->update([
            'ayuda' => 'Confirmar un movimiento de mesa y anularlo. Al confirmar guarda la vigencia de la banca de esa fecha.',
            'updated_at' => $ahora,
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    private static function ayuda(): string
    {
        return 'Permiso especial. No viene asignado a ningún rol: se tilda en Permiso - Rol, en el rol de quien pueda descartar un borrador de mesa. En el movimiento, Eliminar borrador lo borra con la jornada abierta. No pide reversa: el borrador no entró al stock y estas operaciones no se imprimen. No anula un movimiento confirmado.';
    }
};
