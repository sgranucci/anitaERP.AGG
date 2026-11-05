<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Amplía el permiso de anexo I a todo el juego de anexos Crown
 * (II mesas, resumen cajeros, III, VII fallos, VIII bóveda).
 * No crea menús nuevos: se navegan desde el escritorio de la jornada.
 */
return new class extends Migration
{
    private const SLUG = 'listar-tesoreria-crown-anexo';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $ahora = now();
        DB::table('permiso')->where('slug', self::SLUG)->update([
            'nombre' => 'Listar anexos de tesorería Crown',
            'ayuda' => 'Ver y exportar los anexos del día: I (clásico y nuevo, con apuestas deportivas), II (mesas), resumen de cajeros, III, VII (fallos) y VIII (bóveda). No graba en Anita.',
            'updated_at' => $ahora,
        ]);
        DB::table('menu')->where('url', 'caja/tesoreria-crown/anexo')->update([
            'nombre' => 'Anexos',
            'updated_at' => $ahora,
        ]);
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $ahora = now();
        DB::table('permiso')->where('slug', self::SLUG)->update([
            'nombre' => 'Listar anexo I de tesorería Crown',
            'ayuda' => 'Ver el anexo I del día, con mes y anual. Si Anita ya lo cerró, muestra ese detalle. Si no, muestra efectivo, bóveda y poder público. No graba en Anita.',
            'updated_at' => $ahora,
        ]);
        DB::table('menu')->where('url', 'caja/tesoreria-crown/anexo')->update([
            'nombre' => 'Anexo I',
            'updated_at' => $ahora,
        ]);
        SuitecrmPermiso::flushCachePermisos();
    }
};
