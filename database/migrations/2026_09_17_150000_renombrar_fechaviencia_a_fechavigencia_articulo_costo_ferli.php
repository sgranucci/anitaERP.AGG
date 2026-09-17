<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calzados Ferli: corrige typo histórico articulo_costo.fechaviencia → fechavigencia.
 * El modelo y Contable (costos por tarea) ya escriben fechavigencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('articulo_costo')) {
            return;
        }

        if (Schema::hasColumn('articulo_costo', 'fechaviencia')
            && ! Schema::hasColumn('articulo_costo', 'fechavigencia')
        ) {
            Schema::table('articulo_costo', function (Blueprint $table) {
                $table->renameColumn('fechaviencia', 'fechavigencia');
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('articulo_costo')) {
            return;
        }

        if (Schema::hasColumn('articulo_costo', 'fechavigencia')
            && ! Schema::hasColumn('articulo_costo', 'fechaviencia')
        ) {
            Schema::table('articulo_costo', function (Blueprint $table) {
                $table->renameColumn('fechavigencia', 'fechaviencia');
            });
        }
    }
};
