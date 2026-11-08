<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos del selector del anexo II (l-resmesa: mesas / máquinas / gastronomía / todo).
 * No se asignan a ningún rol: Sistemas los tilda en Permiso - Rol.
 */
return new class extends Migration
{
    /** @var array<string, array{nombre: string, ayuda: string}> */
    private const PERMISOS = [
        'listar-tesoreria-crown-anexo2-mesas' => [
            'nombre' => 'Anexo II Crown — mesas',
            'ayuda' => 'En el anexo II, ver y exportar solo el bloque de mesas (créditos, drop, resultado por juego). No graba en Anita. Hay que tildarlo en el rol.',
        ],
        'listar-tesoreria-crown-anexo2-maquinas' => [
            'nombre' => 'Anexo II Crown — máquinas',
            'ayuda' => 'En el anexo II, ver y exportar solo el bloque de slots (ventas cashless, validadoras, pagos, resultado). No graba en Anita. Hay que tildarlo en el rol.',
        ],
        'listar-tesoreria-crown-anexo2-gastronomia' => [
            'nombre' => 'Anexo II Crown — gastronomía',
            'ayuda' => 'En el anexo II, ver y exportar solo el bloque gastro/hotel (ventas Z, rendiciones, tarjetas). No graba en Anita. Hay que tildarlo en el rol.',
        ],
        'listar-tesoreria-crown-anexo2-todo' => [
            'nombre' => 'Anexo II Crown — todo',
            'ayuda' => 'En el anexo II, ver y exportar el informe completo (mesas + máquinas + gastronomía), como el resultado total de Anita. No graba en Anita. Hay que tildarlo en el rol.',
        ],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $ahora = now();
        $menuId = DB::table('menu')->where('url', 'caja/tesoreria-crown/anexo')->value('id');
        foreach (self::PERMISOS as $slug => $datos) {
            $id = DB::table('permiso')->where('slug', $slug)->value('id');
            if (! $id) {
                $id = DB::table('permiso')->insertGetId([
                    'nombre' => $datos['nombre'],
                    'slug' => $slug,
                    'ayuda' => $datos['ayuda'],
                    'menu_id' => $menuId,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            } else {
                DB::table('permiso')->where('id', $id)->update([
                    'nombre' => $datos['nombre'],
                    'ayuda' => $datos['ayuda'],
                    'menu_id' => $menuId ?: DB::table('permiso')->where('id', $id)->value('menu_id'),
                    'updated_at' => $ahora,
                ]);
            }
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', array_keys(self::PERMISOS))->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }
};
