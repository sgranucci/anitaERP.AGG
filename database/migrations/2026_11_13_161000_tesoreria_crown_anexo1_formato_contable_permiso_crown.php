<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permiso para el formato contable del Anexo I (día / mes / anual).
 * Sin él solo queda debe/haber. Se asigna a admin / contaduría / sistemas;
 * los roles de tesorería no lo reciben.
 */
return new class extends Migration
{
    private const SLUG = 'listar-tesoreria-crown-anexo1-contable';

    private const ANEXO_LISTAR = 'listar-tesoreria-crown-anexo';

    /** @var list<string> */
    private const ROLES_CONTABLE = [
        'administrador',
        'Enc-contaduría',
        'Enc-sistemas',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('permiso')) {
            return;
        }

        $ahora = now();
        $menuId = DB::table('menu')->where('url', 'caja/tesoreria-crown/anexo')->value('id')
            ?: DB::table('permiso')->where('slug', self::ANEXO_LISTAR)->value('menu_id');

        $id = DB::table('permiso')->where('slug', self::SLUG)->value('id');
        if (! $id) {
            $id = DB::table('permiso')->insertGetId([
                'nombre' => 'Anexo I Crown — formato contable',
                'slug' => self::SLUG,
                'ayuda' => 'En el Anexo I, ver y exportar el formato contable (día / mes / anual, como l-anexo1.c). Sin este permiso solo queda el formato debe / haber / saldo. Pensado para contaduría; tesorería opera solo con debe/haber.',
                'menu_id' => $menuId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        } else {
            DB::table('permiso')->where('id', $id)->update([
                'nombre' => 'Anexo I Crown — formato contable',
                'ayuda' => 'En el Anexo I, ver y exportar el formato contable (día / mes / anual, como l-anexo1.c). Sin este permiso solo queda el formato debe / haber / saldo. Pensado para contaduría; tesorería opera solo con debe/haber.',
                'menu_id' => $menuId ?: DB::table('permiso')->where('id', $id)->value('menu_id'),
                'updated_at' => $ahora,
            ]);
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES_CONTABLE)->pluck('id');
        foreach ($rolIds as $rolId) {
            $existe = DB::table('permiso_rol')
                ->where('permiso_id', $id)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $id,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('permiso')) {
            return;
        }

        $id = DB::table('permiso')->where('slug', self::SLUG)->value('id');
        if (! $id) {
            return;
        }

        DB::table('permiso_rol')->where('permiso_id', $id)->delete();
        DB::table('permiso')->where('id', $id)->delete();
        SuitecrmPermiso::flushCachePermisos();
    }
};
