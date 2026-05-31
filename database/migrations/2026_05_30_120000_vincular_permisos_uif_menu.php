<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Vincula permisos del módulo UIF con la opción de menú cuya URL coincide con la pantalla.
     *
     * @var array<string, list<string>>
     */
    private const MENU_PERMISO_SLUGS = [
        'uif/actividad_uif' => [
            'listar-actividad-uif',
            'crear-actividad-uif',
            'editar-actividad-uif',
            'actualizar-actividad-uif',
            'borrar-actividad-uif',
        ],
        'uif/pais_uif' => [
            'listar-pais-uif',
            'crear-pais-uif',
            'editar-pais-uif',
            'actualizar-pais-uif',
            'borrar-pais-uif',
        ],
        'uif/pep_uif' => [
            'listar-pep-uif',
            'crear-pep-uif',
            'editar-pep-uif',
            'actualizar-pep-uif',
            'borrar-pep-uif',
        ],
        'uif/so_uif' => [
            'listar-so-uif',
            'crear-so-uif',
            'editar-so-uif',
            'actualizar-so-uif',
            'borrar-so-uif',
        ],
        'uif/provincia_uif' => [
            'listar-provincia-uif',
            'crear-provincia-uif',
            'editar-provincia-uif',
            'actualizar-provincia-uif',
            'borrar-provincia-uif',
        ],
        'uif/frecuencia_uif' => [
            'listar-frecuencia-uif',
            'crear-frecuencia-uif',
            'editar-frecuencia-uif',
            'actualizar-frecuencia-uif',
            'borrar-frecuencia-uif',
        ],
        'uif/juego_uif' => [
            'listar-juego-uif',
            'crear-juego-uif',
            'editar-juego-uif',
            'actualizar-juego-uif',
            'borrar-juego-uif',
        ],
        'uif/inusualidad_uif' => [
            'listar-inusualidad-uif',
            'crear-inusualidad-uif',
            'editar-inusualidad-uif',
            'actualizar-inusualidad-uif',
            'borrar-inusualidad-uif',
        ],
        'uif/monto_uif' => [
            'listar-monto-uif',
            'crear-monto-uif',
            'editar-monto-uif',
            'actualizar-monto-uif',
            'borrar-monto-uif',
        ],
        'uif/factorriesgo_uif' => [
            'listar-factorriesgo-uif',
            'crear-factorriesgo-uif',
            'editar-factorriesgo-uif',
            'actualizar-factorriesgo-uif',
            'borrar-factorriesgo-uif',
        ],
        'uif/puntaje_uif' => [
            'listar-puntaje-uif',
            'crear-puntaje-uif',
            'editar-puntaje-uif',
            'actualizar-puntaje-uif',
            'borrar-puntaje-uif',
        ],
        'uif/localidad_uif' => [
            'listar-localidad-uif',
            'crear-localidad-uif',
            'editar-localidad-uif',
            'actualizar-localidad-uif',
            'borrar-localidad-uif',
        ],
        'uif/profesion_uif' => [
            'listar-profesion-uif',
            'crear-profesion-uif',
            'editar-profesion-uif',
            'actualizar-profesion-uif',
            'borrar-profesion-uif',
        ],
        'uif/nivelsocioeconomico_uif' => [
            'listar-nivelsocioeconomico-uif',
            'crear-nivelsocioeconomico-uif',
            'editar-nivelsocioeconomico-uif',
            'actualizar-nivelsocioeconomico-uif',
            'borrar-nivelsocioeconomico-uif',
        ],
        'uif/estadocivil_uif' => [
            'listar-estadocivil-uif',
            'crear-estadocivil-uif',
            'editar-estadocivil-uif',
            'actualizar-estadocivil-uif',
            'borrar-estadocivil-uif',
        ],
        'uif/cliente_uif' => [
            'listar-cliente-uif',
            'crear-cliente-uif',
            'editar-cliente-uif',
            'actualizar-cliente-uif',
            'borrar-cliente-uif',
            'cajero-uif',
            'supervisor-uif',
        ],
        'uif/premio_uif' => [
            'listar-cliente-premio-uif',
            'crear-cliente-premio-uif',
            'editar-cliente-premio-uif',
            'actualizar-cliente-premio-uif',
            'borrar-cliente-premio-uif',
        ],
        'uif/cliente_congelado_uif' => [
            'listar-cliente-congelado-uif',
            'crear-cliente-congelado-uif',
            'editar-cliente-congelado-uif',
            'actualizar-cliente-congelado-uif',
            'borrar-cliente-congelado-uif',
            'importar-cliente-congelado-uif',
        ],
        'uif/crearexportaoperacion' => [
            'exportar-operacion-uif',
        ],
    ];

    public function up(): void
    {
        foreach (self::MENU_PERMISO_SLUGS as $menuUrl => $slugs) {
            $menuId = (int) (DB::table('menu')->where('url', $menuUrl)->value('id') ?? 0);
            if ($menuId === 0) {
                continue;
            }

            DB::table('permiso')
                ->whereIn('slug', $slugs)
                ->update([
                    'menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $allSlugs = [];
        foreach (self::MENU_PERMISO_SLUGS as $slugs) {
            foreach ($slugs as $slug) {
                $allSlugs[] = $slug;
            }
        }

        DB::table('permiso')
            ->whereIn('slug', $allSlugs)
            ->update([
                'menu_id' => null,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};
