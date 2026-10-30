<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los permisos de tesorería Crown quedan colgados del menú de cada pantalla
 * para que el signo de pregunta de Menú - Rol los muestre junto a la tarea.
 * No asigna el permiso de reabrir a ningún rol.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const POR_MENU = [
        'caja/tesoreria-crown/jornada' => [
            'listar-tesoreria-crown-jornada',
            'crear-tesoreria-crown-jornada',
            'editar-tesoreria-crown-jornada',
            'listar-tesoreria-crown-movimiento',
            'crear-tesoreria-crown-movimiento',
            'editar-tesoreria-crown-movimiento',
            'reabrir-tesoreria-crown-caja',
        ],
        'caja/tesoreria-crown/valor' => [
            'listar-tesoreria-crown-valor',
            'crear-tesoreria-crown-valor',
            'editar-tesoreria-crown-valor',
            'actualizar-tesoreria-crown-valor',
            'eliminar-tesoreria-crown-valor',
        ],
        'caja/tesoreria-crown/deposito' => [
            'listar-tesoreria-crown-deposito',
            'crear-tesoreria-crown-deposito',
            'editar-tesoreria-crown-deposito',
            'actualizar-tesoreria-crown-deposito',
            'eliminar-tesoreria-crown-deposito',
        ],
        'caja/tesoreria-crown/cotizacion' => [
            'listar-tesoreria-crown-cotizacion',
            'crear-tesoreria-crown-cotizacion',
            'editar-tesoreria-crown-cotizacion',
            'actualizar-tesoreria-crown-cotizacion',
            'eliminar-tesoreria-crown-cotizacion',
        ],
        'caja/tesoreria-crown/boveda' => [
            'listar-tesoreria-crown-boveda',
            'crear-tesoreria-crown-boveda',
            'editar-tesoreria-crown-boveda',
        ],
        'caja/tesoreria-crown/mesa' => [
            'listar-tesoreria-crown-mesa',
            'crear-tesoreria-crown-mesa',
            'editar-tesoreria-crown-mesa',
        ],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        foreach (self::POR_MENU as $url => $slugs) {
            $menuId = DB::table('menu')->where('url', $url)->value('id');
            if (! $menuId) {
                continue;
            }
            DB::table('permiso')->whereIn('slug', $slugs)->update([
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $slugs = [];
        foreach (self::POR_MENU as $grupo) {
            foreach ($grupo as $slug) {
                $slugs[] = $slug;
            }
        }
        DB::table('permiso')->whereIn('slug', $slugs)->update([
            'menu_id' => null,
            'updated_at' => now(),
        ]);
    }
};
