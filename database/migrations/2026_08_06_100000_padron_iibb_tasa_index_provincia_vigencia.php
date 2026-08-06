<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acelera el reemplazo del período al importar un padrón provincial y la consulta
 * de cobertura por provincia. Hasta ahora solo existía el índice por provincia_id,
 * que sobre 2,4 millones de filas obliga a filtrar las fechas fila por fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('padron_iibb_tasa', function (Blueprint $table) {
            $table->index(
                ['provincia_id', 'desdefecha', 'hastafecha'],
                'padron_iibb_tasa_provincia_vigencia_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('padron_iibb_tasa', function (Blueprint $table) {
            $table->dropIndex('padron_iibb_tasa_provincia_vigencia_index');
        });
    }
};
