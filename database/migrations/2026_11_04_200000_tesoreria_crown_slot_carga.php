<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos para cargar y editar recuentos de slots en Crown.
 * No se asignan a roles: se tildan en Permiso-Rol. Administrador entra igual.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISOS = [
        'crear-tesoreria-crown-slot' => 'Crear recuento de slots Crown',
        'editar-tesoreria-crown-slot' => 'Editar y confirmar recuento de slots Crown',
        'eliminar-borrador-tesoreria-crown-slot' => 'Eliminar borrador de slots Crown',
    ];

    /** @var array<string, string> */
    private const AYUDAS = [
        'crear-tesoreria-crown-slot' => 'Abrir un recuento nuevo (contadora, manual o corrección) en la jornada abierta y guardar el borrador con el billetaje.',
        'editar-tesoreria-crown-slot' => 'Modificar el borrador, confirmar el recuento con firma de tesorería. El drop importado de Anita queda solo en consulta.',
        'eliminar-borrador-tesoreria-crown-slot' => 'Borrar un borrador de recuento sin reverso. No se asigna a ningún rol al instalarlo.',
        'listar-tesoreria-crown-slot' => 'Ver en la jornada el drop de máquinas y el recuento de stacker del día, con el billetaje de cada uno.',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/slot')->first();
        $ahora = now();
        foreach (self::PERMISOS as $slug => $nombre) {
            $id = DB::table('permiso')->where('slug', $slug)->value('id');
            if (! $id) {
                DB::table('permiso')->insert([
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'ayuda' => self::AYUDAS[$slug] ?? null,
                    'menu_id' => $menu?->id,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            } else {
                DB::table('permiso')->where('id', $id)->update([
                    'ayuda' => self::AYUDAS[$slug] ?? null,
                    'menu_id' => $menu?->id,
                    'updated_at' => $ahora,
                ]);
            }
        }
        DB::table('permiso')->where('slug', 'listar-tesoreria-crown-slot')->update([
            'ayuda' => self::AYUDAS['listar-tesoreria-crown-slot'],
            'updated_at' => $ahora,
        ]);
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $ids = DB::table('permiso')->whereIn('slug', array_keys(self::PERMISOS))->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $ids)->delete();
            DB::table('permiso')->whereIn('id', $ids)->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }
};
